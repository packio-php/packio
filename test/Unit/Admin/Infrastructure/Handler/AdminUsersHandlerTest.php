<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Admin\Infrastructure\Handler\AdminUsersHandler;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Api\Dto\UserDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;

#[CoversClass(AdminUsersHandler::class)]
final class AdminUsersHandlerTest extends TestCase
{
    #[Test]
    public function listExposesLastSignInFromOneFacadeCall(): void
    {
        $facade = $this->createMock(IdentityApiFacadeInterface::class);
        $facade->method('findUsersPaginated')->willReturn(new PaginatedResultDto([
            new UserDto('u-1', 'a@example.test', 'A', ['user'], '2026-11-01T10:00:00+00:00'),
            new UserDto('u-2', 'b@example.test', 'B', ['user'], '2026-11-01T10:00:00+00:00'),
        ], 2, 1, 20));
        $facade->expects(self::once())->method('lastSignInAtByUserIds')->with(['u-1', 'u-2'])->willReturn([
            'u-1' => '2026-11-22T10:00:00+00:00',
        ]);

        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->expects(self::once())->method('render')->with(
            'Admin/Users',
            self::callback(static function(array $props): bool {
                self::assertSame('2026-11-22T10:00:00+00:00', $props['users'][0]['last_sign_in_at']);
                self::assertNull($props['users'][1]['last_sign_in_at']);

                return true;
            }),
        )->willReturn(new EmptyResponse());

        new AdminUsersHandler($facade)->handle(
            new ServerRequest()->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia),
        );
    }

    #[Test]
    public function emptyPageSkipsTheLastSignInLookup(): void
    {
        $facade = $this->createMock(IdentityApiFacadeInterface::class);
        $facade->method('findUsersPaginated')->willReturn(new PaginatedResultDto([], 0, 1, 20));
        $facade->expects(self::never())->method('lastSignInAtByUserIds');

        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->expects(self::once())->method('render')->with(
            'Admin/Users',
            self::callback(static function(array $props): bool {
                self::assertSame([], $props['users']);

                return true;
            }),
        )->willReturn(new EmptyResponse());

        new AdminUsersHandler($facade)->handle(
            new ServerRequest()->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia),
        );
    }
}
