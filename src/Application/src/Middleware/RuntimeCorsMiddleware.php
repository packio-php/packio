<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Mezzio\Cors\Middleware\CorsMiddleware;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Application\Service\CorsOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function trim;

final readonly class RuntimeCorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CorsMiddleware $corsMiddleware,
        private ProjectSettingsApiFacadeInterface $projectSettingsApiFacade,
        private CorsOptions $corsOptions,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $request->hasHeader('Origin') || '' === trim($request->getHeaderLine('Origin'))) {
            return $handler->handle($request);
        }

        $enabled = $this->projectSettingsApiFacade->booleanValue(
            $this->corsOptions->runtimeSettingKey,
            $this->corsOptions->enabledFallback,
        );

        if (! $enabled) {
            return $handler->handle($request);
        }

        return $this->corsMiddleware->process($request, $handler);
    }
}
