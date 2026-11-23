<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Identity\Domain\Entity\UserSignIn;
use Packio\Identity\Infrastructure\Persistence\Cycle\UserSignInRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class UserSignInEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(UserSignIn::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('user_sign_in')
            ->setClass(UserSignIn::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(UserSignInRepository::class)
        ;

        $entity
            ->getFields()
            ->set('uuid', new Field()->setType('uuid')->setColumn('uuid')->setPrimary(true))
            ->set('userUuid', new Field()->setType('string')->setColumn('user_uuid'))
            ->set('method', new Field()->setType('string')->setColumn('method'))
            ->set('ip', new Field()->setType('string')->setColumn('ip'))
            ->set('userAgent', new Field()->setType('string')->setColumn('user_agent'))
            ->set(
                'createdAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('created_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
        ;

        foreach (['ip', 'userAgent'] as $nullableField) {
            $entity->getFields()->get($nullableField)->getOptions()->set(Column::OPT_NULLABLE, true);
        }

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(new ChronosCreatedAt(field: 'createdAt', column: 'created_at'));
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'user_sign_ins');

        $registry
            ->getTableSchema($entity)
            ->index(['user_uuid', 'created_at'])
            ->setName('user_sign_ins_user_recent_idx')
        ;
        $registry
            ->getTableSchema($entity)
            ->index(['created_at'])
            ->setName('user_sign_ins_created_at_idx')
        ;

        return $registry;
    }
}
