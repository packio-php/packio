<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Mezzio\Router\RouteResult;
use Mezzio\Session\SessionInterface;
use Packio\Application\Routing\CsrfRouteOptions;
use Packio\Application\Security\Csrf\CsrfTokenContract;
use Packio\Application\Security\Csrf\CsrfTokenManager;
use Packio\Application\Security\Http\TrustedProxyRequestOriginResolver;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

use function count;
use function in_array;
use function is_string;
use function sprintf;
use function strtoupper;

final readonly class CsrfValidationMiddleware implements MiddlewareInterface
{
    private const int EXPECTED_FAILURE_LOG_MAX_ATTEMPTS = 1;

    private const int EXPECTED_FAILURE_LOG_DECAY_SECONDS = 300;

    private const int POLICY_FAILURE_LOG_MAX_ATTEMPTS = 1;

    private const int POLICY_FAILURE_LOG_DECAY_SECONDS = 300;

    public function __construct(
        private CsrfTokenManager $csrfTokenManager,
        private RateLimiterInterface $rateLimiter,
        private ?LoggerInterface $logger = null,
        private TrustedProxyRequestOriginResolver $trustedProxyRequestOriginResolver = new TrustedProxyRequestOriginResolver(),
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ($this->isSafeMethod($method)) {
            return $handler->handle($request);
        }

        $routeResult = $request->getAttribute(RouteResult::class);

        // The router already determined no route matches this request path.
        // Skip CSRF validation so the request reaches the standard not-found
        // handler instead of producing a misleading 419.
        if ($routeResult instanceof RouteResult && ! $routeResult->isSuccess()) {
            return $handler->handle($request);
        }

        try {
            $csrfExempt = CsrfRouteOptions::isExempt($request);
        } catch (UnexpectedValueException) {
            return $this->failure($request, $method, 'invalid_route_policy', true);
        }
        if ($csrfExempt) {
            return $handler->handle($request);
        }

        $originCheck = $this->sameOriginResult($request);
        if ('same' !== $originCheck['result']) {
            return $this->failure(
                $request,
                $method,
                'missing' === $originCheck['result'] ? 'missing_origin' : 'origin_mismatch',
                false,
                $originCheck['source'],
            );
        }

        $session = $request->getAttribute(SessionInterface::class);
        if (! $session instanceof SessionInterface) {
            return $this->failure($request, $method, 'missing_session');
        }

        $tokens = $request->getHeader(CsrfTokenContract::HEADER);
        if ([] === $tokens || (1 === count($tokens) && '' === $tokens[0])) {
            return $this->failure($request, $method, 'missing_token');
        }

        if (1 !== count($tokens) || ! $this->csrfTokenManager->validate($session, $tokens[0])) {
            return $this->failure($request, $method, 'invalid_token');
        }

        return $handler->handle($request);
    }

    private function isSafeMethod(string $method): bool
    {
        return in_array($method, CsrfTokenContract::SAFE_METHODS, true);
    }

    /**
     * @return array{result: 'mismatch'|'missing'|'same', source: 'missing'|'origin'|'referer'}
     */
    private function sameOriginResult(ServerRequestInterface $serverRequest): array
    {
        $origin = $serverRequest->getHeader('Origin');
        if ([] !== $origin) {
            return 1 === count($origin) && $this->trustedProxyRequestOriginResolver->matchesRequestOrigin($serverRequest, $origin[0], false)
                ? [
                    'result' => 'same',
                    'source' => 'origin',
                ]
                : [
                    'result' => 'mismatch',
                    'source' => 'origin',
                ];
        }

        $referer = $serverRequest->getHeader('Referer');
        if ([] !== $referer) {
            return 1 === count($referer) && $this->trustedProxyRequestOriginResolver->matchesRequestOrigin($serverRequest, $referer[0], true)
                ? [
                    'result' => 'same',
                    'source' => 'referer',
                ]
                : [
                    'result' => 'mismatch',
                    'source' => 'referer',
                ];
        }

        return [
            'result' => 'missing',
            'source' => 'missing',
        ];
    }

    private function failure(
        ServerRequestInterface $serverRequest,
        string $method,
        string $reason,
        bool $isInternalPolicyFailure = false,
        ?string $originSource = null,
    ): ResponseInterface {
        $context = [
            'reason'     => $reason,
            'route'      => $this->routeName($serverRequest),
            'method'     => $method,
            'path'       => $this->routePath($serverRequest),
            'request_id' => $this->requestId($serverRequest),
        ];
        if (null !== $originSource) {
            $context['origin_source'] = $originSource;
        }

        if (! $this->logger instanceof LoggerInterface) {
            return new EmptyResponse(419);
        }

        if ($isInternalPolicyFailure && $this->rateLimiter->attempt(
            $this->policyFailureLogKey($context['route']),
            self::POLICY_FAILURE_LOG_MAX_ATTEMPTS,
            self::POLICY_FAILURE_LOG_DECAY_SECONDS,
        )) {
            $this->logger->error('CSRF validation failed', $context);
        }

        if (! $isInternalPolicyFailure && $this->rateLimiter->attempt(
            $this->expectedFailureLogKey($reason, $context['route']),
            self::EXPECTED_FAILURE_LOG_MAX_ATTEMPTS,
            self::EXPECTED_FAILURE_LOG_DECAY_SECONDS,
        )) {
            $this->logger->notice('CSRF validation failed', $context);
        }

        return new EmptyResponse(419);
    }

    private function policyFailureLogKey(?string $routeName): string
    {
        return sprintf('csrf_route_policy:%s', $routeName ?? 'unmatched');
    }

    private function expectedFailureLogKey(string $reason, ?string $routeName): string
    {
        return sprintf('csrf_validation:%s:%s', $reason, $routeName ?? 'unmatched');
    }

    private function routeName(ServerRequestInterface $serverRequest): ?string
    {
        $routeResult = $serverRequest->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            return null;
        }

        $route = $routeResult->getMatchedRoute();

        return false === $route ? null : $route->getName();
    }

    private function routePath(ServerRequestInterface $serverRequest): ?string
    {
        $routeResult = $serverRequest->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            return null;
        }

        $route = $routeResult->getMatchedRoute();

        return false === $route ? null : $route->getPath();
    }

    private function requestId(ServerRequestInterface $serverRequest): ?string
    {
        $requestId = $serverRequest->getAttribute('request_id');

        return is_string($requestId) && '' !== $requestId ? $requestId : null;
    }
}
