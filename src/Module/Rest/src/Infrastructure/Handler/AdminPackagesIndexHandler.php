<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\PackageResource;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/admin/packages', name: 'api.v1.admin.packages.index')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[Can('admin.access')]
final readonly class AdminPackagesIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private ApiResponseFactory $apiResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $packages = [];
        foreach ($this->packageApiFacade->allPackages() as $packageDto) {
            $packages[] = PackageResource::fromDto($packageDto);
        }

        return $this->apiResponseFactory->json([
            'data' => $packages,
        ]);
    }
}
