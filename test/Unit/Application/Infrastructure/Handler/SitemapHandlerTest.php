<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Common\Markdown\MarkdownToHtmlRenderer;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Handler\SitemapHandler;
use Packio\Application\Legal\LegalDocuments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SitemapHandler::class)]
final class SitemapHandlerTest extends TestCase
{
    #[Test]
    public function sitemapUsesInstallationAppUrl(): void
    {
        $body = $this->sitemap(new LegalDocuments('', '', new MarkdownToHtmlRenderer()));

        self::assertStringContainsString('<loc>https://registry.example.com/</loc>', $body);
        self::assertStringContainsString('<loc>https://registry.example.com/auth/login</loc>', $body);
        self::assertStringNotContainsString('packio.us', $body);
        self::assertStringNotContainsString('//auth', $body);
        self::assertStringNotContainsString('/terms</loc>', $body);
        self::assertStringNotContainsString('/privacy</loc>', $body);
    }

    #[Test]
    public function includesEnabledLegalPages(): void
    {
        $body = $this->sitemap(new LegalDocuments('', '/srv/legal/terms.md', new MarkdownToHtmlRenderer()));

        self::assertStringContainsString(
            "<loc>https://registry.example.com/terms</loc>\n    <changefreq>yearly</changefreq>\n    <priority>0.2</priority>",
            $body,
        );
        self::assertStringNotContainsString('/privacy</loc>', $body);
    }

    private function sitemap(LegalDocuments $legalDocuments): string
    {
        $brandingConfig = new BrandingConfig([
            'app_url' => 'https://registry.example.com/',
        ]);

        return (string) new SitemapHandler($brandingConfig, $legalDocuments)->handle(new ServerRequest())->getBody();
    }
}
