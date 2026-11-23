<?php

declare(strict_types=1);

namespace PackioTest\Feature\Application;

use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function file_put_contents;
use function getenv;
use function putenv;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class LegalPagesFeatureTest extends FeatureTestCase
{
    private const array INERTIA_HEADERS = [
        'Accept'    => 'application/json',
        'X-Inertia' => 'true',
    ];

    private false|string $previousPrivacyPath;
    private false|string $previousTermsPath;
    private string $termsFile;

    protected function setUp(): void
    {
        $this->termsFile = (string) tempnam(sys_get_temp_dir(), 'legal');
        file_put_contents($this->termsFile, "# Terms\n\nBe nice.");

        $this->previousPrivacyPath = getenv('PRIVACY_POLICY_PATH');
        $this->previousTermsPath   = getenv('TERMS_OF_SERVICE_PATH');
        putenv('PRIVACY_POLICY_PATH=');
        putenv('TERMS_OF_SERVICE_PATH=' . $this->termsFile);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('PRIVACY_POLICY_PATH', $this->previousPrivacyPath);
        $this->restoreEnv('TERMS_OF_SERVICE_PATH', $this->previousTermsPath);
        unlink($this->termsFile);

        parent::tearDown();
    }

    #[Test]
    public function termsPageRenders(): void
    {
        $this->get('/terms', self::INERTIA_HEADERS)
            ->assertStatus(200)
            ->assertInertiaComponent('Legal/Show')
            ->assertInertiaProp('title', 'Terms of Service')
            ->assertInertiaProp('html', "<h1>Terms</h1>\n<p>Be nice.</p>")
        ;
    }

    #[Test]
    public function privacyPageIs404WhenOff(): void
    {
        $this->get('/privacy')->assertStatus(404);
        $this->get('/privacy', self::INERTIA_HEADERS)->assertStatus(404);
    }

    #[Test]
    public function sharedPropsCarryEnabledUrls(): void
    {
        $this->get('/auth/login', self::INERTIA_HEADERS)
            ->assertStatus(200)
            ->assertInertiaProp('legal', [
                'privacy_url' => null,
                'terms_url'   => '/terms',
            ])
        ;
    }

    #[Test]
    public function sitemapListsOnlyEnabledPages(): void
    {
        $body = $this->get('/sitemap.xml')->assertStatus(200)->body();

        self::assertStringContainsString('/terms</loc>', $body);
        self::assertStringNotContainsString('/privacy</loc>', $body);
    }

    private function restoreEnv(string $name, false|string $previous): void
    {
        putenv(false === $previous ? $name : $name . '=' . $previous);
    }
}
