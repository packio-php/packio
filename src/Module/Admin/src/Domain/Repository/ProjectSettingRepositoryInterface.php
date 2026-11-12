<?php

declare(strict_types=1);

namespace Packio\Admin\Domain\Repository;

use Packio\Admin\Domain\Entity\ProjectSetting;

interface ProjectSettingRepositoryInterface
{
    public function save(ProjectSetting $projectSetting): ProjectSetting;

    public function findByKey(string $key): ?ProjectSetting;

    /**
     * @param array<string, mixed>        $scope
     * @param array<string, 'ASC'|'DESC'> $orderBy
     *
     * @return list<ProjectSetting>
     */
    public function findAll(array $scope = [], array $orderBy = []): array;
}
