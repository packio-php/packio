<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Source\Api\Dto\ProviderResourceDto;

final readonly class ProviderResourceResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(ProviderResourceDto $providerResourceDto): array
    {
        return [
            'id'            => $providerResourceDto->id,
            'provider'      => $providerResourceDto->provider,
            'name'          => $providerResourceDto->name,
            'canonical_url' => $providerResourceDto->canonicalUrl,
            'default_ref'   => $providerResourceDto->defaultRef,
            'private'       => $providerResourceDto->private,
        ];
    }
}
