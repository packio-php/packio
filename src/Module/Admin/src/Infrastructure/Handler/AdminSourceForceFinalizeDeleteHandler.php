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

use function in_array;
use function is_array;
use function is_scalar;
use function strtolower;
use function trim;

#[Post('/admin/sources/:sourceId/force-finalize-delete', name: 'admin.sources.force-finalize-delete')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminSourceForceFinalizeDeleteHandler implements RequestHandlerInterface
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

        $body = $this->body($request);

        try {
            $message = $this->sourceDeletionRecoveryService->forceFinalizeDeletion(
                $sourceId,
                $adminUserId,
                $this->stringBodyValue($body, 'confirmation'),
                $this->boolBodyValue($body, 'acknowledge_orphaned_archives'),
            );
            $this->inertiaNotificationMessenger->flash($request, 'success', $message);
        } catch (SourceDeletionRecoveryException $exception) {
            // Typed rejection carries a fixed, secret-free message.
            $this->inertiaNotificationMessenger->flash($request, 'error', $exception->getMessage());
        }

        return new RedirectResponse(AdminReturnUrl::from($request, '/admin/sources'));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(ServerRequestInterface $serverRequest): array
    {
        $body = $serverRequest->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function stringBodyValue(array $body, string $key): string
    {
        return is_scalar($body[$key] ?? null) ? trim((string) $body[$key]) : '';
    }

    /**
     * @param array<string, mixed> $body
     */
    private function boolBodyValue(array $body, string $key): bool
    {
        $value = $body[$key] ?? null;
        if (! is_scalar($value)) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
