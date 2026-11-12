<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Inertia;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\InertiaNotificationException;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

#[CoversClass(InertiaNotificationMessenger::class)]
final class InertiaNotificationMessengerTest extends TestCase
{
    #[Test]
    public function itPublishesTheFullToastQueueAndLatestFlashValueToInertia(): void
    {
        $testInertia                  = new TestInertia();
        $serverRequest                = new ServerRequest()->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia);
        $inertiaNotificationMessenger = new InertiaNotificationMessenger();

        $inertiaNotificationMessenger->flash($serverRequest, 'success', 'Saved successfully.');
        $inertiaNotificationMessenger->toast($serverRequest, 'success', 'Background job queued.', 3000, 'bottom-right');
        $inertiaNotificationMessenger->toast($serverRequest, 'info', 'Import started.');

        self::assertSame([
            'success' => 'Saved successfully.',
            'toasts'  => [
                [
                    'type'     => 'success',
                    'message'  => 'Background job queued.',
                    'duration' => 3000,
                    'position' => 'bottom-right',
                ],
                [
                    'type'     => 'info',
                    'message'  => 'Import started.',
                    'duration' => null,
                    'position' => null,
                ],
            ],
        ], $testInertia->flash);
    }

    #[Test]
    public function itFailsWithoutTheInertiaRequestAttribute(): void
    {
        $this->expectException(InertiaNotificationException::class);

        new InertiaNotificationMessenger()->flash(new ServerRequest(), 'success', 'Saved successfully.');
    }

    #[Test]
    public function itDoesNotReuseToastStateAcrossRequests(): void
    {
        $firstInertia                 = new TestInertia();
        $secondInertia                = new TestInertia();
        $inertiaNotificationMessenger = new InertiaNotificationMessenger();

        $inertiaNotificationMessenger->toast(
            new ServerRequest()->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $firstInertia),
            'success',
            'First request.',
        );
        $inertiaNotificationMessenger->toast(
            new ServerRequest()->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $secondInertia),
            'info',
            'Second request.',
        );

        self::assertSame([[
            'type'     => 'success',
            'message'  => 'First request.',
            'duration' => null,
            'position' => null,
        ]], $firstInertia->flash['toasts']);
        self::assertSame([[
            'type'     => 'info',
            'message'  => 'Second request.',
            'duration' => null,
            'position' => null,
        ]], $secondInertia->flash['toasts']);
    }
}
