<?php

declare(strict_types=1);

namespace Packio\Worker\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Infrastructure\Persistence\Cycle\JobLogRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class JobLogEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(JobLog::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('job_log')
            ->setClass(JobLog::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(JobLogRepository::class)
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
                'jobId',
                new Field()->setType('string')->setColumn('job_id'),
            )
            ->set(
                'level',
                new Field()->setType('string')->setColumn('level'),
            )
            ->set(
                'message',
                new Field()->setType('string')->setColumn('message'),
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
        ;

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(new ChronosCreatedAt(field: 'createdAt', column: 'created_at'));
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'job_logs');
        $registry->getTableSchema($entity)->index(['job_id', 'level']);

        return $registry;
    }
}
