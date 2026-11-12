<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Infrastructure\Persistence\Cycle\ProviderAuthorizationRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class ProviderAuthorizationEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(ProviderAuthorization::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('provider_authorization')
            ->setClass(ProviderAuthorization::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(ProviderAuthorizationRepository::class)
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
                'authStrategy',
                new Field()->setType('string')->setColumn('auth_strategy'),
            )
            ->set(
                'status',
                new Field()->setType('string')->setColumn('status'),
            )
            ->set(
                'accountName',
                new Field()->setType('string')->setColumn('account_name'),
            )
            ->set(
                'configuration',
                new Field()
                    ->setType('json')
                    ->setColumn('configuration')
                    ->setTypecast('json'),
            )
            ->set(
                'sourceOAuthAccountId',
                new Field()
                    ->setType('string(36)')
                    ->setColumn('source_oauth_account_id'),
            )
            ->set(
                'providerInstanceId',
                new Field()
                    ->setType('string(36)')
                    ->setColumn('provider_instance_id'),
            )
            ->set(
                'createdAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('created_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos'])
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
            ->get('sourceOAuthAccountId')
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
        $registry->linkTable($entity, 'default', 'provider_authorizations');
        $table = $registry->getTableSchema($entity);
        $table->index(['organization_id'])->setName('provider_authorizations_organization_id_idx');
        $table->index(['source_oauth_account_id'])->setName('provider_authorizations_source_oauth_account_id_idx');

        return $registry;
    }
}
