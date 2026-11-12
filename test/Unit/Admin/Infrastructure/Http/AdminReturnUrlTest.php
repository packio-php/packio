<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Infrastructure\Http;

use Laminas\Diactoros\ServerRequest;
use Packio\Admin\Infrastructure\Http\AdminReturnUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminReturnUrl::class)]
final class AdminReturnUrlTest extends TestCase
{
    #[Test]
    #[DataProvider('returnTargets')]
    public function itReturnsOnlyLocalListUrls(mixed $returnTo, string $expected): void
    {
        $serverRequest = new ServerRequest()->withQueryParams(null === $returnTo ? [] : [
            'return_to' => $returnTo,
        ]);

        self::assertSame($expected, AdminReturnUrl::from($serverRequest, '/admin/sources'));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function returnTargets(): iterable
    {
        yield 'missing'          => [null, '/admin/sources'];

        yield 'exact'            => ['/admin/sources', '/admin/sources'];

        yield 'with query'       => ['/admin/sources?page=2', '/admin/sources?page=2'];

        yield 'prefix sibling'   => ['/admin/sourcesX', '/admin/sources'];

        yield 'absolute url'     => ['https://evil.test/admin/sources', '/admin/sources'];

        yield 'array'            => [['x'], '/admin/sources'];
    }
}
