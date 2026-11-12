<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Application\Config;

use Packio\Organization\Application\Config\ReservedOrganizationSlugs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservedOrganizationSlugs::class)]
final class ReservedOrganizationSlugsTest extends TestCase
{
    #[Test]
    public function normalizesConfiguredReservedSlugs(): void
    {
        $reservedOrganizationSlugs = new ReservedOrganizationSlugs(['Admin', 'app', 'API.Example', '', 123]);

        self::assertSame(['admin', 'app', 'api-example'], $reservedOrganizationSlugs->all());
        self::assertTrue($reservedOrganizationSlugs->contains('ADMIN'));
        self::assertTrue($reservedOrganizationSlugs->contains('api.example'));
        self::assertFalse($reservedOrganizationSlugs->contains('team-admin'));
    }
}
