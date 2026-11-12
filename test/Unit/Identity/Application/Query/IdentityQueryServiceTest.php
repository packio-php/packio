<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Application\Query;

use Cake\Chronos\Chronos;
use Common\Security\SecretEncryptorInterface;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Application\Result\RegistryAccessTokenResult;
use Packio\Identity\Application\Security\RegistryAccessTokenSecrets;
use Packio\Identity\Domain\Entity\RegistryAccessToken;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

use function hash;

#[CoversClass(IdentityQueryService::class)]
#[AllowMockObjectsWithoutExpectations]
final class IdentityQueryServiceTest extends TestCase
{
    #[Test]
    public function authenticateRegistryAccessTokenMarksSavesAndReturnsValidToken(): void
    {
        $registryAccessToken = RegistryAccessToken::issue(
            organizationId: 'org-1',
            ownerUserId: 'user-1',
            name: 'Registry token',
            tokenHash: hash('sha256', 'plain-token'),
            maskedToken: 'pk_********',
            permissions: ['packages.read.private'],
            encryptedToken: 'encrypted-token',
        );
        $registryAccessToken->setIdentifier(Uuid::uuid7());
        $registryAccessToken->setCreatedAt(new Chronos('2026-01-01T00:00:00+00:00'));

        $registryAccessTokenRepository = $this->createMock(RegistryAccessTokenRepositoryInterface::class);
        $registryAccessTokenRepository->expects(self::once())
            ->method('findByTokenHash')
            ->with(hash('sha256', 'plain-token'))
            ->willReturn($registryAccessToken)
        ;
        $registryAccessTokenRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function(RegistryAccessToken $registryAccessToken): bool {
                self::assertSame('org-1', $registryAccessToken->organizationId());
                self::assertInstanceOf(Chronos::class, $registryAccessToken->lastUsedAt());

                return true;
            }))
            ->willReturnArgument(0)
        ;

        $secretEncryptor = $this->createMock(SecretEncryptorInterface::class);
        $secretEncryptor->expects(self::once())
            ->method('isEncrypted')
            ->with('encrypted-token')
            ->willReturn(true)
        ;
        $secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('encrypted-token')
            ->willReturn('plain-token')
        ;

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findById')->with('user-1')->willReturn(new User('user@example.test', 'User'));

        $result = $this->service($registryAccessTokenRepository, $secretEncryptor, $userRepository)
            ->authenticateRegistryAccessToken('plain-token')
        ;

        self::assertInstanceOf(RegistryAccessTokenResult::class, $result);
        self::assertSame('org-1', $result->organizationId);
        self::assertSame('Registry token', $result->name);
        self::assertSame(['packages.read.private'], $result->permissions);
        self::assertSame('pk_********', $result->maskedToken);
        self::assertSame('plain-token', $result->plainToken);
        self::assertNotSame('', $result->lastUsedAt);
    }

    #[Test]
    public function authenticateRegistryAccessTokenReturnsBannedOwnerWithoutRecordingUsage(): void
    {
        $registryAccessToken = RegistryAccessToken::issue('org-1', 'user-1', 'Registry token', hash('sha256', 'plain-token'), 'pk_********', ['packages.read.private']);
        $registryAccessToken->setIdentifier(Uuid::uuid7());
        $registryAccessToken->setCreatedAt(new Chronos('2026-01-01T00:00:00+00:00'));
        $registryAccessTokenRepository = $this->createMock(RegistryAccessTokenRepositoryInterface::class);
        $registryAccessTokenRepository->method('findByTokenHash')->willReturn($registryAccessToken);
        $registryAccessTokenRepository->expects(self::never())->method('save');
        $user = new User('user@example.test', 'User');
        $user->ban('Violation of project rules.', 'admin-1');
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findById')->with('user-1')->willReturn($user);

        $result = $this->service($registryAccessTokenRepository, userRepository: $userRepository)
            ->authenticateRegistryAccessToken('plain-token')
        ;

        self::assertInstanceOf(RegistryAccessTokenResult::class, $result);
        self::assertTrue($result->isOwnerBanned);
        self::assertSame('', $result->lastUsedAt);
    }

    #[Test]
    public function authenticateRegistryAccessTokenReturnsNullForEmptyTokenWithoutRepositoryLookup(): void
    {
        $registryAccessTokenRepository = $this->createMock(RegistryAccessTokenRepositoryInterface::class);
        $registryAccessTokenRepository->expects(self::never())->method('findByTokenHash');
        $registryAccessTokenRepository->expects(self::never())->method('save');

        self::assertNull($this->service($registryAccessTokenRepository)->authenticateRegistryAccessToken(''));
    }

    #[Test]
    public function authenticateRegistryAccessTokenReturnsNullForInvalidTokenWithoutSave(): void
    {
        $registryAccessTokenRepository = $this->createMock(RegistryAccessTokenRepositoryInterface::class);
        $registryAccessTokenRepository->expects(self::once())
            ->method('findByTokenHash')
            ->with(hash('sha256', 'invalid-token'))
            ->willReturn(null)
        ;
        $registryAccessTokenRepository->expects(self::never())->method('save');

        self::assertNull($this->service($registryAccessTokenRepository)->authenticateRegistryAccessToken('invalid-token'));
    }

    #[Test]
    public function authenticateRegistryAccessTokenFailsClosedWhenTokenHasNoOrganization(): void
    {
        $registryAccessTokenRepository = $this->createMock(RegistryAccessTokenRepositoryInterface::class);
        $registryAccessTokenRepository->expects(self::once())
            ->method('findByTokenHash')
            ->with(hash('sha256', 'plain-token'))
            ->willReturn(new RegistryAccessToken(tokenHash: hash('sha256', 'plain-token')))
        ;
        $registryAccessTokenRepository->expects(self::never())->method('save');

        self::assertNull($this->service($registryAccessTokenRepository)->authenticateRegistryAccessToken('plain-token'));
    }

    public function testOrganizationTokenQueryNormalizesSecretsAndDoesNotRecordUsage(): void
    {
        $registryAccessToken = RegistryAccessToken::issue('org-1', 'user-1', 'Deploy', 'hash', 'masked', ['packages.read'], 'encrypted');
        $registryAccessToken->setIdentifier(Uuid::uuid7());
        $registryAccessToken->setCreatedAt(new Chronos('2026-01-01T00:00:00+00:00'));
        $repository = $this->createMock(RegistryAccessTokenRepositoryInterface::class);
        $repository->expects(self::once())->method('latestForOrganization')->with('org-1')->willReturn([$registryAccessToken]);
        $repository->expects(self::never())->method('save');
        $encryptor = $this->createMock(SecretEncryptorInterface::class);
        $encryptor->method('isEncrypted')->with('encrypted')->willReturn(true);
        $encryptor->expects(self::once())->method('decrypt')->with('encrypted')->willReturn('plain');

        $results = $this->service($repository, $encryptor)->registryAccessTokensForOrganization('org-1');

        self::assertCount(1, $results);
        self::assertSame('org-1', $results[0]->organizationId);
        self::assertSame('plain', $results[0]->plainToken);
        self::assertSame('masked', $results[0]->maskedToken);
        self::assertSame('', $results[0]->lastUsedAt);
        self::assertSame(['packages.read'], $results[0]->permissions);
    }

    private function service(
        RegistryAccessTokenRepositoryInterface $registryAccessTokenRepository,
        ?SecretEncryptorInterface $secretEncryptor = null,
        ?UserRepositoryInterface $userRepository = null,
    ): IdentityQueryService {
        return new IdentityQueryService(
            $userRepository ?? $this->createMock(UserRepositoryInterface::class),
            $registryAccessTokenRepository,
            $this->createMock(UserOAuthAccountRepositoryInterface::class),
            new RegistryAccessTokenSecrets($secretEncryptor ?? $this->createMock(SecretEncryptorInterface::class)),
        );
    }
}
