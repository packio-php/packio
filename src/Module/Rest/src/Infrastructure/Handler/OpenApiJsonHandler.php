<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Rest\Infrastructure\OpenApi\OpenApiAccessGuard;
use Packio\Rest\Infrastructure\OpenApi\OpenApiSpecGenerator;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/doc.json', name: 'api.doc.json')]
#[Get('/api/v1/doc.json', name: 'api.v1.doc.json')]
final readonly class OpenApiJsonHandler implements RequestHandlerInterface
{
    public function __construct(
        private ApiResponseFactory $apiResponseFactory,
        private OpenApiSpecGenerator $openApiSpecGenerator,
        private OpenApiAccessGuard $openApiAccessGuard,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $denied = $this->openApiAccessGuard->deny($request);
        if ($denied instanceof ResponseInterface) {
            return $denied;
        }

        return $this->apiResponseFactory->json($this->openApiSpecGenerator->generate(
            includeAdmin: $this->openApiAccessGuard->canViewAdminSection($request),
        ));
    }
}
