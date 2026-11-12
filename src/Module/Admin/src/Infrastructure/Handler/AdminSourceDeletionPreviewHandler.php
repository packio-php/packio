<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryService;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/admin/sources/:sourceId/deletion-preview', name: 'admin.sources.deletion-preview')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminSourceDeletionPreviewHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceDeletionRecoveryService $sourceDeletionRecoveryService,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sourceId = RouteAttributes::uuid($request, 'sourceId');
        if ('' === $sourceId) {
            return new JsonResponse([
                'error' => 'Source not found.',
            ], 404);
        }

        $source = $this->sourceApiFacade->findSource($sourceId);
        if (! $source instanceof SourceDto) {
            return new JsonResponse([
                'error' => 'Source not found.',
            ], 404);
        }

        return new JsonResponse($this->sourceDeletionRecoveryService->deletionPreview($source));
    }
}
