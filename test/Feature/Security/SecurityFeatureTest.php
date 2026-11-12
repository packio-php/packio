<?php

declare(strict_types=1);

namespace PackioTest\Feature\Security;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Base\ForkedChildProcess;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Throwable;

use function bin2hex;
use function fclose;
use function file_put_contents;
use function fread;
use function function_exists;
use function fwrite;
use function pcntl_fork;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class SecurityFeatureTest extends FeatureTestCase
{
    use ForkedChildProcess;

    #[Test]
    public function itRejectsUnsafeWebFormRequestWithoutCsrfToken(): void
    {
        $this->get('/auth/login');

        $testResponse = $this->post('/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $testResponse->assertStatus(419);
    }

    #[Test]
    public function itDeliversOneSessionTokenThroughInertiaAndReusesItForSuccessiveMutations(): void
    {
        $testResponse = $this->get('/auth/login', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $token = $testResponse->json()['props']['csrf']['token'] ?? null;

        self::assertIsString($token);
        self::assertNotSame('', $token);

        $this->withCsrf();
        $this->post('/auth/verification/send', [
            'email' => 'available@example.test',
        ])->assertOk();
        $this->post('/auth/verification/send', [
            'email' => 'available@example.test',
        ])->assertOk();

        self::assertSame($token, $this->loadCsrfToken());
    }

    #[Test]
    #[Group('slow')]
    public function itAcceptsOverlappingUnsafeRequestsWithTheSameSessionToken(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $token     = $this->withCsrf()->loadCsrfToken();
        $errorPath = $this->newErrorPath();
        $this->disconnectDatabaseBeforeFork();

        [$firstParent, $firstChild]   = $this->socketPair();
        [$secondParent, $secondChild] = $this->socketPair();
        $firstPid                     = $this->forkCsrfRequest($firstChild, $errorPath, 'first');
        $secondPid                    = $this->forkCsrfRequest($secondChild, $errorPath, 'second');
        fclose($firstChild);
        fclose($secondChild);

        fwrite($firstParent, '1');
        fwrite($secondParent, '1');
        fclose($firstParent);
        fclose($secondParent);

        $this->assertChildExitedSuccessfully($firstPid, $errorPath);
        $this->assertChildExitedSuccessfully($secondPid, $errorPath);
        self::assertSame($token, $this->loadCsrfToken());

        unlink($errorPath);
    }

    #[Test]
    public function itInvalidatesThePreviousTokenAfterSuccessfulLoginAndLogout(): void
    {
        $password = 'Password1!';

        /** @var IdentityApiFacadeInterface $identity */
        $identity    = $this->getService(IdentityApiFacadeInterface::class);
        $userDto     = $identity->createUser(
            'csrf-' . bin2hex(random_bytes(6)) . '@example.test',
            'CSRF Test User',
            $password,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        $staleBeforeLogin = $this->loadCsrfToken();
        $this->withCsrf();
        $this->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => $password,
        ])->assertRedirect('/organizations');

        $this->post('/auth/verification/send', [
            'email' => 'available@example.test',
        ], [
            'Origin'       => 'https://localhost',
            'X-CSRF-Token' => $staleBeforeLogin,
        ])->assertStatus(419);

        $currentToken = $this->loadCsrfToken('/organizations');
        $this->post('/auth/logout', [], [
            'Origin'       => 'https://localhost',
            'X-CSRF-Token' => $currentToken,
        ])->assertRedirect('/auth/login');

        $this->post('/auth/verification/send', [
            'email' => 'available@example.test',
        ], [
            'Origin'       => 'https://localhost',
            'X-CSRF-Token' => $currentToken,
        ])->assertStatus(419);
    }

    #[Test]
    public function itAllowsWebhookEndpointWithoutCsrfToken(): void
    {
        $testResponse = $this->post('/webhooks/github/demo');

        $testResponse->assertNotStatus(419);
    }

    #[Test]
    public function itDoesNotRequireCsrfForRegistryPackagesEndpoint(): void
    {
        $testResponse = $this->get('/packages.json');

        $testResponse->assertNotStatus(419);
    }

    #[Test]
    public function itRedirectsAnonymousUserFromProtectedPageToLogin(): void
    {
        $testResponse = $this->get('/organizations');

        $testResponse->assertRedirect('/auth/login');
    }

    #[Test]
    public function itReturnsUnauthorizedForPrivateRegistryWithoutToken(): void
    {
        $testResponse = $this->get('/private/packages.json');

        $testResponse->assertStatus(401);
        $testResponse->assertJsonPath(
            'error',
            'Authentication required. Provide a valid registry token via Authorization header.',
        );
    }

    #[Test]
    public function itRateLimitsRepeatedPrivateRegistryAuthFailures(): void
    {
        for ($attempt = 1; $attempt <= 30; ++$attempt) {
            $this->get('/private/packages.json')->assertStatus(401);
        }

        $testResponse = $this->get('/private/packages.json');

        $testResponse->assertStatus(429);
        $testResponse->assertHeader('Retry-After', '60');
        $testResponse->assertJsonPath(
            'error',
            'Too many registry authentication attempts. Please try again later.',
        );
    }

    #[Test]
    public function itRateLimitsRepeatedWebhookRequests(): void
    {
        $sourceId = Uuid::uuid7()->toString();

        for ($attempt = 1; $attempt <= 60; ++$attempt) {
            $this->post('/webhooks/github/' . $sourceId)->assertStatus(404);
        }

        $testResponse = $this->post('/webhooks/github/' . $sourceId);

        $testResponse->assertStatus(429);
        $testResponse->assertHeader('Retry-After', '60');
        $testResponse->assertJsonPath('error', 'Too many webhook requests. Please try again later.');
    }

    #[Test]
    public function changingWebhookProviderCannotBypassRateLimit(): void
    {
        $sourceId = 'unknown-source';
        for ($attempt = 0; $attempt < 60; ++$attempt) {
            $this->post('/webhooks/random-' . $attempt . '/' . $sourceId)->assertStatus(404);
        }
        $this->post('/webhooks/github/' . $sourceId)->assertStatus(429);
    }

    #[Test]
    public function itRateLimitsRepeatedLoginFailures(): void
    {
        $this->withCsrf();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->post('/auth/login', [
                'email'    => 'missing@example.test',
                'password' => 'wrong-password',
            ])->assertRedirect('/auth/login');
        }

        $testResponse = $this->post('/auth/login', [
            'email'    => 'missing@example.test',
            'password' => 'wrong-password',
        ]);

        $testResponse->assertRedirect('/auth/login');
        $testResponse->assertHeader('Retry-After', '900');
    }

    #[Test]
    public function sixthOAuthEmailAttemptIsThrottled(): void
    {
        $this->withCsrf();
        $body = [
            'email' => 'oauth-throttle@example.test',
        ];

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->post('/auth/oauth/email', $body)->assertRedirect('/auth/login');
        }

        $testResponse = $this->post('/auth/oauth/email', $body);

        $testResponse->assertRedirect('/auth/oauth/email');
        $testResponse->assertHeader('Retry-After', '900');
    }

    #[Test]
    public function itReturnsForbiddenWhenMemberAttemptsOwnerOnlyAction(): void
    {
        $password = 'Password1!';

        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'member-' . bin2hex(random_bytes(6)) . '@example.test',
            'Member User',
            $password,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations   = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDto = $organizations->registerOrganization('feature-forbidden-' . bin2hex(random_bytes(4)));
        $organizations->addMember($organizationDto->id, $userDto->id, 'member');

        $this->withCsrf();
        $this->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => $password,
        ])->assertRedirect('/organizations');

        $this->withCsrf('/organization/' . $organizationDto->slug . '/sources');
        $testResponse = $this->post(
            '/organization/' . $organizationDto->slug . '/sources/source-123/webhook/register',
            [],
            [
                'Accept' => 'application/json',
            ],
        );

        $testResponse->assertStatus(403);
        $testResponse->assertJsonPath('error', 'Forbidden');
    }

    private function forkCsrfRequest(mixed $startSocket, string $errorPath, string $suffix): int
    {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            try {
                fread($startSocket, 1);
                fclose($startSocket);

                $this->post('/auth/verification/send', [
                    'email' => 'concurrent-' . $suffix . '@example.test',
                ])->assertOk();

                exit(0);
            } catch (Throwable $throwable) {
                file_put_contents(
                    $errorPath,
                    sprintf("%s: %s\n", $throwable::class, $throwable->getMessage()),
                    FILE_APPEND,
                );

                exit(1);
            }
        }

        return $pid;
    }

    private function newErrorPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'packio-csrf-concurrency-errors-');
        self::assertIsString($path);

        return $path;
    }
}
