<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class SourceEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(Source::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('source')
            ->setClass(Source::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(SourceRepository::class)
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
                'provider',
                new Field()->setType('string')->setColumn('provider'),
            )
            ->set(
                'canonicalUrl',
                new Field()->setType('string')->setColumn('canonical_url'),
            )
            ->set(
                'displayName',
                new Field()->setType('string')->setColumn('display_name'),
            )
            ->set(
                'providerAuthorizationId',
                new Field()
                    ->setType('string')
                    ->setColumn('provider_authorization_id'),
            )
            ->set(
                'providerResourceId',
                new Field()
                    ->setType('string')
                    ->setColumn('provider_resource_id'),
            )
            ->set(
                'providerInstanceId',
                new Field()
                    ->setType('string(36)')
                    ->setColumn('provider_instance_id'),
            )
            ->set(
                'providerConfig',
                new Field()
                    ->setType('json')
                    ->setColumn('provider_config')
                    ->setTypecast('json'),
            )
            ->set(
                'defaultRef',
                new Field()->setType('string')->setColumn('default_ref'),
            )
            ->set(
                'visibility',
                new Field()->setType('string')->setColumn('visibility'),
            )
            ->set(
                'keepLastReleases',
                new Field()->setType('integer')->setColumn('keep_last_releases'),
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
                'lastSyncStatus',
                new Field()->setType('string')->setColumn('last_sync_status'),
            )
            ->set(
                'lastSuccessfulSyncAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('last_successful_sync_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'lastFailedSyncAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('last_failed_sync_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'lastSyncError',
                new Field()->setType('text')->setColumn('last_sync_error'),
            )
            ->set(
                'archivedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('archived_at')
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
            ->get('providerAuthorizationId')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('providerResourceId')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('providerInstanceId')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('lastSuccessfulSyncAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('lastFailedSyncAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('archivedAt')
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
        $entity
            ->getFields()
            ->get('keepLastReleases')
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
        $registry->linkTable($entity, 'default', 'sources');
        $table = $registry->getTableSchema($entity);
        $table->index(['organization_id', 'canonical_url'])->unique(true)->setName('sources_organization_canonical_url_unique');
        $table->index(['provider_authorization_id'])->setName('sources_provider_authorization_id_idx');

        return $registry;
    }
}
