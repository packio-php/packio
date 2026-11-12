<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Application\Security;

use Common\Security\SodiumSecretEncryptor;
use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationInvitationTokens::class)]
final class OrganizationInvitationTokensTest extends TestCase
{
    #[Test]
    public function itEncryptsOnceAndDecryptsBack(): void
    {
        $organizationInvitationTokens = new OrganizationInvitationTokens(new SodiumSecretEncryptor('key-a'));

        $encrypted = $organizationInvitationTokens->encrypt('orgi_plain');

        self::assertStringStartsWith('secretbox:v1:', $encrypted);
        self::assertSame($encrypted, $organizationInvitationTokens->encrypt($encrypted));
        self::assertSame('orgi_plain', $organizationInvitationTokens->decrypt($encrypted));
    }

    #[Test]
    public function itReturnsLegacyPlaintextAsIsAndEmptyForAForeignKey(): void
    {
        $organizationInvitationTokens = new OrganizationInvitationTokens(new SodiumSecretEncryptor('key-a'));

        self::assertSame('orgi_legacy', $organizationInvitationTokens->decrypt('orgi_legacy'));
        self::assertSame('', $organizationInvitationTokens->decrypt(new SodiumSecretEncryptor('key-b')->encrypt('orgi_x')));
    }
}
