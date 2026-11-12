<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Infrastructure\Persistence\Cycle\PackageRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class PackageEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(Package::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('package')
            ->setClass(Package::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(PackageRepository::class)
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
            ->set('name', new Field()->setType('string')->setColumn('name'))
            ->set('type', new Field()->setType('string')->setColumn('type'))
            ->set(
                'description',
                new Field()->setType('text')->setColumn('description'),
            )
            ->set(
                'visibility',
                new Field()->setType('string')->setColumn('visibility'),
            )
            ->set(
                'active',
                new Field()->setType('boolean')->setColumn('active'),
            )
            ->set(
                'sourceId',
                new Field()->setType('string')->setColumn('source_id'),
            )
            ->set(
                'sourceType',
                new Field()->setType('string')->setColumn('source_type'),
            )
            ->set(
                'sourceUrl',
                new Field()->setType('string')->setColumn('source_url'),
            )
            ->set(
                'readmeHtml',
                new Field()->setType('text')->setColumn('readme_html'),
            )
            ->set(
                'downloadCount',
                new Field()->setType('integer')->setColumn('download_count'),
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
            ->get('readmeHtml')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('downloadCount')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, 0)
        ;

        $entity
            ->getFields()
            ->get('updatedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(
            new ChronosCreatedAt(field: 'createdAt', column: 'created_at'),
        );
        $entity->addSchemaModifier(
            new ChronosUpdatedAt(field: 'updatedAt', column: 'updated_at'),
        );
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'packages');
        $registry->getTableSchema($entity)->index(['organization_id', 'name'])->unique();

        return $registry;
    }
}
