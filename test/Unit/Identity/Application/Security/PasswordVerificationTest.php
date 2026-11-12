<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Application\Security;

use Packio\Identity\Application\Security\PasswordVerification;
use PHPUnit\Framework\TestCase;

use function password_hash;

final class PasswordVerificationTest extends TestCase
{
    public function testDummyHashNeverAuthenticatesEvenItsOwnPassword(): void
    {
        self::assertFalse(PasswordVerification::matches('packio-dummy-verification', null));
        self::assertFalse(PasswordVerification::matches('packio-dummy-verification', ''));
    }

    public function testExistingHashStillRequiresCorrectSecret(): void
    {
        $hash = password_hash('correct-secret', PASSWORD_DEFAULT);
        self::assertTrue(PasswordVerification::matches('correct-secret', $hash));
        self::assertFalse(PasswordVerification::matches('wrong-secret', $hash));
    }
}
