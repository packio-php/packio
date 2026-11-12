<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Http;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiPathMatcher::class)]
final class ApiPathMatcherTest extends TestCase
{
    #[Test]
    #[DataProvider('apiPaths')]
    public function matchesOnlyApiPathPrefix(string $path, bool $expected): void
    {
        self::assertSame($expected, ApiPathMatcher::isApiPath($path));
        self::assertSame($expected, ApiPathMatcher::isApiRequest(new ServerRequest(uri: $path)));
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function apiPaths(): array
    {
        return [
            ['/api', true],
            ['/api/', true],
            ['/api/v1/me', true],
            ['/api/doc.json', true],
            ['/apix', false],
            ['/organization/api', false],
            ['/', false],
        ];
    }
}
