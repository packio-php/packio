<?php

declare(strict_types=1);

namespace PackioTest\Unit\Architecture;

use Packio\Identity\Application\Security\ApiAccessTokenScopes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function dirname;
use function file_get_contents;
use function preg_match_all;
use function str_ends_with;

final class ApiScopeInventoryTest extends TestCase
{
    #[Test]
    public function everyRouteScopeIsKnown(): void
    {
        $found = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src', RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! str_ends_with((string) $file, '.php')) {
                continue;
            }

            preg_match_all('/#\[ApiScope\(\s*(?:scope:\s*)?([\'"])([^\'"]+)\1\s*\)\]/', (string) file_get_contents((string) $file), $matches);
            foreach ($matches[2] as $scope) {
                ++$found;
                self::assertTrue(ApiAccessTokenScopes::isKnown($scope), $file . ': ' . $scope);
            }
        }

        self::assertGreaterThan(20, $found);
    }
}
