<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Cake\Chronos\Chronos;
use Common\Branding\BrandingConfig;
use Common\Security\SecretEncryptorInterface;
use Packio\Source\Application\Dto\OAuthTokenData;
use Packio\Source\Application\Dto\OAuthUserData;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Security\SourceProviderInstanceSecrets;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceProviderInstanceOAuthConfig;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SourceOAuthService::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceOAuthServiceTest extends TestCase
{
    use InertSourceCollaborators;

    /** @var (MockObject&OAuthProviderPort) */
    private MockObject $oauthProvider;

    /** @var (MockObject&SourceOAuthAccountRepositoryInterface) */
    private MockObject $sourceOAuthRepo;

    private SourceOAuthAccountSecrets $sourceOAuthAccountSecrets;

    /** @var (MockObject&OrganizationQueryPort) */
    private MockObject $orgQuery;

    /** @var (MockObject&SecretEncryptorInterface) */
    private MockObject $secretEncryptor;

    protected function setUp(): void
    {
        $this->secretEncryptor           = $this->createMock(SecretEncryptorInterface::class);
        $this->oauthProvider             = $this->createMock(OAuthProviderPort::class);
        $this->sourceOAuthRepo           = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
        $this->sourceOAuthAccountSecrets = new SourceOAuthAccountSecrets($this->secretEncryptor);
        $this->orgQuery                  = $this->createMock(OrganizationQueryPort::class);
    }

    // ── resolveOrganizationId ───────────────────────────────────────────────

    #[Test]
    public function resolveOrganizationIdReturnsEmptyForEmptySlug(): void
    {
        $sourceOAuthService = $this->createService();

        $this->oauthProvider->expects(self::once())
            ->method('exchangeCodeForToken')
            ->willReturn(new OAuthTokenData('access', 'refresh', 3600))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('fetchUser')
            ->willReturn(new OAuthUserData('123', 'email@example.com', 'user', 'Name'))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('sourceScopes')
            ->willReturn(['read:user'])
        ;

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnOnConsecutiveCalls('enc_access', 'enc_refresh')
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findByCredentialIdentity')
            ->with('user-1', 'github', '123', null)
            ->willReturn(null)
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('saveOrUpdateByCredentialKey')
            ->with(
                self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => null !== $sourceOAuthAccount->credentialKey()),
                self::anything(),
            )
            ->willReturnCallback(static fn (SourceOAuthAccount $sourceOAuthAccount): SourceOAuthAccount => $sourceOAuthAccount)
        ;

        $this->orgQuery->expects(self::never())
            ->method('resolveOrganizationId')
        ;

        $sourceOAuthService->handleSourceCallback('github', 'code', 'user-1', '');
    }

    #[Test]
    public function resolveOrganizationIdResolvesSlugToUuid(): void
    {
        $sourceOAuthService = $this->createService();

        $this->orgQuery->expects(self::once())
            ->method('resolveOrganizationId')
            ->with('my-org')
            ->willReturn('org-uuid-123')
        ;

        $this->oauthProvider->expects(self::once())
            ->method('exchangeCodeForToken')
            ->willReturn(new OAuthTokenData('access', 'refresh', 3600))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('fetchUser')
            ->willReturn(new OAuthUserData('123', 'email@example.com', 'user', 'Name'))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('sourceScopes')
            ->willReturn(['read:user'])
        ;

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnOnConsecutiveCalls('enc_access', 'enc_refresh')
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findByCredentialIdentity')
            ->with('user-1', 'github', '123', null)
            ->willReturn(null)
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('saveOrUpdateByCredentialKey')
            ->with(
                self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => null !== $sourceOAuthAccount->credentialKey()),
                self::anything(),
            )
            ->willReturnCallback(static fn (SourceOAuthAccount $sourceOAuthAccount): SourceOAuthAccount => $sourceOAuthAccount)
        ;

        $sourceOAuthService->handleSourceCallback('github', 'code', 'user-1', 'my-org');
    }

    #[Test]
    public function resolveOrganizationIdThrowsWhenSlugCannotBeResolved(): void
    {
        $sourceOAuthService = $this->createService();

        $this->orgQuery->expects(self::once())
            ->method('resolveOrganizationId')
            ->with('unknown-org')
            ->willReturn(null)
        ;

        $this->oauthProvider->expects(self::once())
            ->method('exchangeCodeForToken')
            ->willReturn(new OAuthTokenData('access', 'refresh', 3600))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('fetchUser')
            ->willReturn(new OAuthUserData('123', 'email@example.com', 'user', 'Name'))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('sourceScopes')
            ->willReturn(['read:user'])
        ;

        $this->secretEncryptor->expects(self::never())->method('encrypt');
        $this->sourceOAuthRepo->expects(self::never())->method('findByCredentialIdentity');
        $this->sourceOAuthRepo->expects(self::never())->method('save');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Organization not found for source OAuth connection.');
        $sourceOAuthService->handleSourceCallback('github', 'code', 'user-1', 'unknown-org');
    }

    // ── handleSourceCallback ────────────────────────────────────────────────

    #[Test]
    public function handleSourceCallbackCreatesNewAccount(): void
    {
        $sourceOAuthService = $this->createService();

        $this->orgQuery->expects(self::once())
            ->method('resolveOrganizationId')
            ->with('my-org')
            ->willReturn('org-uuid')
        ;

        $this->oauthProvider->expects(self::once())
            ->method('exchangeCodeForToken')
            ->with('github', 'code123')
            ->willReturn(new OAuthTokenData('at', 'rt', 7200))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('fetchUser')
            ->with('github', 'at')
            ->willReturn(new OAuthUserData('prov-123', 'dev@null.com', 'ghuser', 'GH User', 'https://avatar.url'))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('sourceScopes')
            ->with('github')
            ->willReturn(['repo', 'read:user'])
        ;

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnOnConsecutiveCalls('enc_at', 'enc_rt')
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findByCredentialIdentity')
            ->with('user-1', 'github', 'prov-123', null)
            ->willReturn(null)
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('saveOrUpdateByCredentialKey')
            ->with(self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => 'user-1' === $sourceOAuthAccount->userId()
                && 'github' === $sourceOAuthAccount->provider()
                && 'prov-123' === $sourceOAuthAccount->providerUserId()
                && 'dev@null.com' === $sourceOAuthAccount->providerEmail()
                && 'ghuser' === $sourceOAuthAccount->providerUsername()
                && ['repo', 'read:user'] === $sourceOAuthAccount->scopes()
                && $sourceOAuthAccount->isActive()
                && null !== $sourceOAuthAccount->credentialKey()), self::anything())
            ->willReturnCallback(static fn (SourceOAuthAccount $sourceOAuthAccount): SourceOAuthAccount => $sourceOAuthAccount)
        ;

        $result = $sourceOAuthService->handleSourceCallback('github', 'code123', 'user-1', 'my-org');

        self::assertSame('github', $result->provider());
    }

    #[Test]
    public function handleSourceCallbackUpdatesExistingAccount(): void
    {
        $sourceOAuthService = $this->createService();

        $this->orgQuery->expects(self::once())
            ->method('resolveOrganizationId')
            ->with('my-org')
            ->willReturn('org-uuid')
        ;

        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'old-prov-id',
            providerEmail: 'old@null.com',
            providerUsername: 'olduser',
            accessToken: 'old_enc_at',
            refreshToken: 'old_enc_rt',
            tokenExpiresAt: Chronos::parse('+1 hour'),
            scopes: ['read:user'],
        );
        $sourceOAuthAccount->markAsInactive();

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findByCredentialIdentity')
            ->with('user-1', 'github', 'new-prov-id', null)
            ->willReturn($sourceOAuthAccount)
        ;

        $this->oauthProvider->expects(self::once())
            ->method('exchangeCodeForToken')
            ->willReturn(new OAuthTokenData('new_at', 'new_rt', 3600))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('fetchUser')
            ->willReturn(new OAuthUserData('new-prov-id', 'new@null.com', 'newuser', 'New User'))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('sourceScopes')
            ->willReturn(['repo'])
        ;

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnOnConsecutiveCalls('new_enc_at', 'new_enc_rt')
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('saveOrUpdateByCredentialKey')
            ->with(self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => 'new-prov-id' === $sourceOAuthAccount->providerUserId()
                && 'new@null.com' === $sourceOAuthAccount->providerEmail()
                && 'newuser' === $sourceOAuthAccount->providerUsername()
                && ['repo'] === $sourceOAuthAccount->scopes()
                && $sourceOAuthAccount->isActive()
                && null !== $sourceOAuthAccount->credentialKey()), self::anything())
            ->willReturnCallback(static fn (SourceOAuthAccount $sourceOAuthAccount): SourceOAuthAccount => $sourceOAuthAccount)
        ;

        $result = $sourceOAuthService->handleSourceCallback('github', 'code123', 'user-1', 'my-org');

        self::assertSame($sourceOAuthAccount, $result);
    }

    #[Test]
    public function handleSourceCallbackReusesCanonicalAccountWhenProviderWasAuthorizedElsewhere(): void
    {
        $sourceOAuthService = $this->createService();
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            providerEmail: 'old@null.com',
            providerUsername: 'olduser',
            accessToken: 'old_enc_at',
            refreshToken: 'old_enc_rt',
            tokenExpiresAt: Chronos::parse('+1 hour'),
            scopes: ['read:user'],
        );

        $this->orgQuery->expects(self::once())
            ->method('resolveOrganizationId')
            ->with('second-org')
            ->willReturn('org-2')
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findByCredentialIdentity')
            ->with('user-1', 'github', 'provider-user-1', null)
            ->willReturn($sourceOAuthAccount)
        ;

        $this->oauthProvider->expects(self::once())
            ->method('exchangeCodeForToken')
            ->willReturn(new OAuthTokenData('at-org-2', 'rt-org-2', 3600))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('fetchUser')
            ->willReturn(new OAuthUserData('provider-user-1', 'dev@null.com', 'ghuser', 'GH User'))
        ;

        $this->oauthProvider->expects(self::once())
            ->method('sourceScopes')
            ->willReturn(['repo'])
        ;

        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnOnConsecutiveCalls('enc_at_org_2', 'enc_rt_org_2')
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('saveOrUpdateByCredentialKey')
            ->with(self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => 'user-1' === $sourceOAuthAccount->userId()
                && 'github' === $sourceOAuthAccount->provider()
                && 'enc_at_org_2' === $sourceOAuthAccount->storedAccessToken()
                && null !== $sourceOAuthAccount->credentialKey()), self::anything())
            ->willReturnCallback(static fn (SourceOAuthAccount $sourceOAuthAccount): SourceOAuthAccount => $sourceOAuthAccount)
        ;

        $result = $sourceOAuthService->handleSourceCallback('github', 'code123', 'user-1', 'second-org');

        self::assertSame($sourceOAuthAccount, $result);
    }

    // ── verifyAndInvalidateSourceOAuth ──────────────────────────────────────

    #[Test]
    public function verifyMarksAccountAsInactiveWhenDecryptionFails(): void
    {
        $sourceOAuthService = $this->createService();

        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: '123',
            accessToken: 'encrypted_at',
            scopes: ['read:user'],
        );

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;

        // decryptAccessToken returns null when isEncrypted returns false
        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('encrypted_at')
            ->willReturn(false)
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => ! $sourceOAuthAccount->isActive()))
        ;

        $result = $sourceOAuthService->verifyAndInvalidateSourceOAuth('soa-1');

        self::assertFalse($result['valid']);
        self::assertTrue($result['invalidated']);
        $error = $result['error'] ?? null;
        self::assertNotNull($error);
        self::assertStringContainsString('missing or unreadable', $error);
    }

    #[Test]
    public function verifyMarksAccountAsInactiveWhenTokenInvalid(): void
    {
        $sourceOAuthService = $this->createService();

        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: '123',
            accessToken: 'encrypted_at',
            scopes: ['read:user'],
        );

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;

        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('encrypted_at')
            ->willReturn(true)
        ;

        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('encrypted_at')
            ->willReturn('plaintext_token')
        ;

        $this->oauthProvider->expects(self::once())
            ->method('verifyToken')
            ->with('github', 'plaintext_token')
            ->willReturn(false)
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => ! $sourceOAuthAccount->isActive()))
        ;

        $result = $sourceOAuthService->verifyAndInvalidateSourceOAuth('soa-1');

        self::assertFalse($result['valid']);
        self::assertTrue($result['invalidated']);
        $error = $result['error'] ?? null;
        self::assertNotNull($error);
        self::assertStringContainsString('invalid or expired', $error);
    }

    #[Test]
    public function verifyReturnsValidWhenTokenIsGood(): void
    {
        $sourceOAuthService = $this->createService();

        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: '123',
            accessToken: 'encrypted_at',
            scopes: ['read:user'],
        );

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;

        $this->secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('encrypted_at')
            ->willReturn(true)
        ;

        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('encrypted_at')
            ->willReturn('plaintext_token')
        ;

        $this->oauthProvider->expects(self::once())
            ->method('verifyToken')
            ->with('github', 'plaintext_token')
            ->willReturn(true)
        ;

        $this->sourceOAuthRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => $sourceOAuthAccount->isActive()))
        ;

        $result = $sourceOAuthService->verifyAndInvalidateSourceOAuth('soa-1');

        self::assertTrue($result['valid']);
        self::assertFalse($result['invalidated']);
    }

    #[Test]
    public function verifyReturnsInvalidForInactiveAccount(): void
    {
        $sourceOAuthService = $this->createService();

        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: '123',
            accessToken: 'encrypted_at',
            scopes: ['read:user'],
        );
        $sourceOAuthAccount->markAsInactive();

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;

        $this->secretEncryptor->expects(self::never())->method('isEncrypted');
        $this->secretEncryptor->expects(self::never())->method('decrypt');
        $this->oauthProvider->expects(self::never())->method('verifyToken');
        $this->sourceOAuthRepo->expects(self::never())->method('save');

        $result = $sourceOAuthService->verifyAndInvalidateSourceOAuth('soa-1');

        self::assertFalse($result['valid']);
        self::assertFalse($result['invalidated']);
        $error = $result['error'] ?? null;
        self::assertNotNull($error);
        self::assertStringContainsString('inactive', $error);
    }

    #[Test]
    public function verifyReturnsInvalidForMissingAccount(): void
    {
        $sourceOAuthService = $this->createService();

        $this->sourceOAuthRepo->expects(self::once())
            ->method('findById')
            ->with('nonexistent')
            ->willReturn(null)
        ;

        $result = $sourceOAuthService->verifyAndInvalidateSourceOAuth('nonexistent');

        self::assertFalse($result['valid']);
        self::assertFalse($result['invalidated']);
        $error = $result['error'] ?? null;
        self::assertNotNull($error);
        self::assertStringContainsString('not found', $error);
    }

    #[Test]
    public function handleSourceProviderInstanceCallbackStoresInstanceContext(): void
    {
        $sourceProviderInstanceRepository = $this->createMock(SourceProviderInstanceRepositoryInterface::class);
        $sourceProviderInstance           = SourceProviderInstance::create(
            organizationId: 'org-uuid',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: 'encrypted-client-secret',
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        );

        $this->orgQuery->expects(self::once())
            ->method('resolveOrganizationId')
            ->with('my-org')
            ->willReturn('org-uuid')
        ;
        $sourceProviderInstanceRepository->expects(self::once())
            ->method('findActiveByIdInOrganization')
            ->with('instance-1', 'org-uuid')
            ->willReturn($sourceProviderInstance)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('encrypted-client-secret')
            ->willReturn('client-secret')
        ;
        $this->oauthProvider->expects(self::once())
            ->method('exchangeCodeForTokenForProviderInstance')
            ->with(
                SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                self::callback(static fn (array $config): bool => 'https://gitlab.company.test' === $config['base_url']
                    && 'client-id' === $config['client_id']
                    && 'client-secret' === $config['client_secret']
                    && 'https://packio.test/auth/oauth/gitlab_self_hosted/source-callback' === $config['redirect_url']),
                'code-1',
                null,
            )
            ->willReturn(new OAuthTokenData('access-token', 'refresh-token', 3600))
        ;
        $this->oauthProvider->expects(self::once())
            ->method('fetchUserForProviderInstance')
            ->willReturn(new OAuthUserData('42', 'gitlab@example.com', 'gitlab-user', 'GitLab User'))
        ;
        $this->secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['access-token', 'encrypted-access-token'],
                ['refresh-token', 'encrypted-refresh-token'],
            ])
        ;
        $this->sourceOAuthRepo->expects(self::once())
            ->method('findByCredentialIdentity')
            ->with('user-1', SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED, '42', 'instance-1')
            ->willReturn(null)
        ;
        $this->sourceOAuthRepo->expects(self::once())
            ->method('saveOrUpdateByCredentialKey')
            ->with(self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => 'instance-1' === $sourceOAuthAccount->providerInstanceId()
                && 'https://gitlab.company.test' === $sourceOAuthAccount->providerBaseUrl()
                && '42' === $sourceOAuthAccount->providerUserId()
                && SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED === $sourceOAuthAccount->provider()
                && null !== $sourceOAuthAccount->credentialKey()), self::anything(), 'org-uuid')
            ->willReturnCallback(static fn (SourceOAuthAccount $sourceOAuthAccount): SourceOAuthAccount => $sourceOAuthAccount)
        ;

        $sourceOAuthService = new SourceOAuthService(
            $this->oauthProvider,
            $this->sourceOAuthRepo,
            $this->sourceOAuthAccountSecrets,
            $this->orgQuery,
            $sourceProviderInstanceRepository,
            new SourceProviderInstanceOAuthConfig(
                new SourceProviderInstanceSecrets($this->secretEncryptor),
                new BrandingConfig([
                    'app_url' => 'https://packio.test',
                ]),
            ),
        );

        $account = $sourceOAuthService->handleSourceProviderInstanceCallback(
            SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            'code-1',
            'user-1',
            'my-org',
            'instance-1',
        );

        self::assertSame('instance-1', $account->providerInstanceId());
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function createService(): SourceOAuthService
    {
        return new SourceOAuthService(
            $this->oauthProvider,
            $this->sourceOAuthRepo,
            $this->sourceOAuthAccountSecrets,
            $this->orgQuery,
            $this->createStub(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceProviderInstanceOAuthConfig(),
        );
    }
}
