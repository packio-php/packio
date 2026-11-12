<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle\Schema;

use Cycle\ORM\Entity\Behavior\Uuid\Uuid7;
use Cycle\Schema\Definition\Entity;
use Cycle\Schema\Definition\Field;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\Table\Column;
use Packio\Identity\Domain\Entity\ApiAccessToken;
use Packio\Identity\Infrastructure\Persistence\Cycle\ApiAccessTokenRepository;
use Sirix\Cycle\Extension\Behavior\ChronosCreatedAt;
use Sirix\Cycle\Extension\Behavior\ChronosUpdatedAt;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

final class ApiAccessTokenEntitySchema implements GeneratorInterface
{
    public function run(Registry $registry): Registry
    {
        if ($registry->hasEntity(ApiAccessToken::class)) {
            return $registry;
        }

        $entity = new Entity()
            ->setRole('api_access_token')
            ->setClass(ApiAccessToken::class)
            /** @phpstan-ignore-next-line */
            ->setRepository(ApiAccessTokenRepository::class)
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
            ->set('userId', new Field()->setType('string')->setColumn('user_id'))
            ->set('name', new Field()->setType('string')->setColumn('name'))
            ->set('tokenHash', new Field()->setType('string')->setColumn('token_hash'))
            ->set('tokenPrefix', new Field()->setType('string')->setColumn('token_prefix'))
            ->set(
                'scopes',
                new Field()
                    ->setType('json')
                    ->setColumn('scopes')
                    ->setTypecast('json'),
            )
            ->set('lastUsedAt', new Field()->setType('datetime')->setColumn('last_used_at')->setTypecast([ChronosNativeTypecast::class, 'toChronos']))
            ->set('expiresAt', new Field()->setType('datetime')->setColumn('expires_at')->setTypecast([ChronosNativeTypecast::class, 'toChronos']))
            ->set('revokedAt', new Field()->setType('datetime')->setColumn('revoked_at')->setTypecast([ChronosNativeTypecast::class, 'toChronos']))
            ->set('createdAt', new Field()->setType('datetime')->setColumn('created_at')->setTypecast([ChronosNativeTypecast::class, 'toChronos']))
            ->set('updatedAt', new Field()->setType('datetime')->setColumn('updated_at')->setTypecast([ChronosNativeTypecast::class, 'toChronos']))
        ;

        foreach (['lastUsedAt', 'expiresAt', 'revokedAt', 'updatedAt'] as $field) {
            $entity
                ->getFields()
                ->get($field)
                ->getOptions()
                ->set(Column::OPT_NULLABLE, true)
            ;
        }

        $entity->addSchemaModifier(new Uuid7(field: 'uuid', column: 'uuid'));
        $entity->addSchemaModifier(new ChronosCreatedAt(field: 'createdAt', column: 'created_at'));
        $entity->addSchemaModifier(new ChronosUpdatedAt(field: 'updatedAt', column: 'updated_at'));

        $registry->register($entity);
        $registry->linkTable($entity, 'default', 'api_access_tokens');

        $registry
            ->getTableSchema($entity)
            ->index(['token_hash'])
            ->setName('api_access_tokens_unique_token_hash')
            ->unique()
        ;
        $registry
            ->getTableSchema($entity)
            ->index(['user_id'])
            ->setName('api_access_tokens_index_user_id')
        ;
        $registry
            ->getTableSchema($entity)
            ->index(['token_prefix'])
            ->setName('api_access_tokens_index_token_prefix')
        ;
        $registry
            ->getTableSchema($entity)
            ->index(['revoked_at', 'expires_at'])
            ->setName('api_access_tokens_index_revoked_expires')
        ;

        return $registry;
    }
}
