<?php

declare(strict_types=1);

namespace Packio\Release\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Infrastructure\Persistence\Cycle\ReleaseRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class ReleaseEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(Release::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('release')
            ->setClass(Release::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(ReleaseRepository::class)
        ;

        $entity
            ->getFields()
            ->set(
                'uuid',
                new Field()
                    ->setType('uuid')
                    ->setColumn('uuid')
                    ->setPrimary(true),
            )
            ->set(
                'organizationId',
                new Field()->setType('string')->setColumn('organization_id'),
            )
            ->set(
                'packageName',
                new Field()->setType('string')->setColumn('package_name'),
            )
            ->set(
                'version',
                new Field()->setType('string')->setColumn('version'),
            )
            ->set(
                'reference',
                new Field()->setType('string')->setColumn('reference'),
            )
            ->set(
                'upstreamReference',
                new Field()->setType('string')->setColumn('upstream_reference'),
            )
            ->set(
                'referenceType',
                new Field()->setType('string')->setColumn('reference_type'),
            )
            ->set(
                'active',
                new Field()->setType('boolean')->setColumn('active'),
            )
            ->set(
                'composerData',
                new Field()
                    ->setType('json')
                    ->setColumn('composer_data')
                    ->setTypecast('json'),
            )
            ->set(
                'sourceData',
                new Field()
                    ->setType('json')
                    ->setColumn('source_data')
                    ->setTypecast('json'),
            )
            ->set(
                'distData',
                new Field()
                    ->setType('json')
                    ->setColumn('dist_data')
                    ->setTypecast('json'),
            )
            ->set(
                'downloadCount',
                new Field()->setType('integer')->setColumn('download_count'),
            )
            ->set(
                'releasedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('released_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
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
            ->get('updatedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('releasedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('upstreamReference')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('referenceType')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('downloadCount')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, 0)
        ;

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(
            new ChronosCreatedAt(field: 'createdAt', column: 'created_at'),
        );
        $entity->addSchemaModifier(
            new ChronosUpdatedAt(field: 'updatedAt', column: 'updated_at'),
        );
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'releases');
        $registry->getTableSchema($entity)->index(['organization_id', 'package_name', 'version'])->unique();

        return $registry;
    }
}
