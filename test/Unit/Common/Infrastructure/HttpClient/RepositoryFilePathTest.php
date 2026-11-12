<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\RepositoryFilePath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepositoryFilePathTest extends TestCase
{
    public function testNormalizesAndEncodesEachSegment(): void
    {
        self::assertSame('composer.json', RepositoryFilePath::normalize(''));
        self::assertSame('packages/my package/composer.json', RepositoryFilePath::normalize(' packages/my package/composer.json '));
        self::assertSame('packages/my%20package/composer.json', RepositoryFilePath::urlPath('packages/my package/composer.json'));
    }

    #[DataProvider('unsafePaths')]
    public function testRejectsUnsafePaths(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        RepositoryFilePath::normalize($path);
    }

    /** @return iterable<array{string}> */
    public static function unsafePaths(): iterable
    {
        foreach (["\n", '../composer.json', 'a/../composer.json', './composer.json', '/composer.json', 'a//composer.json', 'a/', 'a\composer.json', 'a?token=x', 'a#fragment', '%2e%2e/composer.json', '%252e%252e/composer.json', "a\n.json", 'https://example.com/a', 'C:/composer.json'] as $path) {
            yield [$path];
        }
    }
}
