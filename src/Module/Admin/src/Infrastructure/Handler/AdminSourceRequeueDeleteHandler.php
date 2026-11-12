<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\Http\AdminReturnUrl;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryException;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryService;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Post('/admin/sources/:sourceId/requeue-delete', name: 'admin.sources.requeue-delete')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminSourceRequeueDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceDeletionRecoveryService $sourceDeletionRecoveryService,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sourceId    = RouteAttributes::uuid($request, 'sourceId');
        $adminUserId = RequestContextAttributes::currentUserId($request);
        if ('' === $sourceId || null === $adminUserId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Source not found.');

            return new RedirectResponse(AdminReturnUrl::from($request, '/admin/sources'));
        }

        try {
            $message = $this->sourceDeletionRecoveryService->requeueDelete($sourceId, $adminUserId);
            $this->inertiaNotificationMessenger->flash($request, 'success', $message);
        } catch (SourceDeletionRecoveryException $exception) {
            // Typed rejection carries a fixed, secret-free message.
            $this->inertiaNotificationMessenger->flash($request, 'error', $exception->getMessage());
        }

        return new RedirectResponse(AdminReturnUrl::from($request, '/admin/sources'));
    }
}
