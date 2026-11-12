<?php

declare(strict_types=1);

namespace Packio\Registry\Api;

interface RegistryApiFacadeInterface
{
    /**
     * @return array<string, mixed>
     */
    public function publicRegistryPayload(string $organizationId): array;

    /**
     * @return array<string, mixed>
     */
    public function privateRegistryPayload(string $organizationId): array;

    /**
     * @return null|array<string, mixed>
     */
    public function packageMetadata(string $packageName, string $organizationId): ?array;

    /**
     * @return list<array{
     *     name: string,
     *     description: null|string,
     *     latest_version: null|string,
     *     visibility: string,
     *     active: bool,
     *     source_url: string,
     *     registry_endpoint: string,
     *     access_hint: string
     * }>
     */
    public function packageOverview(string $organizationId): array;
}
