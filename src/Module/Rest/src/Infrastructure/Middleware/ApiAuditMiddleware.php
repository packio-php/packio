<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Middleware;

use Common\Logging\SafeExceptionContext;
use Mezzio\Router\RouteResult;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Rest\Infrastructure\Http\ApiRequestAttributes;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function in_array;
use function is_string;
use function sprintf;
use function str_starts_with;
use function trim;

final readonly class ApiAuditMiddleware implements MiddlewareInterface
{
    private const array WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private AuditApiFacadeInterface $auditApiFacade,
        private LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (! in_array($request->getMethod(), self::WRITE_METHODS, true) || $response->getStatusCode() >= 400) {
            return $response;
        }

        $organization   = RequestContextAttributes::currentOrganization($request);
        $organizationId = $organization instanceof OrganizationDto
            ? $organization->id
            : $this->routeOrganizationId($request);

        try {
            $this->auditApiFacade->record(
                $this->eventName($request),
                $this->context($request, $response, $organizationId),
                $organizationId,
                $this->sourceId($request),
                $this->packageName($request),
                RequestContextAttributes::currentUserId($request) ?? '',
            );
        } catch (Throwable $exception) {
            // The write already happened; a 500 would make the client retry it.
            $this->logger->error('API audit record failed.', SafeExceptionContext::forRequest($exception, $request));
        }

        return $response;
    }

    private function eventName(ServerRequestInterface $serverRequest): string
    {
        $routeName = $this->routeName($serverRequest);
        if ('' === $routeName) {
            return 'api.request';
        }

        return str_starts_with($routeName, 'api.') ? $routeName : 'api.' . $routeName;
    }

    /**
     * @return array<string, mixed>
     */
    private function context(ServerRequestInterface $serverRequest, ResponseInterface $response, string $organizationId): array
    {
        $apiAccessToken = $serverRequest->getAttribute(ApiRequestAttributes::ACCESS_TOKEN);
        $sourceId       = $this->sourceId($serverRequest);
        $packageName    = $this->packageName($serverRequest);

        $context = [
            'method'       => $serverRequest->getMethod(),
            'route'        => $this->routeName($serverRequest),
            'path'         => $serverRequest->getUri()->getPath(),
            'status'       => $response->getStatusCode(),
            'user_id'      => RequestContextAttributes::currentUserId($serverRequest),
            'api_token_id' => $apiAccessToken instanceof ApiAccessTokenDto ? $apiAccessToken->id : null,
            'route_params' => $this->routeParameters($serverRequest),
        ];

        if ('' !== $organizationId) {
            $context['organization_id'] = $organizationId;
        }

        if ('' !== $sourceId) {
            $context['source_id'] = $sourceId;
        }

        if ('' !== $packageName) {
            $context['package_name'] = $packageName;
        }

        return $context;
    }

    private function routeName(ServerRequestInterface $serverRequest): string
    {
        $routeResult = $serverRequest->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            return '';
        }

        $route = $routeResult->getMatchedRoute();
        if (false === $route) {
            return '';
        }

        return $route->getName();
    }

    private function sourceId(ServerRequestInterface $serverRequest): string
    {
        return $this->canonicalOrRaw($serverRequest, 'sourceId');
    }

    private function packageName(ServerRequestInterface $serverRequest): string
    {
        $vendor  = $serverRequest->getAttribute('vendor');
        $package = $serverRequest->getAttribute('package');

        return is_string($vendor) && is_string($package)
            ? sprintf('%s/%s', trim($vendor), trim($package))
            : '';
    }

    private function routeOrganizationId(ServerRequestInterface $serverRequest): string
    {
        return $this->canonicalOrRaw($serverRequest, 'organizationId');
    }

    // a non-UUID value is still written raw so the audit entry is not lost
    private function canonicalOrRaw(ServerRequestInterface $serverRequest, string $attribute): string
    {
        $uuid = ApiRouteResourceResolver::uuid($serverRequest, $attribute);

        return '' !== $uuid ? $uuid : ApiRouteResourceResolver::routeString($serverRequest, $attribute);
    }

    /**
     * @return array<string, string>
     */
    private function routeParameters(ServerRequestInterface $serverRequest): array
    {
        $routeResult = $serverRequest->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            return [];
        }

        $safeParameters = [
            'authorizationId',
            'deliveryId',
            'instanceId',
            'invitationId',
            'jobId',
            'memberId',
            'organization',
            'organizationId',
            'package',
            'sourceId',
            'tokenId',
            'userId',
            'vendor',
        ];
        $parameters     = [];

        foreach ($routeResult->getMatchedParams() as $name => $value) {
            if (! in_array($name, $safeParameters, true)) {
                continue;
            }

            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ('' !== $value) {
                $parameters[$name] = $value;
            }
        }

        return $parameters;
    }
}
