<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\ORMInterface;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceSettingsPatch;
use Packio\Source\Api\Dto\SourceSettingsResultDto;
use Packio\Source\Api\Dto\SourceSettingsStatus;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Port\PackageVisibilityPort;
use Packio\Source\Application\Query\SourceQueryService;
use Packio\Source\Application\Service\SourceSettingsUpdaterInterface;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Infrastructure\Mapper\SourceDtoMapper;
use RuntimeException;
use Throwable;

final readonly class TransactionalSourceSettingsUpdater implements SourceSettingsUpdaterInterface
{
    public function __construct(
        private ORMInterface $orm,
        private SourceQueryService $sourceQueryService,
        private SourceCommandService $sourceCommandService,
        private SourceDtoMapper $sourceDtoMapper,
        private PackageVisibilityPort $packageVisibilityPort,
    ) {}

    public function update(ActorContext $actorContext, string $sourceId, SourceSettingsPatch $sourceSettingsPatch): SourceSettingsResultDto
    {
        $sourceStorage = $this->orm->getSource('source');
        $database      = $sourceStorage->getDatabase();
        if ($database->getDriver(DatabaseInterface::WRITE) !== $this->orm->getSource('package')->getDatabase()->getDriver(DatabaseInterface::WRITE)) {
            throw new RuntimeException('Source settings require a shared Source and Package write connection.');
        }

        try {
            return $database->transaction(function() use ($database, $sourceStorage, $actorContext, $sourceId, $sourceSettingsPatch): SourceSettingsResultDto {
                $row = $database->select()->from($sourceStorage->getTable())
                    ->where([
                        'uuid'            => $sourceId,
                        'organization_id' => $actorContext->organizationId,
                    ])
                    ->forUpdate()->run()->fetch()
                ;
                if (false === $row) {
                    return new SourceSettingsResultDto(SourceSettingsStatus::NotFound);
                }

                // Reload after acquiring the lock: an earlier request read may be stale.
                $this->orm->getHeap()->clean();
                $source = $this->findSource($sourceId);
                if (! $source instanceof SourceDto || $source->organizationId !== $actorContext->organizationId) {
                    return new SourceSettingsResultDto(SourceSettingsStatus::NotFound);
                }

                $entity = $this->sourceCommandService->updateSettingsInContext(
                    $actorContext,
                    $sourceId,
                    $sourceSettingsPatch->visibility ?? $source->visibility,
                    $sourceSettingsPatch->active ?? $source->active,
                    $sourceSettingsPatch->keepLastReleases ?? $source->keepLastReleases,
                );
                if (! $entity instanceof Source) {
                    throw new RuntimeException('Locked source disappeared during settings update.');
                }
                $sourceDto = $this->sourceDtoMapper->map($entity);
                $this->packageVisibilityPort->updateVisibilityBySourceIdInContext($actorContext, $sourceDto->id, $sourceDto->visibility);

                return new SourceSettingsResultDto(SourceSettingsStatus::Updated, $sourceDto);
            });
        } catch (Throwable $exception) {
            // Flushed entities must not remain cached with values rolled back in the database.
            $this->orm->getHeap()->clean();

            throw $exception;
        }
    }

    private function findSource(string $sourceId): ?SourceDto
    {
        $entity = $this->sourceQueryService->findSource($sourceId);

        return $entity instanceof Source ? $this->sourceDtoMapper->map($entity) : null;
    }
}
