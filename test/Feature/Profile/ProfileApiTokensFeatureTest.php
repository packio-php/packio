<?php

declare(strict_types=1);

namespace PackioTest\Feature\Profile;

use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function array_find;
use function bin2hex;
use function json_encode;
use function random_bytes;

final class ProfileApiTokensFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itManagesApiTokensFromProfile(): void
    {
        $userDto = $this->createPasswordUser();
        $this->identity()->issueApiAccessTokenForUser($userDto->id, 'Existing token', ['read']);

        $this->logIn($userDto);

        $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])
            ->assertStatus(200)
            ->assertInertiaComponent('Profile/Show')
            ->assertJsonPath('props.api_tokens.0.name', 'Existing token')
            ->assertJsonPath('props.api_tokens.0.scopes.0', 'read')
        ;

        $this->withCsrf();
        $this->post('/profile/api-tokens', [
            'name'   => 'Automation token',
            'scopes' => ['read', 'write'],
        ])->assertRedirect('/profile');

        $issuedApiAccessTokenDto = $this->apiToken($userDto->id, 'Automation token');
        self::assertSame(['read', 'write'], $issuedApiAccessTokenDto->scopes);

        $testResponse = $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        self::assertStringContainsString(
            'New API token for "Automation token": packio_api_',
            (string) ($testResponse->json()['flash']['success'] ?? ''),
        );

        $this->withCsrf();
        $this->post('/profile/api-tokens/' . $issuedApiAccessTokenDto->id . '/revoke')
            ->assertRedirect('/profile')
        ;

        self::assertTrue($this->apiToken($userDto->id, 'Automation token')->isRevoked());

        $this->withCsrf();
        $this->request('DELETE', '/profile/api-tokens/' . $issuedApiAccessTokenDto->id)
            ->assertRedirect('/profile')
        ;

        self::assertNull($this->apiTokenOrNull($userDto->id, 'Automation token'));
    }

    #[Test]
    public function itDoesNotIssueAdminApiScopeForRegularUsers(): void
    {
        $userDto = $this->createPasswordUser();
        $this->logIn($userDto);

        $this->withCsrf();
        $this->post('/profile/api-tokens', [
            'name'   => 'Admin request',
            'scopes' => ['admin'],
        ])->assertRedirect('/profile');

        self::assertSame(['read'], $this->apiToken($userDto->id, 'Admin request')->scopes);
    }

    #[Test]
    public function itUsesTheInertiaToastValidationPolicyWhenApiTokenMappingFailsWithoutRetainingSecrets(): void
    {
        $userDto = $this->createPasswordUser();
        $secret  = 'profile-mapping-secret-' . bin2hex(random_bytes(6));
        $this->logIn($userDto);

        $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $this->withCsrf('/profile')->post('/profile/api-tokens', [
            'name'           => ['not-a-string'],
            'secret_payload' => $secret,
        ], [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->assertRedirect('/profile');

        $page = $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->json();

        self::assertSame(['Invalid value.'], $page['props']['errors']['name'] ?? null);
        self::assertSame([], $page['props']['old'] ?? null);
        self::assertSame('Invalid API token request.', $page['flash']['toasts'][0]['message'] ?? null);
        self::assertStringNotContainsString($secret, json_encode($page, JSON_THROW_ON_ERROR));
        self::assertNull($this->apiTokenOrNull($userDto->id, 'not-a-string'));
    }

    #[Test]
    public function itRetainsThePackageMappingErrorFallbackForTheUnannotatedPasswordRoute(): void
    {
        $userDto = $this->createPasswordUser();
        $this->logIn($userDto);

        $testResponse = $this->withCsrf('/profile')->post('/profile/password', [
            'new_password'              => ['not-a-string'],
            'new_password_confirmation' => 'Password1!',
            'current_password'          => self::PASSWORD,
        ]);

        $testResponse
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error', 'Mapping failed')
        ;

        $messages = $testResponse->json()['messages'] ?? null;
        self::assertIsArray($messages);
        self::assertNotSame([], $messages);
        $authenticatedUser = $this->identity()->authenticateCredentials($userDto->email, self::PASSWORD);
        self::assertNotNull($authenticatedUser);
        self::assertSame($userDto->id, $authenticatedUser->id);
    }

    #[Test]
    public function itKeepsTheUnannotatedPasswordMappingFallbackOutsideTheInertiaProtocol(): void
    {
        $userDto = $this->createPasswordUser();
        $this->logIn($userDto);

        $testResponse = $this->withCsrf('/profile')->post('/profile/password', [
            'new_password'              => ['not-a-string'],
            'new_password_confirmation' => 'Password1!',
            'current_password'          => self::PASSWORD,
        ], [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertNoInertiaResponse()
            ->assertJsonPath('error', 'Mapping failed')
        ;
        self::assertSame('Origin', $testResponse->header('Vary'));
        self::assertSame('', $testResponse->header('X-Packio-Inertia-Fallback'));
    }

    #[Test]
    public function itDoesNotDeleteActiveApiTokensFromProfile(): void
    {
        $userDto                  = $this->createPasswordUser();
        $issuedApiAccessTokenDto  = $this->identity()->issueApiAccessTokenForUser($userDto->id, 'Active token', ['read']);

        $this->logIn($userDto);

        $this->withCsrf();
        $this->request('DELETE', '/profile/api-tokens/' . $issuedApiAccessTokenDto->token->id)
            ->assertRedirect('/profile')
        ;

        self::assertFalse($this->apiToken($userDto->id, 'Active token')->isRevoked());
    }

    #[Test]
    public function revokeWithInvalidTokenIdShowsNotFound(): void
    {
        $this->logIn($this->createPasswordUser());

        $this->withCsrf();
        $this->post('/profile/api-tokens/not-a-uuid/revoke')->assertRedirect('/profile');

        $this->withCsrf();
        $this->request('DELETE', '/profile/api-tokens/not-a-uuid')->assertRedirect('/profile');
    }

    private function createPasswordUser(): UserDto
    {
        $userDto = $this->identity()->createUser(
            'profile-api-token-' . bin2hex(random_bytes(6)) . '@example.test',
            'Profile API User',
            self::PASSWORD,
        );
        $this->identity()->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function logIn(UserDto $userDto): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }

    private function apiToken(string $userId, string $name): ApiAccessTokenDto
    {
        $apiAccessTokenDto = $this->apiTokenOrNull($userId, $name);

        if (! $apiAccessTokenDto instanceof ApiAccessTokenDto) {
            throw new RuntimeException('API token was not found.');
        }

        return $apiAccessTokenDto;
    }

    private function apiTokenOrNull(string $userId, string $name): ?ApiAccessTokenDto
    {
        return array_find(
            $this->identity()->apiAccessTokensForUser($userId),
            static fn (ApiAccessTokenDto $apiAccessTokenDto): bool => $apiAccessTokenDto->name === $name,
        );
    }

    private function identity(): IdentityApiFacadeInterface
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        if (! $identity instanceof IdentityApiFacadeInterface) {
            throw new RuntimeException('Identity API facade is not configured.');
        }

        return $identity;
    }
}
