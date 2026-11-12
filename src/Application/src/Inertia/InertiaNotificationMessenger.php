<?php

declare(strict_types=1);

namespace Packio\Application\Inertia;

use Psr\Http\Message\ServerRequestInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use WeakMap;

use function in_array;
use function sprintf;

final class InertiaNotificationMessenger
{
    /** @var WeakMap<InertiaInterface, list<array{type: string, message: string, duration: null|int, position: null|string}>> */
    private WeakMap $weakMap;

    public function __construct()
    {
        $this->weakMap = new WeakMap();
    }

    public function flash(ServerRequestInterface $serverRequest, string $type, string $message): void
    {
        if (! in_array($type, ['success', 'error'], true)) {
            throw new InertiaNotificationException(sprintf('Unsupported flash type "%s".', $type));
        }

        $this->inertia($serverRequest)->flash($type, $message);
    }

    public function toast(
        ServerRequestInterface $serverRequest,
        string $type,
        string $message,
        ?int $duration = null,
        ?string $position = null,
    ): void {
        if (! in_array($type, ['success', 'error', 'info', 'warning'], true)) {
            throw new InertiaNotificationException(sprintf('Unsupported toast type "%s".', $type));
        }

        if (null !== $duration && $duration < 0) {
            throw new InertiaNotificationException('Toast duration must be greater than or equal to zero.');
        }

        if (null !== $position && ! in_array($position, ['top-left', 'top-right', 'bottom-left', 'bottom-right'], true)) {
            throw new InertiaNotificationException(sprintf('Unsupported toast position "%s".', $position));
        }

        $inertia  = $this->inertia($serverRequest);
        $toasts   = $this->weakMap[$inertia] ?? [];
        $toasts[] = [
            'type'     => $type,
            'message'  => $message,
            'duration' => $duration,
            'position' => $position,
        ];
        $this->weakMap[$inertia] = $toasts;

        $inertia->flash('toasts', $toasts);
    }

    private function inertia(ServerRequestInterface $serverRequest): InertiaInterface
    {
        $inertia = $serverRequest->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        if (! $inertia instanceof InertiaInterface) {
            throw new InertiaNotificationException('The Inertia request attribute is required to queue notifications.');
        }

        return $inertia;
    }
}
