<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Packio\Source\Domain\Dto\SourceOAuthRefreshCriteria;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Infrastructure\Persistence\Cycle\ProviderAuthorizationRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceOAuthAccountRepository;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class SourceOAuthRefreshRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function sourceOAuthRefreshCandidatesRequireActiveAccountAndRefreshToken(): void
    {
        $sourceOAuthAccountRepository = $this->sourceOAuthAccountRepository();

        $sourceOAuthAccount = $this->account('github', refreshToken: 'enc-refresh', tokenExpiresAt: Chronos::parse('+1 hour'));
        $noRefresh          = $this->account('github', refreshToken: null, tokenExpiresAt: Chronos::parse('+1 hour'));
        $inactive           = $this->account('github', refreshToken: 'enc-refresh', tokenExpiresAt: Chronos::parse('+1 hour'));
        $inactive->markAsInactive();
        $otherProvider = $this->account('gitlab', refreshToken: 'enc-refresh', tokenExpiresAt: Chronos::parse('+1 hour'));

        $sourceOAuthAccountRepository->save($sourceOAuthAccount);
        $sourceOAuthAccountRepository->save($noRefresh);
        $sourceOAuthAccountRepository->save($inactive);
        $sourceOAuthAccountRepository->save($otherProvider);

        $sourceOAuthRefreshCriteria = new SourceOAuthRefreshCriteria(
            tokenExpiresBefore: Chronos::parse('+24 hours'),
            lastUsedBefore: Chronos::parse('-30 days'),
            updatedBefore: Chronos::parse('+1 minute'),
            provider: 'github',
        );

        $found = $sourceOAuthAccountRepository->findRefreshCandidates($sourceOAuthRefreshCriteria);

        self::assertCount(1, $found);
        self::assertSame($sourceOAuthAccount->getIdentifier()->toString(), $found[0]->getIdentifier()->toString());
    }

    #[Test]
    public function sourceOAuthRefreshCandidatesRespectMinimumRefreshInterval(): void
    {
        $sourceOAuthAccountRepository = $this->sourceOAuthAccountRepository();
        $sourceOAuthAccount           = $this->account('github', refreshToken: 'enc-refresh', tokenExpiresAt: Chronos::parse('+1 hour'));

        $sourceOAuthAccountRepository->save($sourceOAuthAccount);

        $found = $sourceOAuthAccountRepository->findRefreshCandidates(new SourceOAuthRefreshCriteria(
            tokenExpiresBefore: Chronos::parse('+24 hours'),
            lastUsedBefore: Chronos::parse('-30 days'),
            updatedBefore: Chronos::parse('-1 day'),
            provider: 'github',
        ));

        self::assertSame([], $found);
    }

    #[Test]
    public function organizationScopedRefreshCandidatesAreLimitedToThatOrganization(): void
    {
        $sourceOAuthAccountRepository                        = $this->sourceOAuthAccountRepository();
        $providerAuthorizationRepository                     = $this->providerAuthorizationRepository();

        $sourceOAuthAccount = $this->account('github', refreshToken: 'enc-refresh', tokenExpiresAt: Chronos::parse('+10 hours'));
        $sourceOAuthAccountRepository->save($sourceOAuthAccount);
        $providerAuthorizationRepository->save($this->authorization($sourceOAuthAccount->getIdentifier()->toString(), 'org-a'));
        foreach ([1, 2, 3] as $hours) {
            $other = $this->account('github', refreshToken: 'enc-refresh', tokenExpiresAt: Chronos::parse('+' . $hours . ' hours'));
            $sourceOAuthAccountRepository->save($other);
            $providerAuthorizationRepository->save($this->authorization($other->getIdentifier()->toString(), 'org-b'));
        }

        $found = $sourceOAuthAccountRepository->findRefreshCandidates(new SourceOAuthRefreshCriteria(
            tokenExpiresBefore: Chronos::parse('+24 hours'),
            lastUsedBefore: Chronos::parse('-30 days'),
            updatedBefore: Chronos::parse('+1 minute'),
            provider: 'github',
            organizationId: 'org-a',
            limit: 1,
        ));

        self::assertCount(1, $found);
        self::assertSame($sourceOAuthAccount->getIdentifier()->toString(), $found[0]->getIdentifier()->toString());
    }

    #[Test]
    public function providerAuthorizationLookupReturnsOnlyActiveLinkedAuthorizationsInOrganization(): void
    {
        $providerAuthorizationRepository = $this->providerAuthorizationRepository();
        $accountId                       = Uuid::uuid7()->toString();

        $providerAuthorization = $this->authorization($accountId, 'org-1');
        $inactive              = $this->authorization($accountId, 'org-1');
        $inactive->updateStatus('inactive');
        $otherOrganization = $this->authorization($accountId, 'org-2');

        $providerAuthorizationRepository->save($providerAuthorization);
        $providerAuthorizationRepository->save($inactive);
        $providerAuthorizationRepository->save($otherOrganization);

        $found = $providerAuthorizationRepository->findActiveBySourceOAuthAccountId($accountId, 'org-1');

        self::assertCount(1, $found);
        self::assertSame($providerAuthorization->getIdentifier()->toString(), $found[0]->getIdentifier()->toString());
    }

    private function sourceOAuthAccountRepository(): SourceOAuthAccountRepository
    {
        $repository = $this->getService(SourceOAuthAccountRepository::class);
        self::assertInstanceOf(SourceOAuthAccountRepository::class, $repository);

        return $repository;
    }

    private function providerAuthorizationRepository(): ProviderAuthorizationRepository
    {
        $repository = $this->getService(ProviderAuthorizationRepository::class);
        self::assertInstanceOf(ProviderAuthorizationRepository::class, $repository);

        return $repository;
    }

    private function account(string $provider, ?string $refreshToken, ?Chronos $tokenExpiresAt): SourceOAuthAccount
    {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: $provider,
            providerUserId: Uuid::uuid7()->toString(),
            accessToken: 'enc-access',
            refreshToken: $refreshToken,
            tokenExpiresAt: $tokenExpiresAt,
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid7());

        return $sourceOAuthAccount;
    }

    private function authorization(string $sourceOAuthAccountId, string $organizationId): ProviderAuthorization
    {
        $providerAuthorization = ProviderAuthorization::authorizeFromOAuth(
            organizationId: $organizationId,
            provider: 'github',
            sourceOAuthAccountId: $sourceOAuthAccountId,
            authStrategy: 'oauth2',
            configuration: [],
        );
        $providerAuthorization->setIdentifier(Uuid::uuid7());

        return $providerAuthorization;
    }
}
