<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Query;

use Cake\Chronos\Chronos;
use Common\Security\SecretEncryptorInterface;
use Packio\Source\Api\Exception\ProviderCallException;
use Packio\Source\Api\Exception\ProviderFailure;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Query\SourceQueryService;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Service\SecretResolverInterface;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceOAuthTokenResolver;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

#[CoversClass(SourceQueryService::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceQueryServiceTest extends TestCase
{
    use InertSourceCollaborators;

    #[Test]
    public function providerResourcesRequiresOrganization(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Organization is required to query provider resources.');

        $this->service()->providerResources('github', 'auth-1', '');
    }

    #[Test]
    public function scopedLookupUsesOrganizationInRepositoryQuery(): void
    {
        $repository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');
        $repository->expects(self::once())->method('findByIdInOrganization')->with('auth-1', 'org-1')->willReturn(null);
        self::assertNull($this->service($repository)->findProviderAuthorizationInOrganization('auth-1', 'org-1'));
    }

    #[Test]
    public function providerResourcesReportsATransientRefreshFailureAsUnavailableWithoutInvalidating(): void
    {
        $authorization = $this->createMock(ProviderAuthorization::class);
        $authorization->method('getIdentifier')->willReturn(Uuid::fromString('00000000-0000-0000-0000-000000000001'));
        $authorization->method('sourceOAuthAccountId')->willReturn('soa-1');
        $authorization->method('provider')->willReturn('github');
        $authorization->method('organizationId')->willReturn('org-1');
        $authorization->expects(self::never())->method('updateStatus');

        $repository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $repository->method('findByIdInOrganization')->willReturn($authorization);
        $repository->expects(self::never())->method('save');

        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: '123',
            accessToken: 'enc_at',
            refreshToken: 'enc_rt',
            tokenExpiresAt: Chronos::parse('-1 hour'),
            scopes: ['repo'],
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid4());
        $accounts = $this->createStub(SourceOAuthAccountRepositoryInterface::class);
        $accounts->method('findById')->willReturn($sourceOAuthAccount);

        $secretEncryptor = $this->createStub(SecretEncryptorInterface::class);
        $secretEncryptor->method('isEncrypted')->willReturn(true);
        $secretEncryptor->method('decrypt')->willReturnMap([['enc_at', 'old-at'], ['enc_rt', 'old-rt']]);

        $oAuthProviderPort = $this->createStub(OAuthProviderPort::class);
        $oAuthProviderPort->method('refreshAccessToken')->willThrowException(new RuntimeException('Failed to refresh token: invalid response'));

        try {
            $this->service($repository, $accounts, $secretEncryptor, $oAuthProviderPort)->providerResources('github', 'auth-1', 'org-1');
            self::fail('Expected ProviderCallException');
        } catch (ProviderCallException $providerCallException) {
            self::assertSame(ProviderFailure::Unavailable, $providerCallException->failure);
        }
    }

    private function service(
        ?ProviderAuthorizationRepositoryInterface $repository = null,
        ?SourceOAuthAccountRepositoryInterface $accounts = null,
        ?SecretEncryptorInterface $encryptor = null,
        ?OAuthProviderPort $oAuthProvider = null,
    ): SourceQueryService {
        $secretEncryptor                 = $encryptor ?? $this->createMock(SecretEncryptorInterface::class);
        $sourceRepository                = $this->createMock(SourceRepositoryInterface::class);
        $providerAuthorizationRepository = $repository ?? $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $sourceOAuthAccountRepository    = $accounts ?? $this->createMock(SourceOAuthAccountRepositoryInterface::class);
        $sourceOAuthAccountSecrets       = new SourceOAuthAccountSecrets($secretEncryptor);
        $oAuthProviderPort               = $oAuthProvider ?? $this->createMock(OAuthProviderPort::class);
        $sourceOAuthService              = new SourceOAuthService(
            $oAuthProviderPort,
            $sourceOAuthAccountRepository,
            $sourceOAuthAccountSecrets,
            $this->createMock(OrganizationQueryPort::class),
            $this->createStub(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceProviderInstanceOAuthConfig(),
        );

        return new SourceQueryService(
            $sourceRepository,
            $providerAuthorizationRepository,
            $this->createMock(SourceProviderRegistryInterface::class),
            new ProviderAuthorizationSecrets($secretEncryptor),
            $this->createMock(SecretResolverInterface::class),
            new SourceAuthorizationInvalidationService(
                $sourceRepository,
                $providerAuthorizationRepository,
                $sourceOAuthService,
            ),
            new SourceOAuthTokenResolver(
                $sourceOAuthAccountRepository,
                $providerAuthorizationRepository,
                $sourceRepository,
                $sourceOAuthAccountSecrets,
                $oAuthProviderPort,
                $this->createStub(SourceProviderInstanceRepositoryInterface::class),
                $this->inertSourceProviderInstanceOAuthConfig(),
            ),
        );
    }
}
