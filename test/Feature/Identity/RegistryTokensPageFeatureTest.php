<?php

declare(strict_types=1);

namespace PackioTest\Feature\Identity;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function count;
use function random_bytes;

final class RegistryTokensPageFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itDisplaysRegistryTokensPageForOwner(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $token           = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Owner token',
        );

        $this->logIn($userDto->email, self::PASSWORD);

        $testResponse = $this->get('/organization/' . $organizationDto->slug . '/tokens', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        $testResponse->assertInertiaComponent('Tokens/Index');
        $testResponse->assertInertiaProp('organization.slug', $organizationDto->slug);
        $testResponse->assertInertiaProp('can_manage_tokens', true);
        $testResponse->assertJsonPath('props.tokens.0.id', $token->id);
    }

    #[Test]
    public function itDisplaysRegistryTokenLastUsedAtAfterTokenAuthentication(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $identity        = $this->getService(IdentityApiFacadeInterface::class);
        $token           = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Owner token',
        );

        $identity->authenticateRegistryAccessToken($token->plainTextToken);

        $this->logIn($userDto->email, self::PASSWORD);

        $testResponse = $this->get('/organization/' . $organizationDto->slug . '/tokens', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $lastUsedAt = $testResponse->json()['props']['tokens'][0]['last_used_at'] ?? '';

        $testResponse->assertStatus(200);
        self::assertNotSame('', $lastUsedAt);
    }

    #[Test]
    public function itDoesNotAllowNonOwnerToAccessTokenManagementForm(): void
    {
        $userDto         = $this->createUser();
        $member          = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $this->addMember($organizationDto, $member);
        $token    = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Shared token',
        );

        $this->logIn($member->email, self::PASSWORD);

        $testResponse = $this->get('/organization/' . $organizationDto->slug . '/tokens', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        $testResponse->assertInertiaComponent('Tokens/Index');
        $testResponse->assertInertiaProp('can_manage_tokens', false);
        $testResponse->assertJsonPath('props.tokens.0.id', $token->id);
        $testResponse->assertJsonPath('props.tokens.0.plain_token', null);
        self::assertStringNotContainsString($token->plainTextToken, $testResponse->body());

        $before = $this->countTokensInOrganization($organizationDto->id);
        $this->withCsrf();
        $this->post('/organization/' . $organizationDto->slug . '/tokens', [
            'name' => 'Member token',
        ])->assertRedirect('/organization/' . $organizationDto->slug);

        self::assertSame($before, $this->countTokensInOrganization($organizationDto->id));
    }

    #[Test]
    public function itRejectsRegistryTokensPageForAuthenticatedNonMembers(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $nonMember       = $this->createUser();

        $this->logIn($nonMember->email, self::PASSWORD);

        $this->get('/organization/' . $organizationDto->slug . '/tokens', [
            'X-Inertia' => 'true',
        ])->assertRedirect('/organizations');
    }

    #[Test]
    public function itCanIssueRegistryTokenViaHttpActionForOwner(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);

        $this->logIn($userDto->email, self::PASSWORD);
        $this->withCsrf();

        self::assertSame(0, $this->countTokensInOrganization($organizationDto->id));

        $this->post('/organization/' . $organizationDto->slug . '/tokens', [
            'name' => 'Deploy token',
        ])->assertRedirect('/organization/' . $organizationDto->slug . '/tokens');

        self::assertSame(1, $this->countTokensInOrganization($organizationDto->id));
    }

    #[Test]
    public function itDoesNotIssueTokenWithInvalidName(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);

        $this->logIn($userDto->email, self::PASSWORD);
        $this->withCsrf();

        $this->post('/organization/' . $organizationDto->slug . '/tokens', [
            'name' => '',
        ])->assertRedirect('/organization/' . $organizationDto->slug . '/tokens');

        self::assertSame(0, $this->countTokensInOrganization($organizationDto->id));
    }

    #[Test]
    public function itDoesNotRevokeTokenFromDifferentOrganizationContext(): void
    {
        $userDto               = $this->createUser();
        $organizationDto       = $this->createOrganization($userDto, 'primary-' . bin2hex(random_bytes(4)));
        $secondaryOrganization = $this->createOrganization($userDto, 'secondary-' . bin2hex(random_bytes(4)));

        $foreignToken = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $secondaryOrganization->id,
            $userDto->id,
            'Foreign token',
        );

        $this->logIn($userDto->email, self::PASSWORD);
        $this->withCsrf();

        $before = $this->countTokensInOrganization($secondaryOrganization->id);

        $this->post('/organization/' . $organizationDto->slug . '/tokens/' . $foreignToken->id . '/revoke')
            ->assertRedirect('/organization/' . $organizationDto->slug . '/tokens')
        ;

        self::assertSame($before, $this->countTokensInOrganization($secondaryOrganization->id));
    }

    #[Test]
    public function itRejectsRevokeRequestWithUnknownTokenId(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Deploy token',
        );

        $this->logIn($userDto->email, self::PASSWORD);
        $this->withCsrf();

        $before = $this->countTokensInOrganization($organizationDto->id);

        $this->post('/organization/' . $organizationDto->slug . '/tokens/not-a-uuid/revoke')->assertRedirect(
            '/organization/' . $organizationDto->slug . '/tokens',
        );

        self::assertSame($before, $this->countTokensInOrganization($organizationDto->id));
    }

    #[Test]
    public function itListsOnlyTokensForTheSelectedOrganization(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $other           = $this->createOrganization($userDto);
        $commands        = $this->getService(IdentityCommandService::class);
        $token           = $commands->issueRegistryTokenForUser($organizationDto->id, $userDto->id, 'Visible token');
        $commands->issueRegistryTokenForUser($other->id, $userDto->id, 'Foreign token');
        $this->logIn($userDto->email, self::PASSWORD);

        $testResponse = $this->get('/organization/' . $organizationDto->slug . '/tokens', [
            'X-Inertia' => 'true',
            'Accept'    => 'application/json',
        ]);
        $testResponse->assertStatus(200);
        self::assertCount(1, $testResponse->json()['props']['tokens']);
        $testResponse->assertJsonPath('props.tokens.0.id', $token->id);
        $testResponse->assertJsonPath('props.tokens.0.plain_token', $token->plainTextToken);
    }

    #[Test]
    public function itRevokesOwnTokenAndAllowsRepeatedRequests(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $identity        = $this->getService(IdentityApiFacadeInterface::class);
        $token           = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser($organizationDto->id, $userDto->id, 'Deploy token');
        $this->logIn($userDto->email, self::PASSWORD);
        $this->withCsrf();

        $url = '/organization/' . $organizationDto->slug . '/tokens/' . $token->id . '/revoke';
        $this->post($url)->assertRedirect('/organization/' . $organizationDto->slug . '/tokens');
        self::assertSame(0, $this->countTokensInOrganization($organizationDto->id));
        $this->post($url)->assertRedirect('/organization/' . $organizationDto->slug . '/tokens');
        self::assertNull($identity->authenticateRegistryAccessToken($token->plainTextToken));
    }

    private function logIn(string $email, string $password): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $email,
            'password' => $password,
        ])->assertRedirect('/organizations');
    }

    private function createUser(): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'registry-token-' . bin2hex(random_bytes(6)) . '@example.test',
            'Registry User',
            'Password1!',
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createOrganization(UserDto $userDto, ?string $name = null): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->registerOrganization(
            $name ?? ('org-' . bin2hex(random_bytes(6))),
            $userDto->id,
        );
    }

    private function addMember(OrganizationDto $organizationDto, UserDto $userDto): void
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organizations->addMember($organizationDto->id, $userDto->id);
    }

    private function countTokensInOrganization(string $organizationId): int
    {
        /** @var RegistryAccessTokenRepositoryInterface $tokens */
        $tokens = $this->getService(RegistryAccessTokenRepositoryInterface::class);

        return count($tokens->latestForOrganization($organizationId));
    }
}
