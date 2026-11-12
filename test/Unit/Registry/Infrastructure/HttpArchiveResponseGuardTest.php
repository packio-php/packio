<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure;

use Packio\Registry\Infrastructure\HttpArchiveResponseGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HttpArchiveResponseGuard::class)]
final class HttpArchiveResponseGuardTest extends TestCase
{
    #[Test]
    public function itAcceptsSuccessfulHttpResponses(): void
    {
        $exception = null;

        try {
            HttpArchiveResponseGuard::assertSuccessful('https://example.test/archive.zip', [
                'HTTP/2 200',
                'content-type: application/zip',
            ]);
        } catch (RuntimeException $exception) {
        }

        self::assertNull($exception);
    }

    #[Test]
    public function itAcceptsRedirectedSuccessfulHttpResponses(): void
    {
        $exception = null;

        try {
            HttpArchiveResponseGuard::assertSuccessful('https://example.test/archive.zip', [
                'HTTP/2 302',
                'HTTP/2 200',
                'content-type: application/zip',
            ]);
        } catch (RuntimeException $exception) {
        }

        self::assertNull($exception);
    }

    #[Test]
    public function itRejectsUpstreamErrorResponses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('upstream returned HTTP 404');

        HttpArchiveResponseGuard::assertSuccessful('https://example.test/archive.zip', [
            'HTTP/2 404',
            'content-type: application/json',
        ]);
    }

    #[Test]
    public function itRejectsResponsesWithoutStatusLine(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('upstream returned HTTP unknown');

        HttpArchiveResponseGuard::assertSuccessful('https://example.test/archive.zip', [
            'content-type: application/zip',
        ]);
    }
}
