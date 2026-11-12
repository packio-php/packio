<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Common\Security\SecretEncryptorInterface;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Application\Dto\SourceOAuthAuthorizationData;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipDecision;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipStatus;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifier;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(SourceOAuthAccountOwnershipDecision::class)]
#[CoversClass(SourceOAuthAccountOwnershipStatus::class)]
#[CoversClass(SourceOAuthAccountOwnershipVerifier::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceOAuthAccountOwnershipVerifierTest extends TestCase
{
    /** @var MockObject&SourceOAuthAccountRepositoryInterface */
    private MockObject $sourceOAuthAccountRepository;

    /** @var MockObject&SecretEncryptorInterface */
    private MockObject $secretEncryptor;

    protected function setUp(): void
    {
        $this->sourceOAuthAccountRepository = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
        $this->secretEncryptor              = $this->createMock(SecretEncryptorInterface::class);
    }

    #[Test]
    public function allowsOwnedActiveAccountAndReturnsOauthPayload(): void
    {
        $sourceOAuthAccount = $this->account();

        $this->sourceOAuthAccountRepository->expects(self::once())
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
            ->willReturn('decrypted_at')
        ;

        $sourceOAuthAccountOwnershipDecision = $this->verifier()->verify(
            new ActorContext('org-1', 'user-1', 'owner'),
            'user-1',
            'github',
            'soa-1',
        );

        self::assertSame(SourceOAuthAccountOwnershipStatus::Allowed, $sourceOAuthAccountOwnershipDecision->status);
        self::assertTrue($sourceOAuthAccountOwnershipDecision->isAllowed());
        $sourceOAuthAuthorizationData = $sourceOAuthAccountOwnershipDecision->requireAuthorizationData();
        self::assertInstanceOf(SourceOAuthAuthorizationData::class, $sourceOAuthAuthorizationData);
        self::assertSame(
            '00000000-0000-0000-0000-000000000001',
            $sourceOAuthAuthorizationData->sourceOAuthAccountId,
        );
        self::assertSame('decrypted_at', $sourceOAuthAuthorizationData->accessToken);
        self::assertSame('ghuser', $sourceOAuthAuthorizationData->providerUsername);
        self::assertSame('gh@example.com', $sourceOAuthAuthorizationData->providerEmail);
    }

    #[Test]
    public function allowsCanonicalAccountForAnotherManagedOrganization(): void
    {
        $sourceOAuthAccount = $this->account();

        $this->sourceOAuthAccountRepository->method('findById')->willReturn($sourceOAuthAccount);
        $this->secretEncryptor->method('isEncrypted')->with('enc_at')->willReturn(true);
        $this->secretEncryptor->method('decrypt')->with('enc_at')->willReturn('decrypted_at');

        $sourceOAuthAccountOwnershipDecision = $this->verifier()->verify(
            new ActorContext('org-2', 'user-1', 'owner'),
            'user-1',
            'github',
            'soa-1',
        );

        self::assertSame(SourceOAuthAccountOwnershipStatus::Allowed, $sourceOAuthAccountOwnershipDecision->status);
        self::assertSame('decrypted_at', $sourceOAuthAccountOwnershipDecision->requireAuthorizationData()->accessToken);
    }

    #[Test]
    public function returnsNotFoundWhenAccountDoesNotExist(): void
    {
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('missing')
            ->willReturn(null)
        ;
        $this->secretEncryptor->expects(self::never())->method('decrypt');

        $sourceOAuthAccountOwnershipDecision = $this->verifier()->verify(
            new ActorContext('org-1', 'user-1', 'owner'),
            'user-1',
            'github',
            'missing',
        );

        self::assertSame(SourceOAuthAccountOwnershipStatus::NotFound, $sourceOAuthAccountOwnershipDecision->status);
        self::assertSame('Source OAuth account not found.', $sourceOAuthAccountOwnershipDecision->message);
    }

    #[Test]
    #[DataProvider('unavailableAccounts')]
    public function returnsUnavailableForOwnershipMismatches(SourceOAuthAccount $sourceOAuthAccount): void
    {
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->willReturn($sourceOAuthAccount)
        ;
        $this->secretEncryptor->expects(self::never())->method('decrypt');

        $sourceOAuthAccountOwnershipDecision = $this->verifier()->verify(
            new ActorContext('org-1', 'user-1', 'owner'),
            'user-1',
            'github',
            'soa-1',
        );

        self::assertSame(SourceOAuthAccountOwnershipStatus::Unavailable, $sourceOAuthAccountOwnershipDecision->status);
        self::assertSame('Source OAuth account is not available.', $sourceOAuthAccountOwnershipDecision->message);
    }

    /** @return iterable<string, array{SourceOAuthAccount}> */
    public static function unavailableAccounts(): iterable
    {
        yield 'wrong user' => [self::account(userId: 'user-2')];

        yield 'wrong provider' => [self::account(provider: 'gitlab')];

        yield 'inactive' => [self::account(active: false)];
    }

    #[Test]
    public function returnsTokenUnavailableWhenAccessTokenCannotBeDecrypted(): void
    {
        $this->sourceOAuthAccountRepository->method('findById')->willReturn($this->account());
        $this->secretEncryptor->method('isEncrypted')->with('enc_at')->willReturn(false);

        $sourceOAuthAccountOwnershipDecision = $this->verifier()->verify(
            new ActorContext('org-1', 'user-1', 'owner'),
            'user-1',
            'github',
            'soa-1',
        );

        self::assertSame(SourceOAuthAccountOwnershipStatus::TokenUnavailable, $sourceOAuthAccountOwnershipDecision->status);
        self::assertSame('Source OAuth account token is missing or unreadable.', $sourceOAuthAccountOwnershipDecision->message);
    }

    private function verifier(): SourceOAuthAccountOwnershipVerifier
    {
        return new SourceOAuthAccountOwnershipVerifier(
            $this->sourceOAuthAccountRepository,
            new SourceOAuthAccountSecrets($this->secretEncryptor),
        );
    }

    private static function account(string $userId = 'user-1', string $provider = 'github', bool $active = true): SourceOAuthAccount
    {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: $userId,
            provider: $provider,
            providerUserId: 'provider-user-1',
            providerEmail: 'gh@example.com',
            providerUsername: 'ghuser',
            accessToken: 'enc_at',
            isActive: $active,
        );
        $sourceOAuthAccount->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000001'));

        return $sourceOAuthAccount;
    }
}
