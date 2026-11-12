<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\OptimisticLock;
use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Identity\Domain\Entity\PasswordResetToken;
use Packio\Identity\Infrastructure\Persistence\Cycle\PasswordResetTokenRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class PasswordResetTokenEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(PasswordResetToken::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('password_reset_token')
            ->setClass(PasswordResetToken::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(PasswordResetTokenRepository::class)
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
                'userUuid',
                new Field()->setType('string')->setColumn('user_uuid'),
            )
            ->set(
                'selector',
                new Field()->setType('string')->setColumn('selector'),
            )
            ->set(
                'tokenHash',
                new Field()->setType('string')->setColumn('token_hash'),
            )
            ->set(
                'createdAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('created_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'expiresAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('expires_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'usedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('used_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'requestedIp',
                new Field()
                    ->setType('string')
                    ->setColumn('requested_ip'),
            )
            ->set(
                'consumedIp',
                new Field()
                    ->setType('string')
                    ->setColumn('consumed_ip'),
            )
            ->set(
                'userAgent',
                new Field()
                    ->setType('string')
                    ->setColumn('user_agent'),
            )
            ->set(
                'version',
                new Field()
                    ->setType('integer')
                    ->setColumn('version'),
            )
        ;

        $entity
            ->getFields()
            ->get('expiresAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('usedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('requestedIp')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('consumedIp')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;
        $entity
            ->getFields()
            ->get('userAgent')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(
            new ChronosCreatedAt(field: 'createdAt', column: 'created_at'),
        );
        $entity->addSchemaModifier(new OptimisticLock(field: 'version', column: 'version', rule: OptimisticLock::RULE_INCREMENT));
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'password_reset_tokens');

        $registry
            ->getTableSchema($entity)
            ->index(['selector'])
            ->setName('password_reset_tokens_unique_selector')
            ->unique()
        ;

        return $registry;
    }
}
