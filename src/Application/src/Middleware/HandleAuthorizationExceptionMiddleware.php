<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Packio\Application\Error\AuthorizationExceptionResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Exception\AuthorizationException;

final readonly class HandleAuthorizationExceptionMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthorizationExceptionResponseFactory $authorizationExceptionResponseFactory) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (AuthorizationException $exception) {
            $response = $this->authorizationExceptionResponseFactory->create($exception, $request);
            if ($response instanceof ResponseInterface) {
                return $response;
            }

            throw $exception;
        }
    }
}
