<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Application\Command;

use Cake\Chronos\Chronos;
use Packio\Identity\Application\Command\ApiAccessTokenCommandService;
use Packio\Identity\Application\Result\ApiAccessTokenResult;
use Packio\Identity\Application\Security\ApiAccessTokenHasher;
use Packio\Identity\Domain\Dto\ApiAccessTokenRegenerationOutcome;
use Packio\Identity\Domain\Dto\ApiAccessTokenRegenerationSpec;
use Packio\Identity\Domain\Entity\ApiAccessToken;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\ApiAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_filter;
use function array_values;
use function str_starts_with;

#[CoversClass(ApiAccessTokenCommandService::class)]
final class ApiAccessTokenCommandServiceTest extends TestCase
{
    #[Test]
    public function nonAdminCannotIssueAdminScopes(): void
    {
        $issuedApiAccessToken = $this->commandService($this->persistingRepository(), User::register('user@example.test', 'User', '', ['user']))
            ->issueForUser('user-1', 'Token', ['admin', 'read'])
        ;
        self::assertSame(['read'], $issuedApiAccessToken->token->scopes);

        $admin = $this->commandService($this->persistingRepository(), User::register('admin@example.test', 'Admin', '', ['admin', 'user']))
            ->issueForUser('admin-1', 'Token', ['admin', 'read'])
        ;
        self::assertSame(['admin', 'read'], $admin->token->scopes);
    }

    #[Test]
    public function regenerateIssuesReplacementAndRevokesOldToken(): void
    {
        $apiAccessToken                   = $this->activeToken(userId: 'user-1', name: 'Deploy token', scopes: ['read', 'write']);
        $inMemoryApiAccessTokenRepository = new InMemoryApiAccessTokenRepository([$apiAccessToken]);

        $apiAccessTokenCommandService = $this->commandService($inMemoryApiAccessTokenRepository);

        $apiAccessTokenRegeneration = $apiAccessTokenCommandService->regenerateForUser('user-1', $apiAccessToken->getIdentifier()->toString());

        self::assertTrue($apiAccessTokenRegeneration->success);
        self::assertSame($apiAccessToken->getIdentifier()->toString(), $apiAccessTokenRegeneration->revokedId);
        self::assertNotNull($apiAccessTokenRegeneration->issued);

        $replacement = $apiAccessTokenRegeneration->issued->token;
        self::assertSame('user-1', $replacement->userId);
        self::assertSame('Deploy token', $replacement->name);
        self::assertSame(['read', 'write'], $replacement->scopes);
        self::assertSame('', $replacement->revokedAt);
        self::assertNotSame($apiAccessToken->getIdentifier()->toString(), $replacement->id);

        $revoked = $inMemoryApiAccessTokenRepository->findByIdForUser($apiAccessTokenRegeneration->revokedId, 'user-1');
        self::assertNotNull($revoked);
        self::assertTrue($revoked->isRevoked());

        self::assertNotSame('', $apiAccessTokenRegeneration->issued->plainTextToken);
        self::assertTrue(str_starts_with($apiAccessTokenRegeneration->issued->plainTextToken, $replacement->tokenPrefix));
    }

    #[Test]
    public function regenerateKeepsAStoredUnknownScopeUnchanged(): void
    {
        $apiAccessToken                       = $this->activeToken('user-1', 'Legacy token', ['read', 'legacy:thing']);
        $inMemoryApiAccessTokenRepository     = new InMemoryApiAccessTokenRepository([$apiAccessToken]);

        $apiAccessTokenRegeneration = $this->commandService($inMemoryApiAccessTokenRepository)->regenerateForUser('user-1', $apiAccessToken->getIdentifier()->toString());

        self::assertTrue($apiAccessTokenRegeneration->success);
        self::assertNotNull($apiAccessTokenRegeneration->issued);
        self::assertSame(['read', 'legacy:thing'], $apiAccessTokenRegeneration->issued->token->scopes);
    }

    #[Test]
    public function issueIgnoresBlankScopesBeforeValidation(): void
    {
        $issuedApiAccessToken = $this->commandService($this->persistingRepository(), User::register('user@example.test', 'User', '', ['user']))
            ->issueForUser('user-1', 'Token', [' ', ' read '])
        ;

        self::assertSame(['read'], $issuedApiAccessToken->token->scopes);
    }

    #[Test]
    public function regenerateKeepsTheSameExpiry(): void
    {
        $expiresAt                        = new Chronos('2030-01-01 00:00:00');
        $apiAccessToken                   = $this->activeToken('user-1', 'Deploy token', ['read'], $expiresAt);
        $inMemoryApiAccessTokenRepository = new InMemoryApiAccessTokenRepository([$apiAccessToken]);

        $apiAccessTokenCommandService = $this->commandService($inMemoryApiAccessTokenRepository);

        $apiAccessTokenRegeneration = $apiAccessTokenCommandService->regenerateForUser('user-1', $apiAccessToken->getIdentifier()->toString());

        self::assertTrue($apiAccessTokenRegeneration->success);
        self::assertNotNull($apiAccessTokenRegeneration->issued);
        self::assertSame($expiresAt->format(DATE_ATOM), $apiAccessTokenRegeneration->issued->token->expiresAt);
    }

