<?php

declare(strict_types=1);

namespace PackioTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function str_contains;

final class HandlerErrorBoundaryTest extends TestCase
{
    public function testHandlersDoNotClassifyExceptionsByMessage(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src'));
        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || ! str_contains($file->getPathname(), '/Infrastructure/Handler/')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression('/str_contains\s*\([^;]*?->getMessage\s*\(/s', $source, $file->getPathname());
        }
    }
}
