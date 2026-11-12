<?php

declare(strict_types=1);

namespace Packio\Audit\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Audit\Domain\Entity\AuditEntry;
use Packio\Audit\Infrastructure\Persistence\Cycle\AuditRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class AuditEntryEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(AuditEntry::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('audit_entry')
            ->setClass(AuditEntry::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(AuditRepository::class)
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
            ->set('event', new Field()->setType('string')->setColumn('event'))
            ->set(
                'occurredAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('occurred_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'organizationId',
                new Field()->setType('string')->setColumn('organization_id'),
            )
            ->set(
                'sourceId',
                new Field()->setType('string')->setColumn('source_id'),
            )
            ->set(
                'packageName',
                new Field()->setType('string')->setColumn('package_name'),
            )
            ->set(
                'actorId',
                new Field()->setType('string')->setColumn('actor_id'),
            )
            ->set(
                'subjectType',
                new Field()->setType('string')->setColumn('subject_type'),
            )
            ->set(
                'subjectId',
                new Field()->setType('string')->setColumn('subject_id'),
            )
            ->set(
                'operationKey',
                new Field()->setType('string')->setColumn('operation_key'),
            )
            ->set(
                'context',
                new Field()
                    ->setType('json')
                    ->setColumn('context')
                    ->setTypecast('json'),
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
            ->get('operationKey')
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
        $registry->linkTable($entity, 'default', 'audit_entries');

        // PostgreSQL can scan this B-tree index backwards for the descending audit query.
        // Cycle requires the columns to exist before it can register the index, so the
        // generated migration below adds this index after creating the new columns.
        $registry
            ->getTableSchema($entity)
            ->index(['subject_type', 'subject_id', 'occurred_at', 'uuid'])
            ->setName('audit_entries_subject_recent_idx')
        ;
        $registry
            ->getTableSchema($entity)
            ->index(['organization_id', 'source_id', 'occurred_at', 'uuid'])
            ->setName('audit_entries_source_recent_idx')
        ;
        $registry
            ->getTableSchema($entity)
            ->index(['operation_key'])
            ->setName('audit_entries_unique_operation_key')
            ->unique()
        ;

        return $registry;
    }
}