    #[Test]
    public function regenerateReturnsNotFoundForUnknownToken(): void
    {
        $inMemoryApiAccessTokenRepository = new InMemoryApiAccessTokenRepository([]);

        $apiAccessTokenCommandService = $this->commandService($inMemoryApiAccessTokenRepository);

        $apiAccessTokenRegeneration = $apiAccessTokenCommandService->regenerateForUser('user-1', Uuid::uuid7()->toString());

        self::assertFalse($apiAccessTokenRegeneration->success);
        self::assertTrue($apiAccessTokenRegeneration->isNotFound());
        self::assertNull($apiAccessTokenRegeneration->issued);
    }

    #[Test]
    public function regenerateReturnsNotFoundForForeignToken(): void
    {
        $apiAccessToken                     = $this->activeToken('user-2', 'Other user token', ['read']);
        $inMemoryApiAccessTokenRepository   = new InMemoryApiAccessTokenRepository([$apiAccessToken]);

        $apiAccessTokenCommandService = $this->commandService($inMemoryApiAccessTokenRepository);

        $apiAccessTokenRegeneration = $apiAccessTokenCommandService->regenerateForUser('user-1', $apiAccessToken->getIdentifier()->toString());

        self::assertFalse($apiAccessTokenRegeneration->success);
        self::assertTrue($apiAccessTokenRegeneration->isNotFound());
        self::assertFalse($apiAccessToken->isRevoked(), 'A foreign token must not be touched.');
    }

    #[Test]
    public function regenerateReturnsNotFoundForRevokedToken(): void
    {
        $apiAccessToken = $this->activeToken('user-1', 'Revoked token', ['read']);
        $apiAccessToken->revoke(new Chronos());
        $inMemoryApiAccessTokenRepository = new InMemoryApiAccessTokenRepository([$apiAccessToken]);

        $apiAccessTokenCommandService = $this->commandService($inMemoryApiAccessTokenRepository);

        $apiAccessTokenRegeneration = $apiAccessTokenCommandService->regenerateForUser('user-1', $apiAccessToken->getIdentifier()->toString());

        self::assertFalse($apiAccessTokenRegeneration->success);
        self::assertTrue($apiAccessTokenRegeneration->isNotFound());
    }

    #[Test]
    public function regenerateReturnsNotFoundForExpiredToken(): void
    {
        $apiAccessToken                     = $this->activeToken('user-1', 'Expired token', ['read'], new Chronos('2020-01-01 00:00:00'));
        $inMemoryApiAccessTokenRepository   = new InMemoryApiAccessTokenRepository([$apiAccessToken]);

        $apiAccessTokenCommandService = $this->commandService($inMemoryApiAccessTokenRepository);

        $apiAccessTokenRegeneration = $apiAccessTokenCommandService->regenerateForUser('user-1', $apiAccessToken->getIdentifier()->toString());

        self::assertFalse($apiAccessTokenRegeneration->success);
        self::assertTrue($apiAccessTokenRegeneration->isNotFound());
    }

    #[Test]
    public function persistenceFailureIsReportedAsFailedOutcome(): void
    {
        $apiAccessToken                               = $this->activeToken('user-1', 'Deploy token', ['read']);
        $inMemoryApiAccessTokenRepository             = new InMemoryApiAccessTokenRepository([$apiAccessToken]);
        $inMemoryApiAccessTokenRepository->failOnSave = true;

        $apiAccessTokenCommandService = $this->commandService($inMemoryApiAccessTokenRepository);

        $apiAccessTokenRegeneration = $apiAccessTokenCommandService->regenerateForUser('user-1', $apiAccessToken->getIdentifier()->toString());

        self::assertFalse($apiAccessTokenRegeneration->success);
        self::assertTrue($apiAccessTokenRegeneration->isPersistenceFailed());
        self::assertNull($apiAccessTokenRegeneration->issued);

        $replacement = $inMemoryApiAccessTokenRepository->findReplacement();
        self::assertNull($replacement, 'Rollback must discard the replacement token.');

        $existingAfterFailure = $inMemoryApiAccessTokenRepository->findByIdForUser($apiAccessToken->getIdentifier()->toString(), 'user-1');
        self::assertNotNull($existingAfterFailure, 'The previous token must survive the rollback.');
        self::assertFalse($existingAfterFailure->isRevoked(), 'The previous token must remain usable after the rollback.');
    }

    private function persistingRepository(): ApiAccessTokenRepositoryInterface
    {
        $apiAccessTokenRepository = $this->createStub(ApiAccessTokenRepositoryInterface::class);
        $apiAccessTokenRepository->method('save')->willReturnCallback(static function(ApiAccessToken $apiAccessToken): ApiAccessToken {
            $apiAccessToken->setIdentifier(Uuid::uuid7());
            $apiAccessToken->setCreatedAt(new Chronos());

            return $apiAccessToken;
        });

        return $apiAccessTokenRepository;
    }

