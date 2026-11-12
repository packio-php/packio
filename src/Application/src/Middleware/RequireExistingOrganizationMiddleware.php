<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RequestPresentationPolicy;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RequireExistingOrganizationMiddleware implements MiddlewareInterface
{
    public function __construct(private RequestPresentationPolicy $requestPresentationPolicy = new RequestPresentationPolicy()) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! RequestContextAttributes::context($request)->currentOrganization instanceof OrganizationDto) {
            if ($this->requestPresentationPolicy->wantsJson($request)) {
                return new JsonResponse([
                    'error' => 'Organization not found.',
                ], 404);
            }

            return new RedirectResponse('/organizations', 302);
        }

        return $handler->handle($request);
    }
}
