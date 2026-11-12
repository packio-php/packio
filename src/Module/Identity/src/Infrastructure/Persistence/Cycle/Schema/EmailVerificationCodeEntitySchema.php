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
use Packio\Identity\Domain\Entity\EmailVerificationCode;
use Packio\Identity\Infrastructure\Persistence\Cycle\EmailVerificationCodeRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class EmailVerificationCodeEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(EmailVerificationCode::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('email_verification_code')
            ->setClass(EmailVerificationCode::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(EmailVerificationCodeRepository::class)
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
                'codeHash',
                new Field()->setType('string')->setColumn('code_hash'),
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
                'verifiedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('verified_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
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
            ->get('verifiedAt')
            ->getOptions()
            ->set(Column::OPT_NULLABLE, true)
        ;

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(
            new ChronosCreatedAt(field: 'createdAt', column: 'created_at'),
        );
        $entity->addSchemaModifier(new OptimisticLock(field: 'version', column: 'version', rule: OptimisticLock::RULE_INCREMENT));
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'email_verification_codes');

        $registry
            ->getTableSchema($entity)
            ->index(['user_uuid'])
            ->setName('email_verification_codes_index_user_uuid')
        ;

        return $registry;
    }
}
