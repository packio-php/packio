<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Infrastructure\Persistence\Cycle\WebhookEventRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class WebhookEventEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(WebhookEvent::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('webhook_event')
            ->setClass(WebhookEvent::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(WebhookEventRepository::class)
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
            ->set('sourceId', new Field()->setType('string')->setColumn('source_id'))
            ->set('provider', new Field()->setType('string')->setColumn('provider'))
            ->set('deliveryId', new Field()->setType('string')->setColumn('delivery_id'))
            ->set('event', new Field()->setType('string')->setColumn('event'))
            ->set('status', new Field()->setType('string')->setColumn('status'))
            ->set('payloadHash', new Field()->setType('string')->setColumn('payload_hash'))
            ->set('message', new Field()->setType('text')->setColumn('message'))
            ->set(
                'scheduledJobId',
                new Field()->setType('string')->setColumn('scheduled_job_id'),
            )
            ->set(
                'reprocessCount',
                new Field()->setType('integer')->setColumn('reprocess_count'),
            )
            ->set(
                'processedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('processed_at')
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
            ->get('scheduledJobId')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity
            ->getFields()
            ->get('reprocessCount')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, 0)
        ;

        $entity
            ->getFields()
            ->get('processedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
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
        $registry->linkTable($entity, 'default', 'webhook_events');
        $table = $registry->getTableSchema($entity);
        $table->index(['source_id', 'provider', 'delivery_id'])->setName('webhook_events_unique_source_provider_delivery')->unique();
        $table->index(['source_id', 'created_at'])->setName('webhook_events_source_created_idx');
        $table->index(['created_at'])->setName('webhook_events_created_idx');

        return $registry;
    }
}
