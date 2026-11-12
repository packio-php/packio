<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Facade;

use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Registry\Application\Query\RegistryQueryService;

final readonly class RegistryApiFacade implements RegistryApiFacadeInterface
{
    public function __construct(private RegistryQueryService $registryQueryService) {}

    public function publicRegistryPayload(string $organizationId): array
    {
        return $this->registryQueryService->publicRegistryPayload($organizationId);
    }

    public function privateRegistryPayload(string $organizationId): array
    {
        return $this->registryQueryService->privateRegistryPayload($organizationId);
    }

    public function packageMetadata(string $packageName, string $organizationId): ?array
    {
        return $this->registryQueryService->packageMetadata($packageName, $organizationId);
    }

    public function packageOverview(string $organizationId): array
    {
        return $this->registryQueryService->packageOverview($organizationId);
    }
}
