<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Branding;

use Common\Branding\BrandingOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrandingOptions::class)]
final class BrandingOptionsDefaultsTest extends TestCase
{
    public function testDefaultsDoNotPointAtMaintainerDomain(): void
    {
        self::assertSame('packio.localhost', new BrandingOptions()->domain);
        self::assertSame('packio.localhost', BrandingOptions::fromArray([])->domain);
    }
}
