<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use Common\Branding\BrandingConfig;

use function is_array;
use function is_string;
use function str_starts_with;

final readonly class RegistryMetadataUrlNormalizer
{
    public function __construct(private BrandingConfig $brandingConfig) {}

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    public function normalize(array $payload, string $organizationSlug): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalize($value, $organizationSlug);
            } elseif (
                is_string($value)
                && ('metadata-url' === $key || 'url' === $key)
                && str_starts_with($value, '/')
                && ! str_starts_with($value, '//')
            ) {
                $payload[$key] = $this->brandingConfig->registryBaseUrl($organizationSlug) . $value;
            }
        }

        return $payload;
    }
}
