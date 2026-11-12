<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Exception\ProviderError;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Throwable;

use function array_map;
use function is_string;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get(
    '/organization/:organization/sources/authorizations/:authorizationId/resources',
    name: 'sources.authorizations.resources',
)]
final readonly class SourceAuthorizationResourcesHandler implements RequestHandlerInterface
{
    public function __construct(private SourceApiFacadeInterface $sourceApiFacade, private ?LoggerInterface $logger = null) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organization = RequestContextAttributes::currentOrganization($request);
        if (! $organization instanceof OrganizationDto) {
            return new JsonResponse([
                'error' => 'Organization not found',
            ], 404);
        }

        $authorizationId = RouteAttributes::uuid($request, 'authorizationId');
        if ('' === $authorizationId) {
            return new JsonResponse([
                'error' => 'Authorization not found.',
            ], 404);
        }

        $params   = $request->getQueryParams();
        $provider = is_string($params['provider'] ?? null) ? $params['provider'] : '';

        if ('' === $provider) {
            return new JsonResponse([
                'error' => 'Provider is required.',
            ], 422);
        }

        try {
            $resources = $this->sourceApiFacade->providerResources($provider, $authorizationId, $organization->id);
        } catch (Throwable $exception) {
            $this->logger?->error('Provider resource loading failed.', SafeExceptionContext::forRequest($exception, $request) + [
                'organization_id'  => $organization->id,
                'authorization_id' => SafeExceptionContext::redact($authorizationId),
                'provider'         => SafeExceptionContext::redact($provider),
            ]);
            $error = ProviderError::fromException($exception);

            $authorizationPayload = null;
            if (401 === $error->status) {
                try {
                    $authorization = $this->sourceApiFacade->findProviderAuthorizationInOrganization($authorizationId, $organization->id);
                    if ($authorization instanceof ProviderAuthorizationDto && $authorization->organizationId === $organization->id) {
                        $authorizationPayload = [
                            'id'                      => $authorization->id,
                            'provider'                => $authorization->provider,
                            'auth_strategy'           => $authorization->authStrategy,
                            'status'                  => $authorization->status,
                            'account_name'            => $authorization->accountName,
                            'configured'              => $authorization->configured,
                            'masked_value'            => $authorization->maskedValue,
                            'source_oauth_account_id' => $authorization->sourceOAuthAccountId,
                            'auth_health'             => 'reauthorization_required',
                        ];
                        $authorizationPayload['auth_failure_reason'] = $error->message;
                    }
                } catch (Throwable $cause) {
                    $this->logger?->error('Provider authorization lookup failed.', SafeExceptionContext::forRequest($cause, $request));
                }
            }

            return new JsonResponse([
                'error' => $error->message,
                'code'  => $error->code,
            ] + (401 === $error->status ? [
                'auth_health'         => 'reauthorization_required',
                'auth_failure_reason' => $error->message,
                'authorization'       => $authorizationPayload,
            ] : []), $error->status);
        }

        return new JsonResponse([
            'resources' => array_map(
                static fn ($resource): array => [
                    'id'            => $resource->id,
                    'provider'      => $resource->provider,
                    'name'          => $resource->name,
                    'canonical_url' => $resource->canonicalUrl,
                    'default_ref'   => $resource->defaultRef,
                    'private'       => $resource->private,
                ],
                $resources,
            ),
        ]);
    }
}
