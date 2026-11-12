<?php

declare(strict_types=1);

namespace Packio\Worker\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\OptimisticLock;
use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Infrastructure\Persistence\Cycle\JobRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class JobEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(Job::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('job')
            ->setClass(Job::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(JobRepository::class)
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
                'type',
                new Field()->setType('string')->setColumn('type'),
            )
            ->set(
                'payload',
                new Field()
                    ->setType('json')
                    ->setColumn('payload')
                    ->setTypecast('json'),
            )
            ->set(
                'idempotencyKey',
                new Field()->setType('string')->setColumn('idempotency_key'),
            )
            ->set(
                'status',
                new Field()->setType('string')->setColumn('status'),
            )
            ->set(
                'priority',
                new Field()->setType('integer')->setColumn('priority'),
            )
            ->set(
                'scheduledAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('scheduled_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'startedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('started_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'completedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('completed_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'attemptCount',
                new Field()->setType('integer')->setColumn('attempt_count'),
            )
            ->set(
                'maxAttempts',
                new Field()->setType('integer')->setColumn('max_attempts'),
            )
            ->set(
                'errorMessage',
                new Field()->setType('text')->setColumn('error_message'),
            )
            ->set(
                'errorDetails',
                new Field()->setType('text')->setColumn('error_details'),
            )
            ->set(
                'workerId',
                new Field()->setType('string')->setColumn('worker_id'),
            )
            ->set(
                'createdAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('created_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'version',
                new Field()
                    ->setType('integer')
                    ->setColumn('version'),
            )
        ;

        $entity
            ->getFields()
            ->get('idempotencyKey')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('scheduledAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('startedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('completedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('errorMessage')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('errorDetails')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('workerId')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(new ChronosCreatedAt(field: 'createdAt', column: 'created_at'));
        $entity->addSchemaModifier(new OptimisticLock(field: 'version', column: 'version', rule: OptimisticLock::RULE_INCREMENT));
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'jobs');
        $registry->getTableSchema($entity)->index(['status', 'scheduled_at']);

        return $registry;
    }
}
