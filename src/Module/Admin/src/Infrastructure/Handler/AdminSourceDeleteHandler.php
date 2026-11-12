<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\Http\AdminReturnUrl;
use Packio\Admin\Infrastructure\Request\AdminSourceDeleteRequest;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryException;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryService;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

use function is_string;

#[Post('/admin/sources/:sourceId/delete', name: 'admin.sources.delete')]
#[Authenticated]
#[Can('admin.access')]
#[MapRequest(body: AdminSourceDeleteRequest::class)]
#[ValidateMappedRequest(AdminSourceDeleteRequest::class)]
#[ValidationFailed(
    redirectTo: '/admin/sources',
    message: 'Invalid request data.',
    notify: ValidationNotify::Flash,
)]
final readonly class AdminSourceDeleteHandler implements RequestHandlerInterface
{
    private const string ACTION_ORIGIN = 'admin-source-delete';

    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceDeletionRecoveryService $sourceDeletionRecoveryService,
        private LoggerInterface $logger,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sourceId    = RouteAttributes::uuid($request, 'sourceId');
        $adminUserId = RequestContextAttributes::currentUserId($request);
        if ('' === $sourceId || null === $adminUserId) {
            $rawId = $request->getAttribute('sourceId');
            $this->reject($request, 'invalid_request', is_string($rawId) ? $rawId : '', 'Source not found.');

            return new RedirectResponse(AdminReturnUrl::from($request, '/admin/sources'));
        }

        $source = $this->sourceApiFacade->findSource($sourceId);
        if (! $source instanceof SourceDto) {
            $this->reject($request, 'not_found', $sourceId, 'Source not found.');

            return new RedirectResponse(AdminReturnUrl::from($request, '/admin/sources'));
        }

        /** @var AdminSourceDeleteRequest $form */
        $form = $request->getAttribute(AdminSourceDeleteRequest::class);
        // Business confirmation depends on the resolved source, not only on the mapped DTO.
        if ($form->confirmationName !== $this->expectedName($source)) {
            $this->reject($request, 'confirmation_mismatch', $sourceId, 'Type the exact source name to confirm deletion.');

            return new RedirectResponse(AdminReturnUrl::from($request, '/admin/sources'));
        }

        try {
            $message = $this->sourceDeletionRecoveryService->initiateDeletion($sourceId, $adminUserId);
            $this->inertiaNotificationMessenger->flash($request, 'success', $message);
        } catch (SourceDeletionRecoveryException $exception) {
            // Typed rejection carries a fixed, secret-free message.
            $this->inertiaNotificationMessenger->flash($request, 'error', $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->error('Admin source deletion failed.', SafeExceptionContext::forRequest($exception, $request));
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Unable to delete source.');
        }

        return new RedirectResponse(AdminReturnUrl::from($request, '/admin/sources'));
    }

    private function expectedName(SourceDto $sourceDto): string
    {
        return '' !== $sourceDto->displayName
            ? $sourceDto->displayName
            : $sourceDto->owner . '/' . $sourceDto->repository;
    }

    private function reject(
        ServerRequestInterface $serverRequest,
        string $reason,
        string $sourceId,
        string $message,
    ): void {
        $this->logger->warning('Admin source deletion request was rejected.', [
            'admin_user_id' => RequestContextAttributes::currentUserId($serverRequest) ?? '',
            'source_id'     => $sourceId,
            'reason'        => $reason,
            'origin'        => self::ACTION_ORIGIN,
        ]);

        $this->inertiaNotificationMessenger->flash($serverRequest, 'error', $message);
    }
}
