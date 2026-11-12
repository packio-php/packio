<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Port;

use Packio\Registry\Application\Dto\ReleaseData;

interface ReleaseStorageMetadataPort
{
    /**
     * @return null|array<string, mixed>
     */
    public function findDistStorageMetadata(string $organizationId, string $packageName, string $version): ?array;

    /**
     * @return list<string>
     */
    public function distStorageKeysForPackage(string $organizationId, string $packageName): array;

    /**
     * @param array<string, mixed> $storageData
     */
    public function updateDistStorageMetadata(
        string $organizationId,
        string $packageName,
        string $version,
        array $storageData
    ): ?ReleaseData;
}
