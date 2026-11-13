<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Adapter;

use Packio\Registry\Application\Dto\SourceData;
use Packio\Registry\Application\Port\SourceQueryPort;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;

use function array_diff_key;

final readonly class SourceApiFacadeAdapter implements SourceQueryPort
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceSecretResolverInterface $sourceSecretResolver
    ) {}

    public function findSource(string $sourceId): ?SourceData
    {
        $dto = $this->sourceApiFacade->findSource($sourceId);

        return $dto instanceof SourceDto ? $this->mapSourceData($dto) : null;
    }

    public function resolveSourceAuthorizationSecret(string $sourceId): ?string
    {
        return $this->sourceSecretResolver->resolveSourceAuthorizationSecret($sourceId);
    }

    private function mapSourceData(SourceDto $sourceDto): SourceData
    {
        return new SourceData(
            id: $sourceDto->id,
            provider: $sourceDto->provider,
            organizationId: $sourceDto->organizationId,
            owner: $sourceDto->owner,
            repository: $sourceDto->repository,
            active: $sourceDto->active,
            visibility: $sourceDto->visibility,
            // The registry only downloads archives; the webhook secret must not travel with the descriptor.
            providerConfig: array_diff_key($sourceDto->providerConfig, [
                'webhook_secret' => true,
            ]),
            authType: $sourceDto->authType,
        );
    }
}
