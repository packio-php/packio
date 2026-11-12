<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Authentication;

use Cake\Chronos\Chronos;
use Packio\Identity\Api\Authentication\ApiAccessTokenContract;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Application\Query\ApiAccessTokenQueryService;
use Packio\Identity\Application\Result\ApiAccessTokenResult;
use Packio\Identity\Application\Security\ApiAccessTokenHasher;
use Packio\Identity\Domain\Entity\ApiAccessToken as ApiAccessTokenEntity;
use Packio\Identity\Domain\Repository\ApiAccessTokenRepositoryInterface;
use Packio\Identity\Infrastructure\Authentication\ApiAccessToken;
use Packio\Identity\Infrastructure\Authentication\ApiAccessTokenStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Sirix\Mezzio\Authentication\Exception\StorageException;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

#[CoversClass(ApiAccessToken::class)]
#[CoversClass(ApiAccessTokenStorage::class)]
final class ApiAccessTokenTest extends TestCase
{
    #[Test]
    public function exposesPackageTokenMetadataAndPublicApiTokenPayload(): void
    {
        $apiAccessToken = new ApiAccessToken(new ApiAccessTokenDto(
            id: 'token-1',
            userId: 'user-1',
            name: 'Deploy token',
            tokenPrefix: 'packio_api_abcd',
            scopes: ['read', 'packages:write'],
            createdAt: '2026-01-01T00:00:00+00:00',
            expiresAt: '2026-12-31T23:59:59+00:00',
        ));

        self::assertSame('token-1', $apiAccessToken->getId());
        self::assertSame(ApiAccessTokenContract::STORAGE, $apiAccessToken->getStorage());
        self::assertSame('user-1', $apiAccessToken->getPayload()['user_id']);
        self::assertSame('token-1', $apiAccessToken->getPayload()['api_token_id']);
        self::assertSame(['read', 'packages:write'], $apiAccessToken->getPayload()['scopes']);
        self::assertInstanceOf(ApiAccessTokenDto::class, $apiAccessToken->getPayload()['api_access_token']);
        self::assertSame(1798761599, $apiAccessToken->getExpiresAt());
    }

    #[Test]
    public function storageCannotCreateTokens(): void
    {
        $apiAccessTokenStorage = new ApiAccessTokenStorage(
            new ApiAccessTokenQueryService(
                $this->createStub(ApiAccessTokenRepositoryInterface::class),
                new ApiAccessTokenHasher('test-key'),
                $this->objectMapper(),
            ),
            $this->objectMapper(),
        );
        $this->expectException(StorageException::class);
        $apiAccessTokenStorage->create([]);
    }

    #[Test]
    public function storageCannotDeleteTokens(): void
    {
        $apiAccessTokenStorage = new ApiAccessTokenStorage(
            new ApiAccessTokenQueryService(
                $this->createStub(ApiAccessTokenRepositoryInterface::class),
                new ApiAccessTokenHasher('test-key'),
                $this->objectMapper(),
            ),
            $this->objectMapper(),
        );
        $apiAccessToken = new ApiAccessToken(new ApiAccessTokenDto(
            id: 'token-1',
            userId: 'user-1',
            name: 'Deploy token',
            tokenPrefix: 'packio_api_abcd',
            scopes: [],
            createdAt: '',
        ));

        $this->expectException(StorageException::class);
        $apiAccessTokenStorage->delete($apiAccessToken);
    }

    #[Test]
    public function loadsValidTokenWithoutRecordingUseBeforeActorResolution(): void
    {
        $entity     = $this->entity();
        $repository = $this->createMock(ApiAccessTokenRepositoryInterface::class);
        $repository->expects(self::once())->method('findByTokenHash')->willReturn($entity);
        $repository->expects(self::never())->method('save');

        $token = $this->storage($repository)->load('plain-token');

        self::assertInstanceOf(ApiAccessToken::class, $token);
        self::assertSame('018f25e5-fd27-7000-8000-000000000002', $token->getId());
        self::assertSame('user-1', $token->getPayload()['user_id']);
        self::assertNull($entity->lastUsedAt());
    }

    #[Test]
    public function recordsUseOnlyForAnActiveTokenOwnedByTheResolvedActor(): void
    {
        $entity     = $this->entity();
        $repository = $this->createMock(ApiAccessTokenRepositoryInterface::class);
        $repository->expects(self::once())->method('findByIdForUser')
            ->with('018f25e5-fd27-7000-8000-000000000002', 'user-1')
            ->willReturn($entity)
        ;
        $repository->expects(self::once())->method('save')->with($entity)->willReturn($entity);

        self::assertTrue(new ApiAccessTokenQueryService(
            $repository,
            new ApiAccessTokenHasher('test-key'),
            $this->objectMapper(),
        )->recordSuccessfulAuthentication('018f25e5-fd27-7000-8000-000000000002', 'user-1'));
        self::assertNotNull($entity->lastUsedAt());
    }

