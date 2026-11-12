<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Common\Security\SecretEncryptorInterface;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceOAuthService;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceAuthorizationInvalidationService::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceAuthorizationInvalidationServiceTest extends TestCase
{
    use InertSourceCollaborators;

    private MockObject&SourceRepositoryInterface $sourceRepository;
    private MockObject&ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository;
    private MockObject&SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository;

    protected function setUp(): void
    {
        $this->sourceRepository                = $this->createMock(SourceRepositoryInterface::class);
        $this->providerAuthorizationRepository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $this->sourceOAuthAccountRepository    = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
    }

    #[Test]
    public function markInvalidSetsAuthorizationAndLinkedOAuthAccountInactive(): void
    {
        $providerAuthorization = ProviderAuthorization::authorizeFromOAuth(
            organizationId: 'org-1',
            provider: 'github',
            sourceOAuthAccountId: 'soa-1',
            authStrategy: 'github_token',
            configuration: [
                'token' => 'enc_token',
            ],
        );
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            accessToken: 'enc_token',
        );

        $this->providerAuthorizationRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ProviderAuthorization $providerAuthorization): bool => 'inactive' === $providerAuthorization->status()))
            ->willReturnCallback(static fn (ProviderAuthorization $providerAuthorization): ProviderAuthorization => $providerAuthorization)
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => ! $sourceOAuthAccount->isActive()))
        ;

        $this->service()->markInvalid($providerAuthorization);
    }

    private function service(): SourceAuthorizationInvalidationService
    {
        return new SourceAuthorizationInvalidationService(
            $this->sourceRepository,
            $this->providerAuthorizationRepository,
            new SourceOAuthService(
                $this->createMock(OAuthProviderPort::class),
                $this->sourceOAuthAccountRepository,
                new SourceOAuthAccountSecrets($this->createMock(SecretEncryptorInterface::class)),
                $this->createMock(OrganizationQueryPort::class),
                $this->createStub(SourceProviderInstanceRepositoryInterface::class),
                $this->inertSourceProviderInstanceOAuthConfig(),
            ),
        );
    }
}
