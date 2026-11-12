<?php

declare(strict_types=1);

namespace PackioTest\Integration\Application;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function preg_match;
use function str_ends_with;
use function str_replace;

final class AuthenticationManagerInventoryTest extends TestCase
{
    #[Test]
    public function applicationCodeCannotBypassTheDecoratedAuthenticationManagerThroughProfiles(): void
    {
        $violations = [];
        $sourceRoot = dirname(__DIR__, 3) . '/src';
        $files      = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (false !== $contents && 1 === preg_match('/->manager\s*\(/', $contents)) {
                $path         = str_replace($sourceRoot . '/', '', $file->getPathname());
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations, 'Inject AuthManagerInterface instead of obtaining a manager from an authentication profile.');
    }
}
