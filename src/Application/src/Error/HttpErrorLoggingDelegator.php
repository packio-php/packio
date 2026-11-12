<?php

declare(strict_types=1);

namespace Packio\Application\Error;

use Common\Logging\SafeExceptionContext;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Exception\AlreadyAuthenticatedException;
use Sirix\Mezzio\Authentication\Exception\AuthenticationException;
use Throwable;
use UnexpectedValueException;

final class HttpErrorLoggingDelegator
{
    public function __invoke(ContainerInterface $container, string $serviceName, callable $callback): ErrorHandler
    {
        unset($serviceName);

        $errorHandler = $callback();
        if (! $errorHandler instanceof ErrorHandler) {
            throw new UnexpectedValueException('HTTP error logging delegator expects an ErrorHandler service.');
        }

        $logger = $container->get(LoggerInterface::class);
        if (! $logger instanceof LoggerInterface) {
            throw new UnexpectedValueException('HTTP error logging delegator expects a LoggerInterface service.');
        }

        $errorHandler->attachListener(
            static function(Throwable $throwable, ServerRequestInterface $serverRequest, ResponseInterface $response) use ($logger): void {
                // Expected login redirects; Sentry ignores them too.
                if ($throwable instanceof AuthenticationException || $throwable instanceof AlreadyAuthenticatedException) {
                    return;
                }

                $logger->error('Uncaught HTTP exception', [
                    'status' => $response->getStatusCode(),
                    'method' => $serverRequest->getMethod(),
                    'path'   => SafeExceptionContext::redactPath($serverRequest->getUri()->getPath()),
                    ...SafeExceptionContext::forRequest($throwable, $serverRequest),
                ]);
            },
        );

        return $errorHandler;
    }
}
