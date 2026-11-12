<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Source\Api\Dto\SourceProviderDto;

final readonly class SourceProviderResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(SourceProviderDto $sourceProviderDto): array
    {
        return [
            'key'             => $sourceProviderDto->key,
            'name'            => $sourceProviderDto->name,
            'description'     => $sourceProviderDto->description,
            'capabilities'    => $sourceProviderDto->capabilities,
            'oauth_provider'  => $sourceProviderDto->oauthProvider,
            'oauth_enabled'   => $sourceProviderDto->oauthEnabled,
            'oauth_available' => $sourceProviderDto->oauthAvailable,
        ];
    }
}
