<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Cake\Chronos\Chronos;
use Packio\Source\Application\Service\SourceOAuthRefreshOptions;
use Packio\Source\Application\Service\SourceOAuthRefreshPolicy;
use Packio\Source\Application\Service\SourceOAuthRefreshProviderOptions;
use Packio\Source\Application\Service\SourceOAuthRefreshReason;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(SourceOAuthRefreshPolicy::class)]
#[CoversClass(SourceOAuthRefreshReason::class)]
final class SourceOAuthRefreshPolicyTest extends TestCase
{
    #[Test]
    public function criteriaUsesProviderOverridesAndExplicitLimit(): void
    {
        $sourceOAuthRefreshPolicy = new SourceOAuthRefreshPolicy(new SourceOAuthRefreshOptions(
            refreshBefore: '12 hours',
            idleBefore: '20 days',
            limit: 50,
            providers: [
                'github' => new SourceOAuthRefreshProviderOptions(idleBefore: '60 days'),
            ],
        ));

        $criteria = $sourceOAuthRefreshPolicy->criteria(
            provider: 'github',
            organizationId: 'org-1',
            limit: 10,
            now: Chronos::parse('2026-06-13 12:00:00'),
        );

        self::assertSame('github', $criteria->provider);
        self::assertSame('org-1', $criteria->organizationId);
        self::assertSame(10, $criteria->limit);
        self::assertSame('2026-06-14 00:00:00', $criteria->tokenExpiresBefore->toDateTimeString());
        self::assertSame('2026-04-14 12:00:00', $criteria->lastUsedBefore->toDateTimeString());
    }

    #[Test]
    public function reasonPrefersExpiringTokensOverIdleKeepalive(): void
    {
        $sourceOAuthRefreshPolicy = new SourceOAuthRefreshPolicy();
        $criteria                 = $sourceOAuthRefreshPolicy->criteria(now: Chronos::parse('2026-06-13 12:00:00'));

        self::assertSame(
            SourceOAuthRefreshReason::ExpiresSoon,
            $sourceOAuthRefreshPolicy->reasonFor($this->account(tokenExpiresAt: Chronos::parse('2026-06-13 13:00:00')), $criteria),
        );
        self::assertSame(
            SourceOAuthRefreshReason::IdleKeepalive,
            $sourceOAuthRefreshPolicy->reasonFor($this->account(tokenExpiresAt: Chronos::parse('2026-06-20 13:00:00')), $criteria),
        );
    }

    private function account(?Chronos $tokenExpiresAt): SourceOAuthAccount
    {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            accessToken: 'enc-access',
            refreshToken: 'enc-refresh',
            tokenExpiresAt: $tokenExpiresAt,
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid7());

        return $sourceOAuthAccount;
    }
}
