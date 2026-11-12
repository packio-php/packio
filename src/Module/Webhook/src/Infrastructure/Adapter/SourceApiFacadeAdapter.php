<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Adapter;

use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Application\Port\SourcePort;

final readonly class SourceApiFacadeAdapter implements SourcePort
{
    private const int SYNC_PRIORITY = 5;

    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
    ) {}

    public function findSource(string $sourceId): ?SourceData
    {
        $dto = $this->sourceApiFacade->findSource($sourceId);

        return $dto instanceof SourceDto ? new SourceData(
            id: $dto->id,
            organizationId: $dto->organizationId,
            provider: $dto->provider,
            url: $dto->url,
            active: $dto->active,
            webhookSecret: (string) ($dto->providerConfig['webhook_secret'] ?? ''),
        ) : null;
    }

    public function scheduleSourceSyncOnce(SourceData $sourceData, string $idempotencyKey): string
    {
        return $this->sourceApiFacade->scheduleSourceSyncOnce(
            $sourceData->id,
            $sourceData->organizationId,
            $idempotencyKey,
            self::SYNC_PRIORITY,
        );
    }
}
