<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceProviderInstanceRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class SourceProviderInstanceEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(SourceProviderInstance::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('source_provider_instance')
            ->setClass(SourceProviderInstance::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(SourceProviderInstanceRepository::class)
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
                'providerType',
                new Field()->setType('string')->setColumn('provider_type'),
            )
            ->set(
                'label',
                new Field()->setType('string')->setColumn('label'),
            )
            ->set(
                'baseUrl',
                new Field()->setType('string')->setColumn('base_url'),
            )
            ->set(
                'clientId',
                new Field()->setType('string')->setColumn('client_id'),
            )
            ->set(
                'clientSecret',
                new Field()->setType('text')->setColumn('client_secret'),
            )
            ->set(
                'authMode',
                new Field()->setType('string')->setColumn('auth_mode'),
            )
            ->set(
                'status',
                new Field()->setType('string')->setColumn('status'),
            )
            ->set(
                'metadata',
                new Field()
                    ->setType('json')
                    ->setColumn('metadata')
                    ->setTypecast('json'),
            )
            ->set(
                'createdByUserId',
                new Field()->setType('string')->setColumn('created_by_user_id'),
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
            ->get('clientSecret')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('metadata')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, '[]')
        ;
        $entity
            ->getFields()
            ->get('status')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, SourceProviderInstance::STATUS_ACTIVE)
        ;
        $entity
            ->getFields()
            ->get('authMode')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN)
        ;
        $entity
            ->getFields()
            ->get('createdByUserId')
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
        $registry->linkTable($entity, 'default', 'source_provider_instances');
        $registry->getTableSchema($entity)
            ->index(['organization_id', 'provider_type', 'base_url'])
            ->setName('source_provider_instances_unique_org_provider_base')
            ->unique()
        ;

        return $registry;
    }
}
