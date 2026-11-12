<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Service;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Service\InitialListViewModeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InitialListViewModeResolver::class)]
final class InitialListViewModeResolverTest extends TestCase
{
    #[Test]
    public function resolvesValidCookieValue(): void
    {
        $initialListViewModeResolver = new InitialListViewModeResolver();
        $serverRequest               = new ServerRequest()->withCookieParams([
            'packio-list-view-mode' => 'table',
        ]);

        self::assertSame('table', $initialListViewModeResolver->resolve($serverRequest, 'packio-list-view-mode'));
    }

    #[Test]
    public function defaultsToGridWhenCookieIsMissingOrInvalid(): void
    {
        $initialListViewModeResolver = new InitialListViewModeResolver();

        self::assertSame('grid', $initialListViewModeResolver->resolve(new ServerRequest(), 'packio-list-view-mode'));

        $serverRequest = new ServerRequest()->withCookieParams([
            'packio-list-view-mode' => 'cards',
        ]);

        self::assertSame('grid', $initialListViewModeResolver->resolve($serverRequest, 'packio-list-view-mode'));
    }

    #[Test]
    public function allowsCustomDefaultMode(): void
    {
        $initialListViewModeResolver = new InitialListViewModeResolver();

        self::assertSame(
            'table',
            $initialListViewModeResolver->resolve(new ServerRequest(), 'packio-admin-packages-view-mode', 'table'),
        );
    }
}
