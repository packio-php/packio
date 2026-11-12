<?php

declare(strict_types=1);

namespace PackioTest\Feature\Source;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceProviderInstanceRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Application\Dto\OAuthTokenData;
use Packio\Source\Application\Dto\OAuthUserData;
use Packio\Source\Application\Port\OAuthProviderPort;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;

final class SourceOAuthCallbackFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    protected function setUp(): void
    {
        $this->serviceOverrides[OAuthProviderPort::class] = new FakeSourceOAuthProviderPort();

        parent::setUp();
    }

    #[Test]
    public function itMapsBothSourceOAuthCallbackPathsIncludingExistingAccounts(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);

        $sourceOAuthAccountDto = $sourceApi->handleSourceOAuthCallback(
            'github',
            'standard-code',
            $owner->id,
            $organization->slug,
            'standard-verifier',
        );
        $existingSourceOAuthAccountDto = $sourceApi->handleSourceOAuthCallback(
            'github',
            'standard-code-repeat',
            $owner->id,
            $organization->slug,
        );

        self::assertNotSame('', $sourceOAuthAccountDto->id);
        self::assertSame($sourceOAuthAccountDto->id, $existingSourceOAuthAccountDto->id);
        self::assertSame('github', $existingSourceOAuthAccountDto->provider);
        self::assertSame('standard-user', $existingSourceOAuthAccountDto->providerUsername);
        self::assertSame('standard@example.test', $existingSourceOAuthAccountDto->providerEmail);

        $providerInstanceId              = $this->createOAuthProviderInstance($organization, $owner);
        $providerInstanceOAuthAccountDto = $sourceApi->handleSourceProviderInstanceOAuthCallback(
            'gitlab_self_hosted',
            'instance-code',
            $owner->id,
            $organization->slug,
            $providerInstanceId,
            'instance-verifier',
        );
        $existingProviderInstanceOAuthAccountDto = $sourceApi->handleSourceProviderInstanceOAuthCallback(
            'gitlab_self_hosted',
            'instance-code-repeat',
            $owner->id,
            $organization->slug,
            $providerInstanceId,
        );

        self::assertNotSame('', $providerInstanceOAuthAccountDto->id);
        self::assertSame($providerInstanceOAuthAccountDto->id, $existingProviderInstanceOAuthAccountDto->id);
        self::assertSame('gitlab_self_hosted', $existingProviderInstanceOAuthAccountDto->provider);
        self::assertSame('instance-user', $existingProviderInstanceOAuthAccountDto->providerUsername);
        self::assertSame('instance@example.test', $existingProviderInstanceOAuthAccountDto->providerEmail);
    }

    /** @return array{0: UserDto, 1: OrganizationDto} */
    private function createOrganizationWithOwner(): array
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'source-oauth-owner-' . bin2hex(random_bytes(4)) . '@example.test',
            'Source OAuth Owner',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return [
            $userDto,
            $organizations->registerOrganization(
                'source-oauth-org-' . bin2hex(random_bytes(4)),
                $userDto->id,
            ),
        ];
    }

    private function createOAuthProviderInstance(OrganizationDto $organizationDto, UserDto $userDto): string
    {
        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);

        return $sourceApi->createSourceProviderInstanceInContext(
            new ActorContext($organizationDto->id, $userDto->id, 'owner'),
            new SourceProviderInstanceRequestDto(
                'gitlab_self_hosted',
                'Feature GitLab OAuth',
                'https://gitlab-' . bin2hex(random_bytes(4)) . '.example.test',
                'feature-client-id',
                'feature-client-secret',
                'oauth2',
            ),
        )->id;
    }
}

final readonly class FakeSourceOAuthProviderPort implements OAuthProviderPort
{
    public function exchangeCodeForToken(string $provider, string $code, ?string $codeVerifier = null): OAuthTokenData
    {
        return new OAuthTokenData('standard-access-token', 'standard-refresh-token');
    }

    public function exchangeCodeForTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $code,
        ?string $codeVerifier = null,
    ): OAuthTokenData {
        return new OAuthTokenData('instance-access-token', 'instance-refresh-token');
    }

    public function fetchUser(string $provider, string $accessToken): OAuthUserData
    {
        return new OAuthUserData('standard-provider-user', 'standard@example.test', 'standard-user');
    }

    public function fetchUserForProviderInstance(string $provider, array $providerInstanceConfig, string $accessToken): OAuthUserData
    {
        return new OAuthUserData('instance-provider-user', 'instance@example.test', 'instance-user');
    }

    public function sourceScopes(string $provider): array
    {
        return ['read:user'];
    }

    public function verifyToken(string $provider, string $accessToken): bool
    {
        return true;
    }

    public function refreshAccessToken(string $provider, string $refreshToken): OAuthTokenData
    {
        return new OAuthTokenData('refreshed-access-token');
    }

    public function refreshAccessTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $refreshToken,
    ): OAuthTokenData {
        return new OAuthTokenData('refreshed-instance-access-token');
    }

    public function getAuthorizationUrlForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $state,
        array $scopes,
        ?string $codeChallenge = null,
    ): string {
        return 'https://oauth.example.test/authorize';
    }

    public function sourceConnectAvailability(): array
    {
        return [];
    }
}
