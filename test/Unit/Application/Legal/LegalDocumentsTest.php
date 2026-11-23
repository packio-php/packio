<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Legal;

use Common\Markdown\MarkdownToHtmlRenderer;
use Packio\Application\Legal\LegalDocument;
use Packio\Application\Legal\LegalDocuments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function is_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(LegalDocuments::class)]
#[CoversClass(LegalDocument::class)]
final class LegalDocumentsTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'legal');
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    #[Test]
    public function bothOffWhenPathsEmpty(): void
    {
        $legalDocuments = new LegalDocuments('', '  ', new MarkdownToHtmlRenderer());

        self::assertSame([
            'privacy_url' => null,
            'terms_url'   => null,
        ], $legalDocuments->sharedProps());
        self::assertFalse($legalDocuments->isEnabled(LegalDocument::Privacy));
        self::assertFalse($legalDocuments->isEnabled(LegalDocument::Terms));
    }

    #[Test]
    public function onlyTermsEnabled(): void
    {
        $legalDocuments = new LegalDocuments('', $this->file, new MarkdownToHtmlRenderer());

        self::assertSame([
            'privacy_url' => null,
            'terms_url'   => '/terms',
        ], $legalDocuments->sharedProps());
        self::assertTrue($legalDocuments->isEnabled(LegalDocument::Terms));
    }

    #[Test]
    public function rendersMarkdownFromFile(): void
    {
        file_put_contents($this->file, "# Terms\n\nBe nice.");

        $html = new LegalDocuments('', $this->file, new MarkdownToHtmlRenderer())->renderHtml(LegalDocument::Terms);

        self::assertStringContainsString('<h1>Terms</h1>', $html);
        self::assertStringContainsString('<p>Be nice.</p>', $html);
    }

    #[Test]
    public function stripsRawHtml(): void
    {
        file_put_contents($this->file, "# Terms\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>");

        $html = new LegalDocuments($this->file, '', new MarkdownToHtmlRenderer())->renderHtml(LegalDocument::Privacy);

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('onerror', $html);
    }

    #[Test]
    public function readsFileOnEveryCall(): void
    {
        file_put_contents($this->file, '# Terms');
        $legalDocuments = new LegalDocuments('', $this->file, new MarkdownToHtmlRenderer());
        $legalDocuments->renderHtml(LegalDocument::Terms);

        file_put_contents($this->file, '# Changed');

        self::assertStringContainsString('Changed', $legalDocuments->renderHtml(LegalDocument::Terms));
    }

    #[Test]
    public function missingFileThrowsWithPath(): void
    {
        $legalDocuments = new LegalDocuments('', '/nonexistent/terms.md', new MarkdownToHtmlRenderer());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('/nonexistent/terms.md');

        $legalDocuments->renderHtml(LegalDocument::Terms);
    }

    #[Test]
    public function mapsPathsToDocuments(): void
    {
        self::assertSame(LegalDocument::Privacy, LegalDocument::fromPath('/privacy'));
        self::assertSame(LegalDocument::Terms, LegalDocument::fromPath('/terms'));
        self::assertNull(LegalDocument::fromPath('/terms/x'));
        self::assertSame('Privacy Policy', LegalDocument::Privacy->title());
        self::assertSame('Terms of Service', LegalDocument::Terms->title());
    }
}
