<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Mapper;

use Packio\Source\Application\Service\SecretResolverInterface;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Infrastructure\Mapper\ProviderAuthorizationDtoMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(ProviderAuthorizationDtoMapper::class)]
#[AllowMockObjectsWithoutExpectations]
final class ProviderAuthorizationDtoMapperTest extends TestCase
{
    private MockObject&SecretResolverInterface $sourceSecretResolver;
    private MockObject&SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository;

    protected function setUp(): void
    {
        $this->sourceSecretResolver         = $this->createMock(SecretResolverInterface::class);
        $this->sourceOAuthAccountRepository = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
    }

    #[Test]
    public function mapMarksInactiveAuthorizationAsReauthorizationRequiredEvenWhenOAuthAccountIsActive(): void
    {
        $authorization = $this->authorization();
        $authorization->updateStatus('inactive');
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            accessToken: 'enc_token',
        );

        $this->sourceSecretResolver->expects(self::once())
            ->method('resolveAuthorizationEntitySecret')
            ->with($authorization, $sourceOAuthAccount)
            ->willReturn('ghp_tokenabcd')
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;

        $providerAuthorizationDto = $this->mapper()->map($authorization);

        self::assertFalse($providerAuthorizationDto->configured);
        self::assertSame('****abcd', $providerAuthorizationDto->maskedValue);
        self::assertSame('reauthorization_required', $providerAuthorizationDto->authHealth);
        self::assertSame('Provider authorization is inactive. Please reconnect your provider.', $providerAuthorizationDto->authFailureReason);
    }

    #[Test]
    public function mapKeepsDtoAvailableWhenOAuthTokenCannotBeResolvedForMasking(): void
    {
        $authorization = $this->authorization();

        $this->sourceSecretResolver->expects(self::once())
            ->method('resolveAuthorizationEntitySecret')
            ->with($authorization, null)
            ->willThrowException(new SourceTokenRefreshUnavailableException('OAuth account is inactive. Reauthorization required.'))
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn(null)
        ;

        $providerAuthorizationDto = $this->mapper()->map($authorization);

        self::assertSame('', $providerAuthorizationDto->maskedValue);
        self::assertSame('reauthorization_required', $providerAuthorizationDto->authHealth);
        self::assertSame('Linked OAuth account not found. Please reconnect your provider.', $providerAuthorizationDto->authFailureReason);
    }

    #[Test]
    public function mapMarksAuthorizationAsReauthorizationRequiredWhenActiveOAuthAccountTokenCannotBeResolved(): void
    {
        $authorization      = $this->authorization();
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            accessToken: 'enc_token',
        );

        $this->sourceSecretResolver->expects(self::once())
            ->method('resolveAuthorizationEntitySecret')
            ->with($authorization, $sourceOAuthAccount)
            ->willThrowException(new SourceTokenRefreshUnavailableException('OAuth account token is unavailable.'))
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;

        $providerAuthorizationDto = $this->mapper()->map($authorization);

        self::assertSame('', $providerAuthorizationDto->maskedValue);
        self::assertSame('reauthorization_required', $providerAuthorizationDto->authHealth);
        self::assertSame('OAuth account token is unavailable. Please reconnect your provider.', $providerAuthorizationDto->authFailureReason);
    }

    private function mapper(): ProviderAuthorizationDtoMapper
    {
        return new ProviderAuthorizationDtoMapper(
            $this->sourceSecretResolver,
            $this->sourceOAuthAccountRepository,
        );
    }

    private function authorization(): ProviderAuthorization
    {
        $providerAuthorization = ProviderAuthorization::authorizeFromOAuth(
            organizationId: 'org-1',
            provider: 'github',
            sourceOAuthAccountId: 'soa-1',
            authStrategy: 'github_token',
            configuration: [
                'token' => 'enc_token',
            ],
            accountName: 'gh-user',
        );
        $providerAuthorization->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000001'));

        return $providerAuthorization;
    }
}
