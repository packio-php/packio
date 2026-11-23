<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Markdown;

use Common\Markdown\MarkdownToHtmlRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MarkdownToHtmlRendererTest extends TestCase
{
    #[Test]
    public function rendersGfmTable(): void
    {
        $html = new MarkdownToHtmlRenderer()->render("| a | b |\n|---|---|\n| 1 | 2 |");

        self::assertStringContainsString('<table>', $html);
    }

    #[Test]
    public function stripsRawHtml(): void
    {
        $html = new MarkdownToHtmlRenderer()->render("<script>alert(1)</script>\n\nHi <img src=x onerror=alert(1)>");

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('onerror', $html);
    }

    #[Test]
    public function dropsUnsafeLinks(): void
    {
        $html = new MarkdownToHtmlRenderer()->render('[x](javascript:alert(1))');

        self::assertStringNotContainsString('javascript:', $html);
    }
}
