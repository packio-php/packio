<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Infrastructure\Persistence\Cycle\UserOAuthAccountRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class UserOAuthAccountEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(UserOAuthAccount::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('user_oauth_account')
            ->setClass(UserOAuthAccount::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(UserOAuthAccountRepository::class)
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
                'oAuthProvider',
                new Field()
                    ->setType('string')
                    ->setColumn('provider')
                    ->setTypecast(OAuthProvider::class),
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
                'scopes',
                new Field()
                    ->setType('json')
                    ->setColumn('scopes')
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
            ->get('scopes')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, '[]')
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
        $registry->linkTable($entity, 'default', 'user_oauth_accounts');

        $registry
            ->getTableSchema($entity)
            ->index(['provider', 'provider_user_id'])
            ->setName('user_oauth_accounts_unique_provider_user')
            ->unique()
        ;

        return $registry;
    }
}
