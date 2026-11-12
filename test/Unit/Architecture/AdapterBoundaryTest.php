<?php

declare(strict_types=1);

namespace PackioTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function preg_match;
use function str_contains;

final class AdapterBoundaryTest extends TestCase
{
    public function testHttpHandlersHaveNoDomainRepositoryDependencies(): void
    {
        foreach ($this->sources() as $path => $source) {
            if (str_contains($path, '/Infrastructure/Handler/')) {
                self::assertDoesNotMatchRegularExpression('/use\s+[^;]+\\\Domain\\\Repository\\\/', $source, $path);
            }
        }
    }

    public function testApplicationDoesNotImplementWorkerHandlers(): void
    {
        foreach ($this->sources() as $path => $source) {
            if (str_contains($path, '/Application/')) {
                self::assertDoesNotMatchRegularExpression('/implements\s+[^;{]*JobHandlerInterface/', $source, $path);
            }
        }
    }

    public function testWebhookApplicationDoesNotParseTransport(): void
    {
        foreach ($this->sources() as $path => $source) {
            if (str_contains($path, '/Webhook/src/Application/')) {
                self::assertStringNotContainsString('Psr\Http\\', $source, $path);
                self::assertDoesNotMatchRegularExpression('/\$headers|json_decode|hash_hmac|WebhookEnvelope/', $source, $path);
            }
        }
    }

    public function testPortsLiveInApplicationPortAndAreImplementedByAdapters(): void
    {
        $adapters = 0;
        foreach ($this->sources() as $path => $source) {
            self::assertSame(str_contains($path, '/Application/Port/'), 1 === preg_match('/^interface\s+\w+Port\b/m', $source), $path);

            if (1 === preg_match('/^(?:final\s+|readonly\s+|abstract\s+)*class\s+\w+[^{]*?\bimplements\b([^{]+)/m', $source, $match) && 1 === preg_match('/\b\w+Port\b/', $match[1])) {
                self::assertStringContainsString('/Infrastructure/Adapter/', $path, $path);
                ++$adapters;
            }
        }

        self::assertGreaterThan(0, $adapters);
    }

    /** @return iterable<string, string> */
    private function sources(): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src/Module'));
        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);

            yield $file->getPathname() => $source;
        }
    }
}
