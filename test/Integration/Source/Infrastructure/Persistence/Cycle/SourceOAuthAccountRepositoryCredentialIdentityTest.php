<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceOAuthAccountRepository;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function str_repeat;

final class SourceOAuthAccountRepositoryCredentialIdentityTest extends IntegrationTestCase
{
    #[Test]
    public function findByCredentialIdentityReturnsInactiveCanonicalAccountForReconnect(): void
    {
        $repository         = $this->repository();
        $sourceOAuthAccount = $this->account(providerUserId: 'provider-user-1');
        $sourceOAuthAccount->markAsInactive();

        $repository->save($sourceOAuthAccount);

        $found = $repository->findByCredentialIdentity(
            'user-1',
            'codeberg',
            'provider-user-1',
            null,
        );

        self::assertSame($sourceOAuthAccount->getIdentifier()->toString(), $found?->getIdentifier()->toString());
        self::assertNull($repository->findActiveByCredentialIdentity(
            'user-1',
            'codeberg',
            'provider-user-1',
            null,
        ));
    }

    #[Test]
    public function findByCredentialIdentityPrefersCredentialKeyBackedCanonicalAccount(): void
    {
        $repository         = $this->repository();
        $sourceOAuthAccount = $this->account(
            providerUserId: 'provider-user-1',
            tokenExpiresAt: Chronos::parse('+2 hours'),
        );
        $credentialKeyAccount = $this->account(
            providerUserId: 'provider-user-1',
            tokenExpiresAt: Chronos::parse('+1 hour'),
        );
        $credentialKeyAccount->assignCredentialKey(str_repeat('b', 64));

        $repository->save($sourceOAuthAccount);
        $repository->save($credentialKeyAccount);

        $found = $repository->findByCredentialIdentity(
            'user-1',
            'codeberg',
            'provider-user-1',
            null,
        );

        self::assertSame($credentialKeyAccount->getIdentifier()->toString(), $found?->getIdentifier()->toString());
    }

    #[Test]
    public function findActiveByCredentialIdentityKeepsProviderInstancesIsolated(): void
    {
        $repository         = $this->repository();
        $sourceOAuthAccount = $this->account(
            providerUserId: 'provider-user-1',
            provider: 'gitlab_self_hosted',
            providerInstanceId: '00000000-0000-0000-0000-000000000101',
            providerBaseUrl: 'https://gitlab-one.example.test',
        );
        $secondInstanceAccount = $this->account(
            providerUserId: 'provider-user-1',
            provider: 'gitlab_self_hosted',
            providerInstanceId: '00000000-0000-0000-0000-000000000102',
            providerBaseUrl: 'https://gitlab-two.example.test',
        );

        $repository->save($sourceOAuthAccount);
        $repository->save($secondInstanceAccount);

        $found = $repository->findActiveByCredentialIdentity(
            'user-1',
            'gitlab_self_hosted',
            'provider-user-1',
            '00000000-0000-0000-0000-000000000102',
        );

        self::assertSame($secondInstanceAccount->getIdentifier()->toString(), $found?->getIdentifier()->toString());
        self::assertNull($repository->findActiveByCredentialIdentity(
            'user-1',
            'gitlab_self_hosted',
            'provider-user-1',
            null,
        ));
    }

    #[Test]
    public function findByProviderInstanceIncludesCanonicalAccounts(): void
    {
        $repository         = $this->repository();
        $sourceOAuthAccount = $this->account(
            providerUserId: 'provider-user-1',
            provider: 'gitlab_self_hosted',
            providerInstanceId: '00000000-0000-0000-0000-000000000101',
            providerBaseUrl: 'https://gitlab-one.example.test',
        );

        $repository->save($sourceOAuthAccount);

        $found = $repository->findByProviderInstance(
            '00000000-0000-0000-0000-000000000101',
        );

        self::assertCount(1, $found);
        self::assertSame($sourceOAuthAccount->getIdentifier()->toString(), $found[0]->getIdentifier()->toString());
    }

    #[Test]
    public function saveOrUpdateByCredentialKeyUpdatesExistingAccountAfterUniqueConflict(): void
    {
        $repository         = $this->repository();
        $credentialKey      = str_repeat('a', 64);
        $sourceOAuthAccount = $this->account(providerUserId: 'provider-user-1');
        $sourceOAuthAccount->assignCredentialKey($credentialKey);
        $duplicateAccount = $this->account(providerUserId: 'provider-user-1');
        $duplicateAccount->assignCredentialKey($credentialKey);

        $repository->save($sourceOAuthAccount);

        $saved = $repository->saveOrUpdateByCredentialKey(
            $duplicateAccount,
            static function(SourceOAuthAccount $sourceOAuthAccount): void {
                $sourceOAuthAccount->syncAuthorizationContext(
                    'provider-user-1',
                    'updated@example.test',
                    'updated-user',
                    ['repo'],
                );
                $sourceOAuthAccount->updateTokens('enc-updated-access-token', 'enc-updated-refresh-token', null);
            },
        );

        self::assertSame($sourceOAuthAccount->getIdentifier()->toString(), $saved->getIdentifier()->toString());
        self::assertSame('updated-user', $saved->providerUsername());
        self::assertSame('enc-updated-access-token', $saved->storedAccessToken());
        self::assertCount(1, $repository->findActiveAccountsByCredentialIdentity(
            'user-1',
            'codeberg',
            'provider-user-1',
            null,
        ));
    }

    private function repository(): SourceOAuthAccountRepository
    {
        $repository = $this->getService(SourceOAuthAccountRepository::class);
        self::assertInstanceOf(SourceOAuthAccountRepository::class, $repository);

        return $repository;
    }

    private function account(
        string $providerUserId,
        string $provider = 'codeberg',
        ?Chronos $tokenExpiresAt = null,
        ?string $providerInstanceId = null,
        ?string $providerBaseUrl = null,
    ): SourceOAuthAccount {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: $provider,
            providerUserId: $providerUserId,
            accessToken: 'enc-access-token',
            refreshToken: 'enc-refresh-token',
            tokenExpiresAt: $tokenExpiresAt,
            providerInstanceId: $providerInstanceId,
            providerBaseUrl: $providerBaseUrl,
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid7());

        return $sourceOAuthAccount;
    }
}
