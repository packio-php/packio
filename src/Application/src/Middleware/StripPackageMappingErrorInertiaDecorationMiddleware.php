<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_filter;
use function array_map;
use function explode;
use function implode;
use function strcasecmp;
use function trim;

final readonly class StripPackageMappingErrorInertiaDecorationMiddleware implements MiddlewareInterface
{
    /**
     * An internal response marker set by the mapping-error responder and consumed
     * before the response leaves the application.
     */
    public const string INERTIA_FALLBACK_RESPONSE_MARKER = 'X-Packio-Inertia-Fallback';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (! $response->hasHeader(self::INERTIA_FALLBACK_RESPONSE_MARKER)) {
            return $response;
        }

        return $this->withoutInertiaDecoration($response);
    }

    private function withoutInertiaDecoration(ResponseInterface $response): ResponseInterface
    {
        $varyTokens = array_filter(
            array_map(trim(...), explode(',', $response->getHeaderLine('Vary'))),
            static fn (string $token): bool => '' !== $token && 0 !== strcasecmp($token, 'X-Inertia'),
        );

        $response = $response
            ->withoutHeader(self::INERTIA_FALLBACK_RESPONSE_MARKER)
            ->withoutHeader('X-Inertia')
            ->withoutHeader('X-Inertia-Version')
            ->withoutHeader('X-Inertia-Location')
            ->withoutHeader('X-Inertia-Redirect')
        ;

        if ([] === $varyTokens) {
            return $response->withoutHeader('Vary');
        }

        return $response->withHeader('Vary', implode(', ', $varyTokens));
    }
}
