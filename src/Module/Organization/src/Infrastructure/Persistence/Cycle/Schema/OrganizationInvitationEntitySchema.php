<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Infrastructure\Persistence\Cycle\OrganizationInvitationRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class OrganizationInvitationEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(OrganizationInvitation::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('organization_invitation')
            ->setClass(OrganizationInvitation::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(OrganizationInvitationRepository::class)
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
                'invitedByUserId',
                new Field()->setType('string')->setColumn('invited_by_user_id'),
            )
            ->set('token', new Field()->setType('string')->setColumn('token'))
            ->set('tokenHash', new Field()->setType('string(64)')->setColumn('token_hash'))
            ->set('role', new Field()->setType('string')->setColumn('role'))
            ->set(
                'acceptedByUserId',
                new Field()
                    ->setType('string')
                    ->setColumn('accepted_by_user_id'),
            )
            ->set(
                'expiresAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('expires_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'acceptedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('accepted_at')
                    ->setTypecast([ChronosNativeTypecast::class, 'toChronos']),
            )
            ->set(
                'revokedAt',
                new Field()
                    ->setType('datetime')
                    ->setColumn('revoked_at')
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

        foreach (['acceptedByUserId', 'expiresAt', 'acceptedAt', 'revokedAt', 'updatedAt'] as $fieldName) {
            $entity
                ->getFields()
                ->get($fieldName)
                ->getOptions()
                ->set(Column::OPT_NULLABLE, true)
            ;
        }

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(
            new ChronosCreatedAt(field: 'createdAt', column: 'created_at'),
        );
        $entity->addSchemaModifier(
            new ChronosUpdatedAt(field: 'updatedAt', column: 'updated_at'),
        );
        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'organization_invitations');
        $table = $registry->getTableSchema($entity);
        // Declared before its index: the table is read from the database, where a new column does not exist yet.
        $table->string('token_hash', 64);
        $table->index(['token_hash'])->setName('organization_invitations_token_hash_unique')->unique();
        $table->index(['organization_id'])->setName('organization_invitations_organization_id_idx');

        return $registry;
    }
}
