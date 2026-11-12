<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Persistence\Cycle;

use Override;
use Packio\Admin\Domain\Entity\ProjectSetting;
use Packio\Admin\Domain\Repository\ProjectSettingRepositoryInterface;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function iterator_to_array;
use function strtolower;
use function trim;

/**
 * @extends AbstractWriteRepository<ProjectSetting>
 */
final class ProjectSettingRepository extends AbstractWriteRepository implements ProjectSettingRepositoryInterface
{
    public function save(ProjectSetting $projectSetting): ProjectSetting
    {
        $this->persistAndFlush($projectSetting);

        return $projectSetting;
    }

    public function findByKey(string $key): ?ProjectSetting
    {
        $entity = $this->select()
            ->where([
                'key' => strtolower(trim($key)),
            ])
            ->fetchOne()
        ;

        return $entity instanceof ProjectSetting ? $entity : null;
    }

    /**
     * @param array<string, mixed>        $scope
     * @param array<string, 'ASC'|'DESC'> $orderBy
     *
     * @return list<ProjectSetting>
     */
    #[Override]
    public function findAll(array $scope = [], array $orderBy = []): array
    {
        /** @var non-empty-array<non-empty-string, non-empty-string> $sort */
        $sort = [] === $orderBy ? [
            'key' => 'ASC',
        ] : $orderBy;

        return iterator_to_array(
            $this->select()->orderBy($sort)->fetchAll(),
            false,
        );
    }
}
