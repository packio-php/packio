<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Handler\SitemapHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SitemapHandler::class)]
final class SitemapHandlerTest extends TestCase
{
    #[Test]
    public function sitemapUsesInstallationAppUrl(): void
    {
        $brandingConfig = new BrandingConfig([
            'app_url' => 'https://registry.example.com/',
        ]);

        $body = (string) new SitemapHandler($brandingConfig)->handle(new ServerRequest())->getBody();

        self::assertStringContainsString('<loc>https://registry.example.com/</loc>', $body);
        self::assertStringContainsString('<loc>https://registry.example.com/auth/login</loc>', $body);
        self::assertStringNotContainsString('packio.us', $body);
        self::assertStringNotContainsString('//auth', $body);
    }
}
