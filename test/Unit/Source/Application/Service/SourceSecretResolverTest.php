<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Cake\Chronos\Chronos;
use Common\Security\SecretEncryptorInterface;
use Common\Security\SodiumSecretEncryptor;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SourceOAuthTokenResolver;
use Packio\Source\Application\Service\SourceSecretResolver;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(SourceSecretResolver::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceSecretResolverTest extends TestCase
{
    use InertSourceCollaborators;

    /** @var MockObject&SourceRepositoryInterface */
    private MockObject $sourceRepository;

    /** @var MockObject&ProviderAuthorizationRepositoryInterface */
    private MockObject $providerAuthorizationRepository;

    /** @var MockObject&SourceOAuthAccountRepositoryInterface */
    private MockObject $sourceOAuthAccountRepository;

    /** @var MockObject&SecretEncryptorInterface */
    private MockObject $secretEncryptor;

    protected function setUp(): void
    {
        $this->sourceRepository                = $this->createMock(SourceRepositoryInterface::class);
        $this->providerAuthorizationRepository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $this->sourceOAuthAccountRepository    = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
        $this->secretEncryptor                 = $this->createMock(SecretEncryptorInterface::class);
    }

    #[Test]
    public function resolveAuthorizationSecretUsesStoredOAuthTokenResolver(): void
    {
        $providerAuthorization = $this->authorization(secret: 'enc_legacy');
        $sourceOAuthAccount    = $this->sourceOAuthAccount(accessToken: 'enc_oauth');

        $this->providerAuthorizationRepository->expects(self::once())
            ->method('findById')
            ->with('auth-1')
            ->willReturn($providerAuthorization)
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('enc_oauth')
            ->willReturn(true)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('enc_oauth')
            ->willReturn('canonical-oauth-token')
        ;

        self::assertSame('canonical-oauth-token', $this->resolver()->resolveAuthorizationSecret('auth-1'));
    }

    #[Test]
    public function resolveAuthorizationSecretDoesNotRefreshExpiredOAuthToken(): void
    {
        $providerAuthorization = $this->authorization(secret: 'enc_legacy');
        $sourceOAuthAccount    = $this->sourceOAuthAccount(
            accessToken: 'enc_oauth',
            refreshToken: 'enc_refresh',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );
        $oauthProvider = $this->createMock(OAuthProviderPort::class);

        $this->providerAuthorizationRepository->expects(self::once())
            ->method('findById')
            ->with('auth-1')
            ->willReturn($providerAuthorization)
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('enc_oauth')
            ->willReturn(true)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('enc_oauth')
            ->willReturn('expired-but-stored-token')
        ;
        $oauthProvider->expects(self::never())->method('refreshAccessToken');

        self::assertSame('expired-but-stored-token', $this->resolver($oauthProvider)->resolveAuthorizationSecret('auth-1'));
    }

    #[Test]
    public function resolveAuthorizationSecretDoesNotFallBackToLegacyCopiedTokenWhenOAuthTokenCannotBeResolved(): void
    {
        $providerAuthorization = $this->authorization(secret: 'enc_legacy');
        $sourceOAuthAccount    = $this->sourceOAuthAccount(accessToken: 'enc_oauth');
        $sourceOAuthAccount->markAsInactive();

        $this->providerAuthorizationRepository->expects(self::once())
            ->method('findById')
            ->with('auth-1')
            ->willReturn($providerAuthorization)
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;
        $this->secretEncryptor->expects(self::never())->method('decrypt');

        $this->expectException(SourceTokenRefreshUnavailableException::class);
        $this->expectExceptionMessage('inactive');

        $this->resolver()->resolveAuthorizationSecret('auth-1');
    }

    #[Test]
    public function resolveSourceAuthorizationSecretDoesNotFallBackToLegacyCopiedTokenWhenOAuthTokenCannotBeResolved(): void
    {
        $source                = $this->source();
        $providerAuthorization = $this->authorization(secret: 'enc_legacy');
        $sourceOAuthAccount    = $this->sourceOAuthAccount(accessToken: 'enc_oauth');
        $sourceOAuthAccount->markAsInactive();

        $this->sourceRepository->expects(self::once())
            ->method('findById')
            ->with('source-1')
            ->willReturn($source)
        ;
        $this->providerAuthorizationRepository->expects(self::once())
            ->method('findByIdInOrganization')
            ->with('auth-1', 'org-1')
            ->willReturn($providerAuthorization)
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;
        $this->secretEncryptor->expects(self::never())->method('decrypt');

        $this->expectException(SourceTokenRefreshUnavailableException::class);
        $this->expectExceptionMessage('inactive');

        $this->resolver()->resolveSourceAuthorizationSecret('source-1');
    }

    #[Test]
    public function resolveSourceAuthorizationSecretUsesCopiedTokenForNonOAuthAuthorization(): void
    {
        $source                = $this->source();
        $providerAuthorization = $this->authorization(secret: 'enc_legacy', sourceOAuthAccountId: null);

        $this->sourceRepository->expects(self::once())
            ->method('findById')
            ->with('source-1')
            ->willReturn($source)
        ;
        $this->providerAuthorizationRepository->expects(self::once())
            ->method('findByIdInOrganization')
            ->with('auth-1', 'org-1')
            ->willReturn($providerAuthorization)
        ;
        $this->sourceOAuthAccountRepository->expects(self::never())->method('findById');
        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('enc_legacy')
            ->willReturn(true)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('enc_legacy')
            ->willReturn('legacy-token')
        ;

        self::assertSame('legacy-token', $this->resolver()->resolveSourceAuthorizationSecret('source-1'));
    }

    private function resolver(?OAuthProviderPort $oAuthProviderPort = null): SourceSecretResolver
    {
        return new SourceSecretResolver(
            $this->sourceRepository,
            $this->providerAuthorizationRepository,
            new ProviderAuthorizationSecrets($this->secretEncryptor),
            new SourceOAuthTokenResolver(
                $this->sourceOAuthAccountRepository,
                $this->providerAuthorizationRepository,
                $this->sourceRepository,
                new SourceOAuthAccountSecrets($this->secretEncryptor),
                $oAuthProviderPort ?? $this->createMock(OAuthProviderPort::class),
                $this->createStub(SourceProviderInstanceRepositoryInterface::class),
                $this->inertSourceProviderInstanceOAuthConfig(),
            ),
            new SourceWebhookSecrets(new SodiumSecretEncryptor('test-webhook-key')),
        );
    }

    private function authorization(string $secret, ?string $sourceOAuthAccountId = 'soa-1'): ProviderAuthorization
    {
        $providerAuthorization = $this->createMock(ProviderAuthorization::class);
        $providerAuthorization->method('getIdentifier')
            ->willReturn(Uuid::fromString('00000000-0000-0000-0000-000000000001'))
        ;
        $providerAuthorization->method('sourceOAuthAccountId')->willReturn($sourceOAuthAccountId);
        $providerAuthorization->method('provider')->willReturn('github');
        $providerAuthorization->method('organizationId')->willReturn('org-1');
        $providerAuthorization->method('secret')->with('token')->willReturn($secret);

        return $providerAuthorization;
    }

    private function source(): Source
    {
        $source = new Source(
            organizationId: 'org-1',
            provider: 'github',
            canonicalUrl: 'https://github.com/acme/package',
            displayName: 'acme/package',
            providerAuthorizationId: 'auth-1',
        );
        $source->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000003'));

        return $source;
    }

    private function sourceOAuthAccount(
        string $accessToken,
        ?string $refreshToken = null,
        ?Chronos $tokenExpiresAt = null
    ): SourceOAuthAccount {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            tokenExpiresAt: $tokenExpiresAt,
        );
        $sourceOAuthAccount->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000002'));

        return $sourceOAuthAccount;
    }
}
