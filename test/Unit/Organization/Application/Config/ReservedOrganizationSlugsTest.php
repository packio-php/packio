<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Application\Config;

use Packio\Organization\Application\Config\ReservedOrganizationSlugs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_unique;
use function sort;

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

    #[Test]
    public function defaultListCoversServiceSubdomains(): void
    {
        $reservedOrganizationSlugs = new ReservedOrganizationSlugs(ReservedOrganizationSlugs::DEFAULT_SLUGS);

        foreach (['mail', 'support', 'packio', 'autodiscover', 'no-reply', 'Status'] as $slug) {
            self::assertTrue($reservedOrganizationSlugs->contains($slug), $slug);
        }

        self::assertFalse($reservedOrganizationSlugs->contains('acme'));
        self::assertFalse($reservedOrganizationSlugs->contains('mailer-team'));

        $sorted = ReservedOrganizationSlugs::DEFAULT_SLUGS;
        sort($sorted);
        self::assertSame($sorted, ReservedOrganizationSlugs::DEFAULT_SLUGS);
        self::assertSame(array_unique($sorted), $sorted);
    }
}
