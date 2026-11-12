<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Response;

use Packio\Source\Api\Dto\SourceProviderInstanceDependenciesDto;
use Packio\Source\Api\Dto\SourceProviderInstanceDto;

use function array_map;

final readonly class SourceProviderInstancePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(SourceProviderInstanceDto $sourceProviderInstanceDto): array
    {
        return [
            'id'                => $sourceProviderInstanceDto->id,
            'organization_id'   => $sourceProviderInstanceDto->organizationId,
            'provider_type'     => $sourceProviderInstanceDto->providerType,
            'label'             => $sourceProviderInstanceDto->label,
            'base_url'          => $sourceProviderInstanceDto->baseUrl,
            'client_id'         => $sourceProviderInstanceDto->clientId,
            'has_client_secret' => $sourceProviderInstanceDto->hasClientSecret,
            'auth_mode'         => $sourceProviderInstanceDto->authMode,
            'status'            => $sourceProviderInstanceDto->status,
            'metadata'          => $sourceProviderInstanceDto->metadata,
        ];
    }

    /** @return array<string, mixed> */
    public function dependencies(SourceProviderInstanceDependenciesDto $sourceProviderInstanceDependenciesDto): array
    {
        return [
            'instance'                     => [
                'id'            => $sourceProviderInstanceDependenciesDto->instanceId,
                'label'         => $sourceProviderInstanceDependenciesDto->label,
                'base_url'      => $sourceProviderInstanceDependenciesDto->baseUrl,
                'provider_type' => $sourceProviderInstanceDependenciesDto->providerType,
            ],
            'source_count'                 => $sourceProviderInstanceDependenciesDto->sourceCount(),
            'authorization_count'          => $sourceProviderInstanceDependenciesDto->authorizationCount(),
            'active_authorization_count'   => $sourceProviderInstanceDependenciesDto->activeAuthorizationCount(),
            'inactive_authorization_count' => $sourceProviderInstanceDependenciesDto->inactiveAuthorizationCount(),
            'sources'                      => array_map(static fn (array $source): array => [
                'id'       => $source['id'],
                'name'     => $source['name'],
                'url'      => $source['url'],
                'provider' => $source['provider'],
                'status'   => $source['status'],
            ], $sourceProviderInstanceDependenciesDto->sources),
            'authorizations'               => array_map(static fn (array $authorization): array => [
                'id'            => $authorization['id'],
                'account_name'  => $authorization['accountName'],
                'provider'      => $authorization['provider'],
                'auth_strategy' => $authorization['authStrategy'],
                'status'        => $authorization['status'],
            ], $sourceProviderInstanceDependenciesDto->authorizations),
        ];
    }
}
