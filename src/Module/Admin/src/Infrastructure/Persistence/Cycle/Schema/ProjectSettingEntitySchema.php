<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Persistence\Cycle\Schema;

use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Admin\Domain\Entity\ProjectSetting;
use Packio\Admin\Infrastructure\Persistence\Cycle\ProjectSettingRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class ProjectSettingEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(ProjectSetting::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('project_setting')
            ->setClass(ProjectSetting::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(ProjectSettingRepository::class)
        ;

        $entity
            ->getFields()
            ->set(
                'key',
                new Field()
                    ->setType('string')
                    ->setColumn('setting_key')
                    ->setPrimary(true),
            )
            ->set(
                'value',
                new Field()->setType('string')->setColumn('setting_value'),
            )
            ->set(
                'updatedByUserId',
                new Field()
                    ->setType('string')
                    ->setColumn('updated_by_user_id'),
            )
            ->set(
                'createdAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('created_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'updatedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('updated_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
        ;

        $entity
            ->getFields()
            ->get('updatedByUserId')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('updatedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity->addSchemaModifier(
            new ChronosCreatedAt(field: 'createdAt', column: 'created_at'),
        );
        $entity->addSchemaModifier(
            new ChronosUpdatedAt(field: 'updatedAt', column: 'updated_at'),
        );
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'project_settings');

        return $registry;
    }
}
