<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Packio\Source\Domain\Dto\SourceOAuthRefreshCriteria;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Exception\SourceProviderInstanceUnavailableException;
use Packio\Source\Domain\Repository\SourceOAuthAccountLockingRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use PDO;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;
use Throwable;

use function array_filter;
use function array_slice;
use function array_values;
use function iterator_to_array;
use function str_contains;
use function strtolower;
use function trim;
use function usort;

/**
 * @extends AbstractWriteRepository<SourceOAuthAccount>
 */
final class SourceOAuthAccountRepository extends AbstractWriteRepository implements SourceOAuthAccountRepositoryInterface, SourceOAuthAccountLockingRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = SourceOAuthAccount::class;

    public function findById(string $id): ?SourceOAuthAccount
    {
        $entity = $this->findByPK($id);

        return $entity instanceof SourceOAuthAccount ? $entity : null;
    }

    public function findActiveByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId
    ): ?SourceOAuthAccount {
        return $this->findAccountsByCredentialIdentity(
            $userId,
            $provider,
            $providerUserId,
            $providerInstanceId,
            true,
        )[0] ?? null;
    }

    public function findByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId
    ): ?SourceOAuthAccount {
        return $this->findAccountsByCredentialIdentity(
            $userId,
            $provider,
            $providerUserId,
            $providerInstanceId,
            null,
        )[0] ?? null;
    }

    public function findActiveAccountsByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId
    ): array {
        return $this->findAccountsByCredentialIdentity(
            $userId,
            $provider,
            $providerUserId,
            $providerInstanceId,
            true,
        );
    }

    public function findByProviderInstance(string $providerInstanceId): array
    {
        return $this->select()
            ->where('provider_instance_id', $providerInstanceId)
            ->fetchAll()
        ;
    }

    public function findLockedByProviderInstance(string $providerInstanceId): array
    {
        return $this->select()
            ->where('provider_instance_id', $providerInstanceId)
            ->forUpdate()
            ->fetchAll()
        ;
    }

    public function findRefreshCandidates(SourceOAuthRefreshCriteria $sourceOAuthRefreshCriteria): array
    {
        $accounts = $this->refreshCandidateBaseQuery($sourceOAuthRefreshCriteria);
        $accounts = array_values(array_filter(
            $accounts,
            fn (SourceOAuthAccount $sourceOAuthAccount): bool => $this->isRefreshCandidate($sourceOAuthAccount, $sourceOAuthRefreshCriteria),
        ));

        usort($accounts, static function(SourceOAuthAccount $left, SourceOAuthAccount $right): int {
            $leftExpiresAt  = $left->tokenExpiresAt()?->getTimestamp() ?? PHP_INT_MAX;
            $rightExpiresAt = $right->tokenExpiresAt()?->getTimestamp() ?? PHP_INT_MAX;
            if ($leftExpiresAt !== $rightExpiresAt) {
                return $leftExpiresAt <=> $rightExpiresAt;
            }

            $leftLastUsedAt  = $left->lastUsedAt()?->getTimestamp() ?? 0;
            $rightLastUsedAt = $right->lastUsedAt()?->getTimestamp() ?? 0;
            if ($leftLastUsedAt !== $rightLastUsedAt) {
                return $leftLastUsedAt <=> $rightLastUsedAt;
            }

            $leftUpdatedAt  = $left->getUpdatedAt()?->getTimestamp() ?? 0;
            $rightUpdatedAt = $right->getUpdatedAt()?->getTimestamp() ?? 0;
            if ($leftUpdatedAt !== $rightUpdatedAt) {
                return $leftUpdatedAt <=> $rightUpdatedAt;
            }

            return $left->getIdentifier()->toString() <=> $right->getIdentifier()->toString();
        });

        return array_slice($accounts, 0, $sourceOAuthRefreshCriteria->limit);
    }

    public function save(SourceOAuthAccount $sourceOAuthAccount): void
    {
        $this->persistAndFlush($sourceOAuthAccount);
    }

    public function saveOrUpdateByCredentialKey(
        SourceOAuthAccount $sourceOAuthAccount,
        callable $updateExisting,
        ?string $organizationId = null,
    ): SourceOAuthAccount {
        $providerInstanceId = trim((string) $sourceOAuthAccount->providerInstanceId());
        if (null === $organizationId || '' === $organizationId || '' === $providerInstanceId) {
            return $this->saveOrUpdateByCredentialKeyWithoutInstanceGuard($sourceOAuthAccount, $updateExisting);
        }

        return $this->database()->transaction(function(DatabaseInterface $database) use ($sourceOAuthAccount, $updateExisting, $organizationId, $providerInstanceId): SourceOAuthAccount {
            $this->lockProviderInstanceDependencies($database, $organizationId, $providerInstanceId);
            $this->assertActiveProviderInstanceLocked($database, $organizationId, $providerInstanceId);

            return $this->saveOrUpdateByCredentialKeyWithoutInstanceGuard($sourceOAuthAccount, $updateExisting);
        });
    }

    public function withLockedById(string $id, callable $callback): mixed
    {
        return $this->database()->transaction(
            function() use ($id, $callback): mixed {
                $entity = $this->select()
                    ->where([
                        'uuid' => $id,
                    ])
                    ->forUpdate()
                    ->fetchOne()
                ;

                if (! $entity instanceof SourceOAuthAccount) {
                    return null;
                }

                return $callback($entity);
            },
        );
    }

    private function saveOrUpdateByCredentialKeyWithoutInstanceGuard(
        SourceOAuthAccount $sourceOAuthAccount,
        callable $updateExisting
    ): SourceOAuthAccount {
        $credentialKey = trim((string) $sourceOAuthAccount->credentialKey());
        if ('' === $credentialKey) {
            $this->save($sourceOAuthAccount);

            return $sourceOAuthAccount;
        }

        try {
            $this->save($sourceOAuthAccount);

            return $sourceOAuthAccount;
        } catch (RepositoryPersistException $exception) {
            if (! $this->isCredentialKeyUniqueViolation($exception)) {
                throw $exception;
            }
        }

        $existing = $this->withLockedByCredentialKey(
            $credentialKey,
            function(SourceOAuthAccount $sourceOAuthAccount) use ($updateExisting): SourceOAuthAccount {
                $updateExisting($sourceOAuthAccount);
                $this->persistAndFlush($sourceOAuthAccount);

                return $sourceOAuthAccount;
            },
        );

        if ($existing instanceof SourceOAuthAccount) {
            return $existing;
        }

        throw new RepositoryPersistException('Source OAuth credential unique conflict could not be resolved.');
    }

    /**
     * @return list<SourceOAuthAccount>
     */
    private function refreshCandidateBaseQuery(SourceOAuthRefreshCriteria $sourceOAuthRefreshCriteria): array
    {
        $select = $this->select()
            ->where('is_active', true)
            ->where('refresh_token', '!=', null)
        ;

        if (null !== $sourceOAuthRefreshCriteria->provider) {
            $select->where('provider', $sourceOAuthRefreshCriteria->provider);
        }

        if (null !== $sourceOAuthRefreshCriteria->providerInstanceId) {
            $select->where('provider_instance_id', $sourceOAuthRefreshCriteria->providerInstanceId);
        }

        if (null !== $sourceOAuthRefreshCriteria->organizationId && '' !== $sourceOAuthRefreshCriteria->organizationId) {
            // Same "active" criterion as ProviderAuthorizationRepository::findActiveBySourceOAuthAccountId.
            $accountIds = $this->database()->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
                ->selectQuery($this->database()->getPrefix())
                ->from('provider_authorizations')
                ->distinct()
                ->columns('source_oauth_account_id')
                ->where('organization_id', $sourceOAuthRefreshCriteria->organizationId)
                ->where('status', 'active')
                ->where('source_oauth_account_id', '!=', null)
                ->fetchAll(PDO::FETCH_COLUMN)
            ;
            if ([] === $accountIds) {
                return [];
            }

            $select->where('uuid', 'in', $accountIds);
        }

        return iterator_to_array($select->fetchAll(), false);
    }

    private function isRefreshCandidate(
        SourceOAuthAccount $sourceOAuthAccount,
        SourceOAuthRefreshCriteria $sourceOAuthRefreshCriteria
    ): bool {
        $updatedAt = $sourceOAuthAccount->getUpdatedAt();
        if ($updatedAt instanceof Chronos && $updatedAt > $sourceOAuthRefreshCriteria->updatedBefore) {
            return false;
        }

        $expiresAt = $sourceOAuthAccount->tokenExpiresAt();
        if ($expiresAt instanceof Chronos && $expiresAt <= $sourceOAuthRefreshCriteria->tokenExpiresBefore) {
            return true;
        }

        $lastUsedAt = $sourceOAuthAccount->lastUsedAt();

        return ! $lastUsedAt instanceof Chronos || $lastUsedAt <= $sourceOAuthRefreshCriteria->lastUsedBefore;
    }

    /**
     * @return list<SourceOAuthAccount>
     */
    private function findAccountsByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId,
        ?bool $isActive,
    ): array {
        $select = $this->select()
            ->where('user_id', $userId)
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
        ;

        if (null !== $isActive) {
            $select->where('is_active', $isActive);
        }

        if (null === $providerInstanceId || '' === $providerInstanceId) {
            $select->where('provider_instance_id', null);
        } else {
            $select->where('provider_instance_id', $providerInstanceId);
        }

        $accounts = $select->fetchAll();
        usort($accounts, static function(SourceOAuthAccount $left, SourceOAuthAccount $right): int {
            $leftCredentialKey  = null !== $left->credentialKey() ? 0 : 1;
            $rightCredentialKey = null !== $right->credentialKey() ? 0 : 1;
            if ($leftCredentialKey !== $rightCredentialKey) {
                return $leftCredentialKey <=> $rightCredentialKey;
            }

            $leftActive  = $left->isActive() ? 0 : 1;
            $rightActive = $right->isActive() ? 0 : 1;
            if ($leftActive !== $rightActive) {
                return $leftActive <=> $rightActive;
            }

            $leftExpiresAt  = $left->tokenExpiresAt()?->getTimestamp() ?? 0;
            $rightExpiresAt = $right->tokenExpiresAt()?->getTimestamp() ?? 0;
            if ($leftExpiresAt !== $rightExpiresAt) {
                return $rightExpiresAt <=> $leftExpiresAt;
            }

            $leftLastUsedAt  = $left->lastUsedAt()?->getTimestamp() ?? 0;
            $rightLastUsedAt = $right->lastUsedAt()?->getTimestamp() ?? 0;

            return $rightLastUsedAt <=> $leftLastUsedAt;
        });

        return $accounts;
    }

    private function withLockedByCredentialKey(string $credentialKey, callable $callback): mixed
    {
        return $this->database()->transaction(
            function() use ($credentialKey, $callback): mixed {
                $entity = $this->select()
                    ->where([
                        'credential_key' => $credentialKey,
                    ])
                    ->forUpdate()
                    ->fetchOne()
                ;

                if (! $entity instanceof SourceOAuthAccount) {
                    return null;
                }

                return $callback($entity);
            },
        );
    }

    private function isCredentialKeyUniqueViolation(Throwable $throwable): bool
    {
        $current = $throwable;
        while ($current instanceof Throwable) {
            $message = strtolower($current->getMessage());
            if (
                str_contains($message, 'source_oauth_accounts_unique_credential_key')
                || str_contains($message, 'credential_key')
            ) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    private function assertActiveProviderInstanceLocked(
        DatabaseInterface $database,
        string $organizationId,
        string $providerInstanceId,
    ): void {
        $rows = $database->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
            ->selectQuery($database->getPrefix())
            ->from('source_provider_instances')
            ->columns('uuid')
            ->where('uuid', $providerInstanceId)
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->forUpdate()
            ->limit(1)
            ->fetchAll()
        ;
        if ([] === $rows) {
            throw new SourceProviderInstanceUnavailableException('Source provider instance is not available.');
        }
    }

    private function lockProviderInstanceDependencies(
        DatabaseInterface $database,
        string $organizationId,
        string $providerInstanceId,
    ): void {
        $database->query(
            'SELECT pg_advisory_xact_lock(hashtext(?))',
            ['source-provider-instance-dependencies:' . $organizationId . ':' . $providerInstanceId],
        );
    }
}
