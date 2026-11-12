<?php

declare(strict_types=1);

namespace Packio\Source\Application\Command;

use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;

final readonly class SourceMaintenanceService
{
    public function __construct(private SourceRepositoryInterface $sourceRepository) {}

    public function markSyncSucceeded(string $id): ?Source
    {
        $entity = $this->sourceRepository->findById($id);
        if (! $entity instanceof Source) {
            return null;
        }

        $entity->markSyncSucceeded();

        return $this->sourceRepository->save($entity);
    }

    public function markSyncFailed(string $id, string $error): ?Source
    {
        $entity = $this->sourceRepository->findById($id);
        if (! $entity instanceof Source) {
            return null;
        }

        $entity->markSyncFailed($error);

        return $this->sourceRepository->save($entity);
    }

    public function deleteSource(string $id): bool
    {
        $entity = $this->sourceRepository->findById($id);
        if (! $entity instanceof Source) {
            return false;
        }

        $this->sourceRepository->deleteAndFlush($entity);

        return true;
    }
}
