<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Domain\Entity;

use Packio\Source\Domain\Entity\SourceOAuthAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceOAuthAccount::class)]
final class SourceOAuthAccountTest extends TestCase
{
    #[Test]
    public function markAsUsedDoesNotReactivateInactiveAccount(): void
    {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            accessToken: 'enc_access_token',
        );
        $sourceOAuthAccount->markAsInactive();

        $sourceOAuthAccount->markAsUsed();

        self::assertFalse($sourceOAuthAccount->isActive());
        self::assertNotNull($sourceOAuthAccount->lastUsedAt());
    }
}
