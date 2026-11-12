<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Packio\Identity\Infrastructure\Handler\SourceOAuthPopupRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function preg_match;
use function substr_count;

#[CoversClass(SourceOAuthPopupRenderer::class)]
final class SourceOAuthPopupRendererTest extends TestCase
{
    #[Test]
    public function payloadCannotBreakOutOfScriptAndIsSentOnlyToTheSameOrigin(): void
    {
        $attack   = '</script><script>alert("x" & \'y\')</script>';
        $response = new SourceOAuthPopupRenderer()->render(false, $attack, $attack, $attack);
        $html     = (string) $response->getBody();
        self::assertStringNotContainsString($attack, $html);
        self::assertSame(1, substr_count($html, '</script>'));
        self::assertStringContainsString('\u003C', $html);
        self::assertStringContainsString('\u0026', $html);
        self::assertStringContainsString('\u0027', $html);
        self::assertStringContainsString('\u0022', $html);
        self::assertStringContainsString(', window.location.origin); window.close();', $html);
        if (1 !== preg_match('/postMessage\((.*), window.location.origin\)/', $html, $matches)) {
            self::fail('Popup must post a JSON payload to its opener.');
        }
        $payload = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($attack, $payload['provider']);
        self::assertSame($attack, $payload['sourceOAuthAccountId']);
        self::assertSame($attack, $payload['error']);
        self::assertFalse($payload['success']);
    }
}
