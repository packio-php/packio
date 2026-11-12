<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Source\Api\Dto\SourceProviderInstanceDto;

final readonly class SourceProviderInstanceResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(SourceProviderInstanceDto $sourceProviderInstanceDto): array
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
}