    /**
     * @param list<string> $scopes
     */
    private function activeToken(string $userId, string $name, array $scopes, ?Chronos $expiresAt = null): ApiAccessToken
    {
        $apiAccessToken = ApiAccessToken::issue(
            userId: $userId,
            name: $name,
            tokenHash: 'hash-' . $userId,
            tokenPrefix: 'packio_api_abcd',
            scopes: $scopes,
            expiresAt: $expiresAt,
        );
        $apiAccessToken->setIdentifier(Uuid::uuid7());

        return $apiAccessToken;
    }

    private function commandService(
        ApiAccessTokenRepositoryInterface $apiAccessTokenRepository,
        ?User $user = null
    ): ApiAccessTokenCommandService {
        $objectMapper = $this->createStub(ObjectMapperInterface::class);
        $objectMapper->method('map')->willReturnCallback($this->mapToken(...));
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        return new ApiAccessTokenCommandService(
            $apiAccessTokenRepository,
            new ApiAccessTokenHasher('test-key'),
            $objectMapper,
            $userRepository,
        );
    }

    private function mapToken(ApiAccessToken $apiAccessToken): ApiAccessTokenResult
    {
        return new ApiAccessTokenResult(
            id: $apiAccessToken->getIdentifier()->toString(),
            userId: $apiAccessToken->userId(),
            name: $apiAccessToken->name(),
            tokenPrefix: $apiAccessToken->tokenPrefix(),
            scopes: $apiAccessToken->scopes(),
            createdAt: $apiAccessToken->getCreatedAt()->format(DATE_ATOM),
            updatedAt: $apiAccessToken->getUpdatedAt()?->format(DATE_ATOM) ?? '',
            lastUsedAt: $apiAccessToken->lastUsedAt()?->format(DATE_ATOM) ?? '',
            expiresAt: $apiAccessToken->expiresAt()?->format(DATE_ATOM) ?? '',
            revokedAt: $apiAccessToken->revokedAt()?->format(DATE_ATOM) ?? '',
        );
    }
}

final class InMemoryApiAccessTokenRepository implements ApiAccessTokenRepositoryInterface
{
    public bool $failOnSave = false;

    /** @var list<ApiAccessToken> */
    private array $replacementTokens = [];

    /** @param list<ApiAccessToken> $tokens */
    public function __construct(private array $tokens = []) {}

    public function save(ApiAccessToken $apiAccessToken): ApiAccessToken
    {
        if ($this->failOnSave) {
            throw new RepositoryPersistException('Simulated persistence failure.', 0);
        }

        $identifier = $apiAccessToken->getIdentifier()->toString();
        foreach ($this->tokens as $index => $existing) {
            if ($existing->getIdentifier()->toString() === $identifier) {
                $this->tokens[$index] = $apiAccessToken;

                return $apiAccessToken;
            }
        }

        if (! $apiAccessToken->revokedAt() instanceof Chronos) {
            $this->replacementTokens[] = $apiAccessToken;
        }
        $this->tokens[] = $apiAccessToken;

        return $apiAccessToken;
    }

    public function regenerate(
        string $userId,
        string $tokenId,
        ApiAccessTokenRegenerationSpec $apiAccessTokenRegenerationSpec
    ): ?ApiAccessTokenRegenerationOutcome {
        if ($this->failOnSave) {
            throw new RepositoryPersistException('Simulated persistence failure.', 0);
        }

        $revoked = $this->findByIdForUser($tokenId, $userId);
        if (! $revoked instanceof ApiAccessToken || $revoked->isRevoked() || $revoked->isExpired(new Chronos())) {
            return null;
        }

        $apiAccessToken = $apiAccessTokenRegenerationSpec->issueFor($revoked);
        $apiAccessToken->setIdentifier(Uuid::uuid7());
        $apiAccessToken->setCreatedAt(new Chronos());
        $this->save($apiAccessToken);
        $revoked->revoke(new Chronos());
        $this->save($revoked);

        return new ApiAccessTokenRegenerationOutcome($revoked, $apiAccessToken);
    }

    public function findByTokenHash(string $tokenHash): ?ApiAccessToken
    {
        foreach ($this->tokens as $token) {
            if ($token->tokenHash() === $tokenHash) {
                return $token;
            }
        }

        return null;
    }

    public function findByIdForUser(string $id, string $userId): ?ApiAccessToken
    {
        $identifier = null;
        foreach ($this->tokens as $token) {
            if ($token->getIdentifier()->toString() === $id) {
                $identifier = $id;
            }
        }

        if (null === $identifier) {
            return null;
        }

        foreach ($this->tokens as $token) {
            if ($token->getIdentifier()->toString() === $identifier && $token->userId() === $userId) {
                return $token;
            }
        }

        return null;
    }

    public function latestForUser(string $userId): array
    {
        return array_values(array_filter(
            $this->tokens,
            static fn (ApiAccessToken $apiAccessToken): bool => $apiAccessToken->userId() === $userId,
        ));
    }

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void {}

    public function findReplacement(): ?ApiAccessToken
    {
        return $this->replacementTokens[0] ?? null;
    }
}
