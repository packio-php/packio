<?php

declare(strict_types=1);

namespace PackioTest\Feature\Application;

use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

final class NotFoundBrandingFeatureTest extends FeatureTestCase
{
    #[Test]
    public function unknownRouteRendersTheConfiguredBrandDomain(): void
    {
        $body = $this->get('/no-such-page-' . __LINE__)->assertStatus(404)->body();

        // The test bootstrap sets BRAND_DOMAIN=packio.us; the template default is packio.localhost.
        self::assertStringContainsString('packio.us', $body);
        self::assertStringNotContainsString('packio.localhost', $body);
    }
}
