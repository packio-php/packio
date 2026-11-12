<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Infrastructure\Persistence\Cycle\OrganizationRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class OrganizationEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(Organization::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('organization')
            ->setClass(Organization::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(OrganizationRepository::class)
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
            ->set('slug', new Field()->setType('string')->setColumn('slug'))
            ->set('name', new Field()->setType('string')->setColumn('name'))
            ->set(
                'anonymousAccess',
                new Field()->setType('boolean')->setColumn('anonymous_access'),
            )
            ->set(
                'active',
                new Field()->setType('boolean')->setColumn('active'),
            )
            ->set(
                'status',
                new Field()
                    ->setType('string')
                    ->setColumn('status'),
            )
            ->set(
                'deletionRunId',
                new Field()
                    ->setType('string(36)')
                    ->setColumn('deletion_run_id'),
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
            ->get('status')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, 'active')
        ;
        $entity
            ->getFields()
            ->get('deletionRunId')
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
        $registry->linkTable($entity, 'default', 'organizations');
        $registry->getTableSchema($entity)->index(['slug'])->unique();

        return $registry;
    }
}
