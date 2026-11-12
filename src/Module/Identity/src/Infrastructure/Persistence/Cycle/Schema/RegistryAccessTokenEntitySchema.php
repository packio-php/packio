<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Identity\Domain\Entity\RegistryAccessToken;
use Packio\Identity\Infrastructure\Persistence\Cycle\RegistryAccessTokenRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class RegistryAccessTokenEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(RegistryAccessToken::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('registry_access_token')
            ->setClass(RegistryAccessToken::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(RegistryAccessTokenRepository::class)
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
                'ownerUserId',
                new Field()->setType('string')->setColumn('owner_user_id'),
            )
            ->set(
                'organizationId',
                new Field()->setType('string')->setColumn('organization_id'),
            )
            ->set('name', new Field()->setType('string')->setColumn('name'))
            ->set(
                'tokenHash',
                new Field()->setType('string')->setColumn('token_hash'),
            )
            ->set(
                'maskedToken',
                new Field()->setType('string')->setColumn('masked_token'),
            )
            ->set(
                'permissions',
                new Field()
                    ->setType('json')
                    ->setColumn('permissions')
                    ->setTypecast('json'),
            )
            ->set(
                'lastUsedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('last_used_at')
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
            ->set(
                'encryptedToken',
                new Field()->setType('string')->setColumn('encrypted_token'),
            )
        ;

        $entity
            ->getFields()
            ->get('lastUsedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('updatedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('maskedToken')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('encryptedToken')
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
        $registry->linkTable($entity, 'default', 'registry_access_tokens');

        $registry
            ->getTableSchema($entity)
            ->index(['token_hash'])
            ->setName('registry_access_tokens_unique_token_hash')
            ->unique()
        ;
        $registry
            ->getTableSchema($entity)
            ->index(['organization_id'])
            ->setName('registry_access_tokens_index_organization_id')
        ;

        return $registry;
    }
}
