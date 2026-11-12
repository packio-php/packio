<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Router\RouteResult;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function strtoupper;
use function trim;

final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, mixed> $rateLimitConfig
     */
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private ClientIpResolverInterface $clientIpResolver,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private array $rateLimitConfig,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);

        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            return $handler->handle($request);
        }

        $matchedRoute = $routeResult->getMatchedRoute();
        if (false === $matchedRoute) {
            return $handler->handle($request);
        }

        $options = $matchedRoute->getOptions();
        $rule    = $this->resolveRule($options['rate_limit'] ?? [], $request->getMethod());
        if (null === $rule) {
            return $handler->handle($request);
        }

        // Resolve policy from config
        $policy = $this->resolvePolicy($rule);
        if (null === $policy) {
            return $handler->handle($request);
        }

        $maxAttempts  = $policy['max_attempts'];
        $decaySeconds = $policy['decay_seconds'];

        // Build the subject key
        $limiterKey = $this->buildLimiterKey($rule, $request);
        if (null === $limiterKey) {
            return $handler->handle($request);
        }

        // Check the rate limit
        if ($this->rateLimiter->attempt($limiterKey, $maxAttempts, $decaySeconds)) {
            return $handler->handle($request);
        }

        // Build throttled response
        return $this->buildThrottledResponse($rule, $request, $decaySeconds);
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     *
     * @return null|array<string, mixed>
     */
    private function resolveRule(array $rules, string $httpMethod): ?array
    {
        $normalizedMethod = strtoupper($httpMethod);

        foreach ($rules as $rule) {
            $methods = $rule['methods'] ?? [];
            if ([] !== $methods && ! in_array($normalizedMethod, $methods, true)) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return null|array{max_attempts: int, decay_seconds: int}
     */
    private function resolvePolicy(array $rule): ?array
    {
        $key = $rule['key'] ?? null;
        if (! is_string($key) || '' === $key) {
            return null;
        }

        $policy = $this->rateLimitConfig[$key] ?? null;
        if (! is_array($policy)) {
            return null;
        }

        $maxAttempts  = $policy['max_attempts'] ?? null;
        $decaySeconds = $policy['decay_seconds'] ?? null;

        if (! is_int($maxAttempts) || ! is_int($decaySeconds)) {
            return null;
        }

        return [
            'max_attempts'  => $maxAttempts,
            'decay_seconds' => $decaySeconds,
        ];
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return null|string The rate limiter key (e.g. 'login:ip:127.0.0.1')
     */
    private function buildLimiterKey(array $rule, ServerRequestInterface $serverRequest): ?string
    {
        $key = $rule['key'] ?? '';
        if (! is_string($key) || '' === $key) {
            return null;
        }

        $by = $rule['by'] ?? ['ip'];
        if (! is_array($by)) {
            $by = ['ip'];
        }

        $parts = [$key];

        foreach ($by as $strategy) {
            $resolved = $this->resolveSubjectPart((string) $strategy, $serverRequest);
            if (null === $resolved) {
                return null;
            }

            $parts[] = $resolved;
        }

        return implode(':', $parts);
    }

    /**
     * @return null|string The resolved subject part value
     */
    private function resolveSubjectPart(string $strategy, ServerRequestInterface $serverRequest): ?string
    {
        $parts = explode(':', $strategy, 2);
        $base  = $parts[0];
        $param = $parts[1] ?? null;

        return match ($base) {
            'ip'    => 'ip:' . ($this->clientIpResolver->resolve($serverRequest) ?? 'unknown'),
            'user'  => $this->resolveUserIdentifier($serverRequest),
            'body'  => $this->resolveBodyField($serverRequest, $param),
            'route' => $this->resolveRouteAttribute($serverRequest, $param),
            default => null,
        };
    }

    private function resolveUserIdentifier(ServerRequestInterface $serverRequest): string
    {
        $auth = RequestContextAttributes::currentUserId($serverRequest);
        if (null !== $auth) {
            return 'user:' . $auth;
        }

        // Try common authentication attributes
        $auth = $serverRequest->getAttribute('user_id');
        if (is_string($auth) && '' !== $auth) {
            return 'user:' . $auth;
        }

        $auth = $serverRequest->getAttribute('user-uuid');
        if (is_string($auth) && '' !== $auth) {
            return 'user:' . $auth;
        }

        return 'user:guest';
    }

    private function resolveBodyField(ServerRequestInterface $serverRequest, ?string $field): string
    {
        if (null === $field || '' === $field) {
            return 'body';
        }

        $body  = $serverRequest->getParsedBody();
        $value = is_array($body) && array_key_exists($field, $body) ? $body[$field] : null;

        if (is_string($value) && '' !== trim($value)) {
            return 'body:' . $field . ':' . trim($value);
        }

        return 'body:' . $field;
    }

    private function resolveRouteAttribute(ServerRequestInterface $serverRequest, ?string $attribute): string
    {
        if (null === $attribute || '' === $attribute) {
            return 'route';
        }

        $value = $serverRequest->getAttribute($attribute);

        if (is_string($value) && '' !== trim($value)) {
            return 'route:' . $attribute . ':' . trim($value);
        }

        return 'route:' . $attribute;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function buildThrottledResponse(array $rule, ServerRequestInterface $serverRequest, int $decaySeconds): ResponseInterface
    {
        $responseType = (string) ($rule['response'] ?? 'json');
        $message      = $rule['message'] ?? null;
        $status       = (int) ($rule['status'] ?? 429);
        $redirectTo   = $rule['redirect_to'] ?? null;
        $level        = (string) ($rule['level'] ?? 'error');
        $notify       = (string) ($rule['notify'] ?? 'flash');

        $message = is_string($message) && '' !== $message
            ? $message
            : 'Too many requests. Please try again later.';

        $headers = [
            'Retry-After' => (string) $decaySeconds,
        ];

        return match ($responseType) {
            'redirect' => $this->buildRedirectResponse($serverRequest, $redirectTo, $message, $level, $notify, $headers),
            'empty'    => $this->buildEmptyResponse($status, $headers),
            default    => $this->buildJsonResponse($message, $status, $headers),
        };
    }

    /**
     * @param array<non-empty-string, array<string>|string> $headers
     */
    private function buildJsonResponse(string $message, int $status, array $headers): ResponseInterface
    {
        return new JsonResponse(
            [
                'error'  => $message,
                'status' => $status,
            ],
            $status,
            $headers,
        );
    }

    /**
     * @param array<non-empty-string, array<string>|string> $headers
     */
    private function buildEmptyResponse(int $status, array $headers): ResponseInterface
    {
        $response = new EmptyResponse($status);

        foreach ($headers as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        return $response;
    }

    /**
     * @param array<string, string> $headers
     */
    private function buildRedirectResponse(
        ServerRequestInterface $serverRequest,
        ?string $redirectTo,
        string $message,
        string $level,
        string $notify,
        array $headers,
    ): ResponseInterface {
        $destination = (is_string($redirectTo) && '' !== $redirectTo) ? $redirectTo : '/';

        // Flash message if needed
        if ('none' !== $notify) {
            if ('toast' === $notify) {
                $this->inertiaNotificationMessenger->toast($serverRequest, $level, $message);
            } else {
                $this->inertiaNotificationMessenger->flash($serverRequest, $level, $message);
            }
        }

        $response = new RedirectResponse($destination, 302);

        foreach ($headers as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        return $response;
    }
}