    #[Test]
    public function doesNotLoadUnknownToken(): void
    {
        $repository = $this->createMock(ApiAccessTokenRepositoryInterface::class);
        $repository->expects(self::once())->method('findByTokenHash')->willReturn(null);
        $repository->expects(self::never())->method('save');

        self::assertNull($this->storage($repository)->load('unknown-token'));
    }

    #[Test]
    public function doesNotLoadRevokedOrExpiredToken(): void
    {
        foreach ([
            $this->entity(revokedAt: new Chronos('-1 minute')),
            $this->entity(expiresAt: new Chronos('-1 minute')),
        ] as $entity) {
            $repository = $this->createMock(ApiAccessTokenRepositoryInterface::class);
            $repository->expects(self::once())->method('findByTokenHash')->willReturn($entity);
            $repository->expects(self::never())->method('save');

            self::assertNull($this->storage($repository)->load('plain-token'));
        }
    }

    #[Test]
    public function propagatesUnexpectedPersistenceFailure(): void
    {
        $entity     = $this->entity();
        $repository = $this->createMock(ApiAccessTokenRepositoryInterface::class);
        $repository->expects(self::once())->method('findByIdForUser')->willReturn($entity);
        $repository->expects(self::once())->method('save')->willThrowException(new RuntimeException('Database unavailable'));

        $this->expectException(RuntimeException::class);
        new ApiAccessTokenQueryService(
            $repository,
            new ApiAccessTokenHasher('test-key'),
            $this->objectMapper(),
        )->recordSuccessfulAuthentication('018f25e5-fd27-7000-8000-000000000002', 'user-1');
    }

    private function storage(ApiAccessTokenRepositoryInterface $apiAccessTokenRepository): ApiAccessTokenStorage
    {
        return new ApiAccessTokenStorage(new ApiAccessTokenQueryService(
            $apiAccessTokenRepository,
            new ApiAccessTokenHasher('test-key'),
            $this->objectMapper(),
        ), $this->objectMapper());
    }

    private function objectMapper(): ObjectMapperInterface
    {
        $objectMapper = $this->createStub(ObjectMapperInterface::class);
        $objectMapper->method('map')->willReturnCallback(static function(object $source, string $target): object {
            if (ApiAccessTokenResult::class === $target && $source instanceof ApiAccessTokenEntity) {
                return new ApiAccessTokenResult(
                    id: $source->getIdentifier()->toString(),
                    userId: $source->userId(),
                    name: $source->name(),
                    tokenPrefix: $source->tokenPrefix(),
                    scopes: $source->scopes(),
                    createdAt: $source->getCreatedAt()->format(DATE_ATOM),
                    updatedAt: $source->getUpdatedAt()?->format(DATE_ATOM) ?? '',
                    lastUsedAt: $source->lastUsedAt()?->format(DATE_ATOM) ?? '',
                    expiresAt: $source->expiresAt()?->format(DATE_ATOM) ?? '',
                    revokedAt: $source->revokedAt()?->format(DATE_ATOM) ?? '',
                );
            }

            self::assertInstanceOf(ApiAccessTokenResult::class, $source);

            return new ApiAccessTokenDto(
                id: $source->id,
                userId: $source->userId,
                name: $source->name,
                tokenPrefix: $source->tokenPrefix,
                scopes: $source->scopes,
                createdAt: $source->createdAt,
                updatedAt: $source->updatedAt,
                lastUsedAt: $source->lastUsedAt,
                expiresAt: $source->expiresAt,
                revokedAt: $source->revokedAt,
            );
        });

        return $objectMapper;
    }

    private function entity(?Chronos $expiresAt = null, ?Chronos $revokedAt = null): ApiAccessTokenEntity
    {
        $apiAccessToken = ApiAccessTokenEntity::issue('user-1', 'Deploy token', 'hash', 'packio_api_abcd', ['read'], $expiresAt);
        $apiAccessToken->setIdentifier(Uuid::fromString('018f25e5-fd27-7000-8000-000000000002'));
        $apiAccessToken->setCreatedAt(new Chronos('2026-01-01T00:00:00+00:00'));

        if ($revokedAt instanceof Chronos) {
            $apiAccessToken->revoke($revokedAt);
        }

        return $apiAccessToken;
    }
}
