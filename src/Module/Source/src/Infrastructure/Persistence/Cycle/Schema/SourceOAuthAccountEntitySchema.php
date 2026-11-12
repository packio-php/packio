<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceOAuthAccountRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class SourceOAuthAccountEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(SourceOAuthAccount::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('source_oauth_account')
            ->setClass(SourceOAuthAccount::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(SourceOAuthAccountRepository::class)
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
                'userId',
                new Field()->setType('string')->setColumn('user_id'),
            )
            ->set(
                'providerInstanceId',
                new Field()
                    ->setType('string(36)')
                    ->setColumn('provider_instance_id'),
            )
            ->set(
                'providerBaseUrl',
                new Field()
                    ->setType('string')
                    ->setColumn('provider_base_url'),
            )
            ->set(
                'credentialKey',
                new Field()
                    ->setType('string(64)')
                    ->setColumn('credential_key'),
            )
            ->set(
                'provider',
                new Field()
                    ->setType('string')
                    ->setColumn('provider')
            )
            ->set(
                'providerUserId',
                new Field()
                    ->setType('string')
                    ->setColumn('provider_user_id'),
            )
            ->set(
                'providerEmail',
                new Field()
                    ->setType('string')
                    ->setColumn('provider_email'),
            )
            ->set(
                'providerUsername',
                new Field()
                    ->setType('string')
                    ->setColumn('provider_username'),
            )
            ->set(
                'accessToken',
                new Field()
                    ->setType('text')
                    ->setColumn('access_token'),
            )
            ->set(
                'refreshToken',
                new Field()
                    ->setType('text')
                    ->setColumn('refresh_token'),
            )
            ->set(
                'tokenExpiresAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('token_expires_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'scopes',
                new Field()
                    ->setType('json')
                    ->setColumn('scopes')
                    ->setTypecast('json'),
            )
            ->set(
                'isActive',
                new Field()
                    ->setType('bool')
                    ->setColumn('is_active'),
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
        ;

        $entity
            ->getFields()
            ->get('scopes')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, '[]')
        ;

        $entity
            ->getFields()
            ->get('isActive')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, 'true')
        ;

        $entity
            ->getFields()
            ->get('providerEmail')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('providerUsername')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('refreshToken')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('tokenExpiresAt')
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
            ->get('providerBaseUrl')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('credentialKey')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
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

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(
            new ChronosCreatedAt(field: 'createdAt', column: 'created_at'),
        );
        $entity->addSchemaModifier(
            new ChronosUpdatedAt(field: 'updatedAt', column: 'updated_at'),
        );
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'source_oauth_accounts');
        $registry->getTableSchema($entity)
            ->index(['credential_key'])
            ->setName('source_oauth_accounts_unique_credential_key')
            ->unique()
        ;

        return $registry;
    }
}
