<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Rest\Infrastructure\Resource\SourceProviderResource;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/source-providers', name: 'api.v1.source-providers.index')]
final readonly class SourceProvidersIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $providers = [];
        foreach ($this->sourceApiFacade->availableProviders() as $sourceProviderDto) {
            $providers[] = SourceProviderResource::fromDto($sourceProviderDto);
        }

        return $this->apiResponseFactory->json([
            'data' => $providers,
        ]);
    }
}
