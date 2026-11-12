<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/admin/settings', name: 'api.v1.admin.settings.index')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[Can('admin.access')]
final readonly class AdminSettingsIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectSettingsApiFacadeInterface $projectSettingsApiFacade,
        private ApiResponseFactory $apiResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->apiResponseFactory->json([
            'data' => $this->projectSettingsApiFacade->settingsPayload(),
        ]);
    }
}
