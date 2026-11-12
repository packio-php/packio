<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Service;

interface ReleaseArchiveDescriptorInterface
{
    /**
     * @return null|array{provider: string, owner: string, repository: string, reference: string, source_id: string, file_name: string, base_url: string, provider_config: array<string, mixed>}
     */
    public function archiveDescriptor(string $packageName, string $version, string $organizationId): ?array;
}
