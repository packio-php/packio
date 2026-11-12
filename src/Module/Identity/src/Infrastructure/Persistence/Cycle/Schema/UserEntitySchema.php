<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Infrastructure\Persistence\Cycle\UserRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class UserEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(User::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('user')
            ->setClass(User::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(UserRepository::class)
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
            ->set('email', new Field()->setType('string')->setColumn('email'))
            ->set(
                'displayName',
                new Field()->setType('string')->setColumn('display_name'),
            )
            ->set(
                'passwordHash',
                new Field()->setType('string')->setColumn('password_hash'),
            )
            ->set(
                'roles',
                new Field()
                    ->setType('json')
                    ->setColumn('roles')
                    ->setTypecast('json'),
            )
            ->set(
                'emailVerifiedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('email_verified_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'bannedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('banned_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'banReason',
                new Field()
                    ->setType('string(500)')
                    ->setColumn('ban_reason'),
            )
            ->set(
                'bannedByUserId',
                new Field()
                    ->setType('string')
                    ->setColumn('banned_by_user_id'),
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
            ->get('emailVerifiedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('bannedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('banReason')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('bannedByUserId')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('createdAt')
            ->getOptions()
            ->set(Column::OPT_DEFAULT, 'CURRENT_TIMESTAMP')
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
            new ChronosUpdatedAt(field: 'updatedAt', column: 'updated_at', nullable: true),
        );
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'users');

        // User lists use the PostgreSQL database default collation and this order.
        $registry
            ->getTableSchema($entity)
            ->index(['email', 'uuid'])
            ->setName('users_email_uuid_idx')
        ;
        $registry
            ->getTableSchema($entity)
            ->index(['email'])
            ->setName('users_email_unique')
            ->unique()
        ;

        return $registry;
    }
}
