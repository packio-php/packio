<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Cake\Chronos\Chronos;
use Common\Branding\BrandingConfig;
use Common\Security\SecretEncryptorInterface;
use Packio\Source\Application\Dto\OAuthTokenData;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Security\SourceProviderInstanceSecrets;
use Packio\Source\Application\Service\SourceOAuthTokenResolver;
use Packio\Source\Application\Service\SourceProviderInstanceOAuthConfig;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Exception\ProviderRefreshRejectedException;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountLockingRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

#[CoversClass(SourceOAuthTokenResolver::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceOAuthTokenResolverTest extends TestCase
{
    use InertSourceCollaborators;

    /** @var (MockObject&SourceOAuthAccountRepositoryInterface) */
    private MockObject $oauthAccountRepo;

    /** @var (MockObject&ProviderAuthorizationRepositoryInterface) */
    private MockObject $authRepo;

    /** @var (MockObject&SourceRepositoryInterface) */
    private MockObject $sourceRepo;

    private SourceOAuthAccountSecrets $sourceOAuthAccountSecrets;

    /** @var (MockObject&OAuthProviderPort) */
    private MockObject $oauthProvider;

    /** @var (MockObject&SecretEncryptorInterface) */
    private MockObject $secretEncryptor;

    protected function setUp(): void
    {
        $this->oauthAccountRepo          = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
        $this->authRepo                  = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $this->sourceRepo                = $this->createMock(SourceRepositoryInterface::class);
        $this->secretEncryptor           = $this->createMock(SecretEncryptorInterface::class);
        $this->sourceOAuthAccountSecrets = new SourceOAuthAccountSecrets($this->secretEncryptor);
        $this->oauthProvider             = $this->createMock(OAuthProviderPort::class);
    }

    // ── resolveForAuthorization: not found ──────────────────────────────────

    #[Test]
    public function resolveForAuthorizationThrowsWhenNotFound(): void
    {
        $this->authRepo->expects(self::once())
            ->method('findById')
            ->with('auth-1')
            ->willReturn(null)
        ;

        $sourceOAuthTokenResolver = $this->createResolver();

        $this->expectException(SourceTokenMissingException::class);
        $this->expectExceptionMessage('Provider authorization not found.');

        $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');
    }

    // ── Non-OAuth authorization ─────────────────────────────────────────────

    #[Test]
    public function resolveReturnsEmptyTokenForNonOAuthAuthorization(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: null);

        $this->authRepo->expects(self::once())
            ->method('findById')
            ->with('auth-1')
            ->willReturn($providerAuthorization)
        ;

        $sourceOAuthTokenResolver = $this->createResolver();

        $result = $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');

        self::assertSame('', $result['access_token']);
        self::assertFalse($result['refreshed']);
        self::assertNull($result['oauth_account_id']);
    }

    #[Test]
    public function resolveReturnsEmptyTokenForEmptyOAuthAccountId(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: '');

        $this->authRepo->expects(self::once())
            ->method('findById')
            ->willReturn($providerAuthorization)
        ;

        $sourceOAuthTokenResolver = $this->createResolver();

        $result = $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');

        self::assertSame('', $result['access_token']);
        self::assertFalse($result['refreshed']);
        self::assertNull($result['oauth_account_id']);
    }

    #[Test]
    public function resolveForProviderAuthorizationUsesAlreadyLoadedAuthorization(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        $sourceOAuthAccount    = $this->createAccount(
            accessToken: 'enc_at',
            tokenExpiresAt: Chronos::parse('+2 hours'),
        );

        $this->authRepo->expects(self::never())->method('findById');
        $this->oauthAccountRepo->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('enc_at')
            ->willReturn(true)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('enc_at')
            ->willReturn('plaintext-token')
        ;

        $result = $this->createResolver()->resolveForProviderAuthorization($providerAuthorization);

        self::assertSame('plaintext-token', $result['access_token']);
        self::assertFalse($result['refreshed']);
        self::assertNotEmpty($result['oauth_account_id']);
    }

    #[Test]
    public function resolveStoredForProviderAuthorizationDoesNotRefreshExpiredToken(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        $sourceOAuthAccount    = $this->createAccount(
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );

        $this->authRepo->expects(self::never())->method('findById');
        $this->oauthAccountRepo->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('enc_at')
            ->willReturn(true)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('enc_at')
            ->willReturn('expired-token')
        ;
        $this->oauthProvider->expects(self::never())->method('refreshAccessToken');

        $result = $this->createResolver()->resolveStoredForProviderAuthorization($providerAuthorization);

        self::assertSame('expired-token', $result['access_token']);
        self::assertFalse($result['refreshed']);
        self::assertNotEmpty($result['oauth_account_id']);
    }

    // ── OAuth account not found ─────────────────────────────────────────────

    #[Test]
    public function resolveThrowsWhenOAuthAccountNotFound(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn(null)
        ;

        $sourceOAuthTokenResolver = $this->createResolver();

        $this->expectException(SourceTokenMissingException::class);
        $this->expectExceptionMessage('Linked OAuth account not found.');

        $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');
    }

    // ── Inactive OAuth account ──────────────────────────────────────────────

    #[Test]
    public function resolveThrowsWhenAccountIsInactive(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        $sourceOAuthAccount    = $this->createAccount(accessToken: 'enc_at', scopes: ['repo']);
        $sourceOAuthAccount->markAsInactive();

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);

        $sourceOAuthTokenResolver = $this->createResolver();

        $this->expectException(SourceTokenRefreshUnavailableException::class);
        $this->expectExceptionMessage('inactive');

        $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');
    }

    #[Test]
    public function resolveThrowsWhenOAuthAccountProviderDoesNotMatchAuthorization(): void
    {
        $providerAuthorization = $this->createAuthorization(
            id: 'auth-1',
            sourceOAuthAccountId: 'soa-1',
            provider: 'github',
        );
        $sourceOAuthAccount = $this->createAccount(
            provider: 'gitlab',
            accessToken: 'enc_at',
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);
        $this->secretEncryptor->expects(self::never())->method('decrypt');

        $sourceOAuthTokenResolver = $this->createResolver();

        $this->expectException(SourceTokenRefreshUnavailableException::class);
        $this->expectExceptionMessage('does not match provider authorization');

        $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');
    }

    // ── Valid (non-expired) token ───────────────────────────────────────────

    #[Test]
    public function resolveReturnsTokenWhenNotExpired(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        $sourceOAuthAccount    = $this->createAccount(
            accessToken: 'enc_at',
            tokenExpiresAt: Chronos::parse('+2 hours'),
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);

        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('enc_at')
            ->willReturn(true)
        ;

        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('enc_at')
            ->willReturn('plaintext-token')
        ;

        $sourceOAuthTokenResolver = $this->createResolver();

        $result = $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');

        self::assertSame('plaintext-token', $result['access_token']);
        self::assertFalse($result['refreshed']);
        self::assertNotEmpty($result['oauth_account_id']); // account UUID
    }

    #[Test]
    public function resolveReturnsTokenForCanonicalAccountInAnotherOrganization(): void
    {
        $providerAuthorization = $this->createAuthorization(
            id: 'auth-1',
            sourceOAuthAccountId: 'soa-1',
            organizationId: 'org-2',
        );
        $sourceOAuthAccount = $this->createAccount(
            accessToken: 'enc_at',
            tokenExpiresAt: Chronos::parse('+2 hours'),
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);
        $this->secretEncryptor->method('isEncrypted')->with('enc_at')->willReturn(true);
        $this->secretEncryptor->method('decrypt')->with('enc_at')->willReturn('plaintext-token');

        $result = $this->createResolver()->resolveForAuthorization('auth-1');

        self::assertSame('plaintext-token', $result['access_token']);
        self::assertFalse($result['refreshed']);
    }

    // ── Expired token, no refresh token → reauthorization required ──────────

    #[Test]
    public function resolveThrowsWhenExpiredAndNoRefreshToken(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        $sourceOAuthAccount    = $this->createAccount(
            accessToken: 'enc_at',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);

        // Decrypt access token
        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('enc_at')
            ->willReturn(true)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('enc_at')
            ->willReturn('plaintext-token')
        ;

        // Account has null refresh token, so decryptRefreshToken returns null without calling encryptor
        // → throws SourceTokenRefreshUnavailableException

        $sourceOAuthTokenResolver = $this->createResolver();

        $this->expectException(SourceTokenRefreshUnavailableException::class);
        $this->expectExceptionMessage('No refresh token available');

        $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');
    }

    // ── Expired token, successful refresh ───────────────────────────────────

    #[Test]
    public function resolveRefreshesWhenExpired(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        $sourceOAuthAccount    = $this->createAccount(
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);

        // Decrypt access token → check isTokenExpired
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('isEncrypted')
            ->willReturnMap([
                ['enc_at', true],
                ['enc_rt', true],
            ])
        ;

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['enc_at', 'old-at'],
                ['enc_rt', 'old-rt'],
            ])
        ;

        // Token is expired → calls refreshAccessToken
        $this->oauthProvider->expects(self::once())
            ->method('refreshAccessToken')
            ->with('github', 'old-rt')
            ->willReturn(new OAuthTokenData('new-at', 'new-rt', 7200))
        ;

        // Encrypt new tokens for storage
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['new-at', 'new-enc-at'],
                ['new-rt', 'new-enc-rt'],
            ])
        ;

        $this->oauthAccountRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn (SourceOAuthAccount $sourceOAuthAccount): bool => $sourceOAuthAccount->isActive()
                && 'new-enc-at' === $sourceOAuthAccount->storedAccessToken()
                && 'new-enc-rt' === $sourceOAuthAccount->storedRefreshToken()
            ))
        ;

        $sourceOAuthTokenResolver = $this->createResolver();

        $result = $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');

        self::assertSame('new-at', $result['access_token']);
        self::assertTrue($result['refreshed']);
        self::assertNotEmpty($result['oauth_account_id']); // account UUID
    }

    #[Test]
    public function resolveKeepsExistingRefreshTokenWhenRefreshResponseOmitsOne(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        $sourceOAuthAccount    = $this->createAccount(
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('isEncrypted')
            ->willReturnMap([
                ['enc_at', true],
                ['enc_rt', true],
            ])
        ;
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['enc_at', 'old-at'],
                ['enc_rt', 'old-rt'],
            ])
        ;
        $this->oauthProvider->expects(self::once())
            ->method('refreshAccessToken')
            ->with('github', 'old-rt')
            ->willReturn(new OAuthTokenData('new-at', null, 7200))
        ;
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['new-at', 'new-enc-at'],
                ['old-rt', 'old-enc-rt'],
            ])
        ;
        $this->oauthAccountRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn (SourceOAuthAccount $sourceOAuthAccount): bool => 'new-enc-at' === $sourceOAuthAccount->storedAccessToken()
                && 'old-enc-rt' === $sourceOAuthAccount->storedRefreshToken()
            ))
        ;

        $result = $this->createResolver()->resolveForAuthorization('auth-1');

        self::assertSame('new-at', $result['access_token']);
        self::assertTrue($result['refreshed']);
    }

    #[Test]
    public function resolveRefreshesInstanceBackedTokenThroughProviderInstance(): void
    {
        $providerAuthorization = $this->createAuthorization(
            id: 'auth-1',
            sourceOAuthAccountId: 'soa-1',
            provider: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            providerInstanceId: 'instance-1',
        );
        $sourceOAuthAccount = $this->createAccount(
            provider: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );
        $sourceOAuthAccount->assignProviderInstance('instance-1', 'https://gitlab.company.test');

        $sourceProviderInstanceRepository = $this->createMock(SourceProviderInstanceRepositoryInterface::class);
        $sourceProviderInstanceRepository->expects(self::once())
            ->method('findActiveByIdInOrganization')
            ->with('instance-1', 'org-1')
            ->willReturn(SourceProviderInstance::create(
                organizationId: 'org-1',
                providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                label: 'Company GitLab',
                baseUrl: 'https://gitlab.company.test',
                clientId: 'client-id',
                clientSecret: 'encrypted-client-secret',
                authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
            ))
        ;

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('isEncrypted')
            ->willReturnMap([
                ['enc_at', true],
                ['enc_rt', true],
            ])
        ;
        $this->secretEncryptor->expects(self::exactly(3))
            ->method('decrypt')
            ->willReturnMap([
                ['enc_at', 'old-at'],
                ['enc_rt', 'old-rt'],
                ['encrypted-client-secret', 'client-secret'],
            ])
        ;
        $this->oauthProvider->expects(self::never())->method('refreshAccessToken');
        $this->oauthProvider->expects(self::once())
            ->method('refreshAccessTokenForProviderInstance')
            ->with(
                SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                self::callback(static fn (array $config): bool => 'https://gitlab.company.test' === $config['base_url']
                    && 'client-id' === $config['client_id']
                    && 'client-secret' === $config['client_secret']
                    && 'https://packio.test/auth/oauth/gitlab_self_hosted/source-callback' === $config['redirect_url']),
                'old-rt',
            )
            ->willReturn(new OAuthTokenData('new-at', 'new-rt', 7200))
        ;
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['new-at', 'new-enc-at'],
                ['new-rt', 'new-enc-rt'],
            ])
        ;
        $this->oauthAccountRepo->expects(self::once())->method('save');

        $sourceOAuthTokenResolver = new SourceOAuthTokenResolver(
            $this->oauthAccountRepo,
            $this->authRepo,
            $this->sourceRepo,
            $this->sourceOAuthAccountSecrets,
            $this->oauthProvider,
            $sourceProviderInstanceRepository,
            new SourceProviderInstanceOAuthConfig(
                new SourceProviderInstanceSecrets($this->secretEncryptor),
                new BrandingConfig([
                    'app_url' => 'https://packio.test',
                ]),
            ),
        );

        $result = $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');

        self::assertSame('new-at', $result['access_token']);
        self::assertTrue($result['refreshed']);
    }

    #[Test]
    public function resolveFailsCodebergRefreshWhenResponseOmitsRotatedRefreshToken(): void
    {
        $providerAuthorization = $this->createAuthorization(
            id: 'auth-1',
            sourceOAuthAccountId: 'soa-1',
            provider: 'codeberg',
        );
        $sourceOAuthAccount = $this->createAccount(
            provider: 'codeberg',
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('isEncrypted')
            ->willReturnMap([
                ['enc_at', true],
                ['enc_rt', true],
            ])
        ;
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['enc_at', 'old-at'],
                ['enc_rt', 'old-rt'],
            ])
        ;
        $this->oauthProvider->expects(self::once())
            ->method('refreshAccessToken')
            ->with('codeberg', 'old-rt')
            ->willReturn(new OAuthTokenData('new-at', null, 7200))
        ;
        $this->secretEncryptor->expects(self::never())->method('encrypt');
        $this->oauthAccountRepo->expects(self::never())->method('save');

        $this->expectException(SourceTokenRefreshFailedException::class);
        $this->expectExceptionMessage('Codeberg token refresh response did not include a new refresh token.');

        $this->createResolver()->resolveForAuthorization('auth-1');
    }

    #[Test]
    public function resolveUsesLockedFreshTokenWhenAnotherWorkerAlreadyRefreshedIt(): void
    {
        $sourceOAuthAccount = $this->createAccount(
            provider: 'codeberg',
            accessToken: 'enc_stale_at',
            refreshToken: 'enc_stale_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );
        $freshAccount = $this->createAccount(
            provider: 'codeberg',
            accessToken: 'enc_fresh_at',
            refreshToken: 'enc_fresh_rt',
            tokenExpiresAt: Chronos::parse('+2 hours'),
        );
        $freshAccount->setIdentifier($sourceOAuthAccount->getIdentifier());

        $providerAuthorization = $this->createAuthorization(
            id: 'auth-1',
            sourceOAuthAccountId: $sourceOAuthAccount->getIdentifier()->toString(),
            provider: 'codeberg',
        );

        /** @var MockObject&SourceOAuthAccountLockingRepositoryInterface&SourceOAuthAccountRepositoryInterface $mockObject */
        $mockObject = $this->createMockForIntersectionOfInterfaces([
            SourceOAuthAccountRepositoryInterface::class,
            SourceOAuthAccountLockingRepositoryInterface::class,
        ]);
        $mockObject->expects(self::once())
            ->method('findById')
            ->with($sourceOAuthAccount->getIdentifier()->toString())
            ->willReturn($sourceOAuthAccount)
        ;
        $mockObject->expects(self::once())
            ->method('withLockedById')
            ->with(
                $sourceOAuthAccount->getIdentifier()->toString(),
                self::isCallable(),
            )
            ->willReturnCallback(static fn (string $id, callable $callback): array => $callback($freshAccount))
        ;

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('isEncrypted')
            ->willReturnMap([
                ['enc_stale_at', true],
                ['enc_fresh_at', true],
            ])
        ;
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['enc_stale_at', 'stale-at'],
                ['enc_fresh_at', 'fresh-at'],
            ])
        ;
        $this->oauthProvider->expects(self::never())->method('refreshAccessToken');
        $mockObject->expects(self::never())->method('save');

        $sourceOAuthTokenResolver = new SourceOAuthTokenResolver(
            $mockObject,
            $this->authRepo,
            $this->sourceRepo,
            $this->sourceOAuthAccountSecrets,
            $this->oauthProvider,
            $this->createStub(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceProviderInstanceOAuthConfig(),
        );

        $result = $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');

        self::assertSame('fresh-at', $result['access_token']);
        self::assertFalse($result['refreshed']);
        self::assertSame($sourceOAuthAccount->getIdentifier()->toString(), $result['oauth_account_id']);
    }

    #[Test]
    public function resolveRecoversCodebergAlreadyUsedRefreshTokenWhenNewerTokenWasStored(): void
    {
        $sourceOAuthAccount = $this->createAccount(
            provider: 'codeberg',
            accessToken: 'enc_stale_at',
            refreshToken: 'enc_stale_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );
        $freshAccount = $this->createAccount(
            provider: 'codeberg',
            accessToken: 'enc_fresh_at',
            refreshToken: 'enc_fresh_rt',
            tokenExpiresAt: Chronos::parse('+2 hours'),
        );
        $freshAccount->setIdentifier($sourceOAuthAccount->getIdentifier());

        $providerAuthorization = $this->createAuthorization(
            id: 'auth-1',
            sourceOAuthAccountId: $sourceOAuthAccount->getIdentifier()->toString(),
            provider: 'codeberg',
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::exactly(2))
            ->method('findById')
            ->with($sourceOAuthAccount->getIdentifier()->toString())
            ->willReturnOnConsecutiveCalls($sourceOAuthAccount, $freshAccount)
        ;
        $this->secretEncryptor->expects(self::exactly(4))
            ->method('isEncrypted')
            ->willReturnMap([
                ['enc_stale_at', true],
                ['enc_stale_rt', true],
                ['enc_fresh_rt', true],
                ['enc_fresh_at', true],
            ])
        ;
        $this->secretEncryptor->expects(self::exactly(4))
            ->method('decrypt')
            ->willReturnMap([
                ['enc_stale_at', 'stale-at'],
                ['enc_stale_rt', 'stale-rt'],
                ['enc_fresh_rt', 'fresh-rt'],
                ['enc_fresh_at', 'fresh-at'],
            ])
        ;
        $this->oauthProvider->expects(self::once())
            ->method('refreshAccessToken')
            ->with('codeberg', 'stale-rt')
            ->willThrowException(new RuntimeException('Codeberg OAuth error (unauthorized_client): token was already used'))
        ;
        $this->oauthAccountRepo->expects(self::never())->method('save');

        $result = $this->createResolver()->resolveForAuthorization('auth-1');

        self::assertSame('fresh-at', $result['access_token']);
        self::assertFalse($result['refreshed']);
        self::assertSame($sourceOAuthAccount->getIdentifier()->toString(), $result['oauth_account_id']);
    }

    // ── Refresh fails ───────────────────────────────────────────────────────

    #[Test]
    public function resolveThrowsWhenRefreshFails(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        $sourceOAuthAccount    = $this->createAccount(
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('isEncrypted')
            ->willReturn(true)
        ;

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['enc_at', 'old-at'],
                ['enc_rt', 'old-rt'],
            ])
        ;

        $this->oauthProvider->expects(self::once())
            ->method('refreshAccessToken')
            ->with('github', 'old-rt')
            ->willThrowException(new RuntimeException('Upstream error'))
        ;

        $sourceOAuthTokenResolver = $this->createResolver();

        $this->expectException(SourceTokenRefreshFailedException::class);
        $this->expectExceptionMessage('Token refresh failed');

        $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');
    }

    #[Test]
    #[DataProvider('refreshFailures')]
    public function resolveFlagsReauthorizationOnlyForProviderRejection(
        RuntimeException $runtimeException,
        bool $requiresReauthorization
    ): void {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        $sourceOAuthAccount    = $this->createAccount(
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );

        $this->authRepo->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->method('findById')->willReturn($sourceOAuthAccount);
        $this->secretEncryptor->method('isEncrypted')->willReturn(true);
        $this->secretEncryptor->method('decrypt')->willReturnMap([
            ['enc_at', 'old-at'],
            ['enc_rt', 'old-rt'],
        ]);
        $this->oauthProvider->expects(self::once())->method('refreshAccessToken')->willThrowException($runtimeException);

        try {
            $this->createResolver()->resolveForAuthorization('auth-1');
            self::fail('Expected SourceTokenRefreshFailedException');
        } catch (SourceTokenRefreshFailedException $sourceTokenRefreshFailedException) {
            self::assertSame($requiresReauthorization, $sourceTokenRefreshFailedException->requiresReauthorization);
        }
    }

    /** @return array<string, array{RuntimeException, bool}> */
    public static function refreshFailures(): array
    {
        return [
            'provider rejection' => [new ProviderRefreshRejectedException('invalid_grant'), true],
            'transient failure'  => [new RuntimeException('Failed to refresh token: invalid response'), false],
        ];
    }

    #[Test]
    public function codebergAlreadyUsedTokenWithoutNewerStoredTokenRequiresReauthorization(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1', provider: 'codeberg');
        $sourceOAuthAccount    = $this->createAccount(
            provider: 'codeberg',
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );

        $this->authRepo->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->method('findById')->willReturn($sourceOAuthAccount);
        $this->secretEncryptor->method('isEncrypted')->willReturn(true);
        $this->secretEncryptor->method('decrypt')->willReturnMap([
            ['enc_at', 'old-at'],
            ['enc_rt', 'old-rt'],
        ]);
        $this->oauthProvider->expects(self::once())
            ->method('refreshAccessToken')
            ->willThrowException(new ProviderRefreshRejectedException('Codeberg OAuth error (unauthorized_client): token was already used'))
        ;

        try {
            $this->createResolver()->resolveForAuthorization('auth-1');
            self::fail('Expected SourceTokenRefreshFailedException');
        } catch (SourceTokenRefreshFailedException $sourceTokenRefreshFailedException) {
            self::assertTrue($sourceTokenRefreshFailedException->requiresReauthorization);
        }
    }

    // ── resolveForSource ────────────────────────────────────────────────────

    #[Test]
    public function resolveForSourceThrowsWhenSourceNotFound(): void
    {
        $this->sourceRepo->expects(self::once())
            ->method('findById')
            ->with('src-1')
            ->willReturn(null)
        ;

        $sourceOAuthTokenResolver = $this->createResolver();

        $this->expectException(SourceTokenMissingException::class);

        $sourceOAuthTokenResolver->resolveForSource('src-1');
    }

    // ── Token about to expire (buffer) triggers refresh ─────────────────────

    #[Test]
    public function resolveRefreshesWhenTokenExpiresWithinBuffer(): void
    {
        $providerAuthorization = $this->createAuthorization(id: 'auth-1', sourceOAuthAccountId: 'soa-1');
        // Token expires in 30 seconds — within the 60-second buffer
        $sourceOAuthAccount = $this->createAccount(
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('+30 seconds'),
        );

        $this->authRepo->expects(self::once())->method('findById')->willReturn($providerAuthorization);
        $this->oauthAccountRepo->expects(self::once())->method('findById')->willReturn($sourceOAuthAccount);

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('isEncrypted')
            ->willReturn(true)
        ;

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['enc_at', 'old-at'],
                ['enc_rt', 'old-rt'],
            ])
        ;

        // Should trigger refresh because 30s < 60s buffer
        $this->oauthProvider->expects(self::once())
            ->method('refreshAccessToken')
            ->with('github', 'old-rt')
            ->willReturn(new OAuthTokenData('new-at', 'new-rt', 7200))
        ;

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['new-at', 'new-enc-at'],
                ['new-rt', 'new-enc-rt'],
            ])
        ;

        $this->oauthAccountRepo->expects(self::once())->method('save');

        $sourceOAuthTokenResolver = $this->createResolver();

        $result = $sourceOAuthTokenResolver->resolveForAuthorization('auth-1');

        self::assertSame('new-at', $result['access_token']);
        self::assertTrue($result['refreshed']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function createResolver(): SourceOAuthTokenResolver
    {
        return new SourceOAuthTokenResolver(
            $this->oauthAccountRepo,
            $this->authRepo,
            $this->sourceRepo,
            $this->sourceOAuthAccountSecrets,
            $this->oauthProvider,
            $this->createStub(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceProviderInstanceOAuthConfig(),
        );
    }

    /**
     * @param list<string> $scopes
     */
    private function createAccount(
        string $userId = 'user-1',
        string $provider = 'github',
        string $providerUserId = '123',
        string $accessToken = 'enc_at',
        ?string $refreshToken = null,
        ?Chronos $tokenExpiresAt = null,
        // list<string>
        $scopes = ['repo'],
    ): SourceOAuthAccount {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: $userId,
            provider: $provider,
            providerUserId: $providerUserId,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            tokenExpiresAt: $tokenExpiresAt,
            scopes: $scopes,
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid4());

        return $sourceOAuthAccount;
    }

    private function createAuthorization(
        string $id,
        ?string $sourceOAuthAccountId,
        string $provider = 'github',
        string $organizationId = 'org-1',
        ?string $providerInstanceId = null,
    ): ProviderAuthorization {
        $auth = $this->createMock(ProviderAuthorization::class);
        $auth->method('getIdentifier')->willReturn(Uuid::fromString('00000000-0000-0000-0000-000000000001'));
        $auth->method('sourceOAuthAccountId')->willReturn($sourceOAuthAccountId);
        $auth->method('provider')->willReturn($provider);
        $auth->method('organizationId')->willReturn($organizationId);
        $auth->method('providerInstanceId')->willReturn($providerInstanceId);

        return $auth;
    }
}
