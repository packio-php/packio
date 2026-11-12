<?php

declare(strict_types=1);

use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToNullableAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Api\Dto\IssuedApiAccessTokenDto;
use Packio\Identity\Api\Dto\IssuedRegistryAccessTokenDto;
use Packio\Identity\Api\Dto\PasswordResetResultDto;
use Packio\Identity\Api\Dto\RegistryAccessTokenDto;
use Packio\Identity\Application\Result\ApiAccessTokenResult;
use Packio\Identity\Application\Result\IdentityUserResult;
use Packio\Identity\Application\Result\IssuedApiAccessToken;
use Packio\Identity\Application\Result\IssuedRegistryAccessToken;
use Packio\Identity\Application\Result\PasswordResetResult;
use Packio\Identity\Application\Result\RegistryAccessTokenResult;
use Packio\Identity\Domain\Entity\ApiAccessToken;
use Packio\Identity\Domain\Entity\User;
use Packio\Shared\Api\Dto\UserDto;

return [
    'object_mapper' => [
        'mappings' => [
            ApiAccessToken::class . '->' . ApiAccessTokenResult::class                     => [
                'source'         => ApiAccessToken::class,
                'target'         => ApiAccessTokenResult::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'id'          => [
                        'method'      => 'getIdentifier',
                        'transformer' => UuidInterfaceToStringTransformer::class,
                    ],
                    'userId'      => [
                        'method' => 'userId',
                    ],
                    'name'        => [
                        'method' => 'name',
                    ],
                    'tokenPrefix' => [
                        'method' => 'tokenPrefix',
                    ],
                    'scopes'      => [
                        'method' => 'scopes',
                    ],
                    'createdAt'   => [
                        'method'      => 'getCreatedAt',
                        'transformer' => ChronosToAtomStringTransformer::class,
                    ],
                    'updatedAt'   => [
                        'method'      => 'getUpdatedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                    'lastUsedAt'  => [
                        'method'      => 'lastUsedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                    'expiresAt'   => [
                        'method'      => 'expiresAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                    'revokedAt'   => [
                        'method'      => 'revokedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                ],
                'ignored_source' => [],
            ],
            User::class . '->' . UserDto::class                                            => [
                'source'         => User::class,
                'target'         => UserDto::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'id'              => [
                        'method'      => 'getIdentifier',
                        'transformer' => UuidInterfaceToStringTransformer::class,
                    ],
                    'email'           => [
                        'method' => 'email',
                    ],
                    'displayName'     => [
                        'method' => 'displayName',
                    ],
                    'roles'           => [
                        'method' => 'roles',
                    ],
                    'createdAt'       => [
                        'method'      => 'getCreatedAt',
                        'transformer' => ChronosToAtomStringTransformer::class,
                    ],
                    'updatedAt'       => [
                        'method'      => 'getUpdatedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                    'hasPassword'     => [
                        'method' => 'hasPassword',
                    ],
                    'isEmailVerified' => [
                        'method' => 'isEmailVerified',
                    ],
                    'isBanned'        => [
                        'method' => 'isBanned',
                    ],
                    'banReason'       => [
                        'method' => 'banReason',
                    ],
                    'bannedAt'        => [
                        'method'      => 'bannedAt',
                        'transformer' => NullableChronosToNullableAtomStringTransformer::class,
                    ],
                    'bannedByUserId'  => [
                        'method' => 'bannedByUserId',
                    ],
                ],
                'ignored_source' => [],
            ],
            IdentityUserResult::class . '->' . UserDto::class                              => [
                'source'         => IdentityUserResult::class,
                'target'         => UserDto::class,
                'rules'          => [],
                'ignored_source' => [],
            ],
            ApiAccessTokenResult::class . '->' . ApiAccessTokenDto::class                  => [
                'source'         => ApiAccessTokenResult::class,
                'target'         => ApiAccessTokenDto::class,
                'rules'          => [],
                'ignored_source' => [],
            ],
            IssuedRegistryAccessToken::class . '->' . IssuedRegistryAccessTokenDto::class  => [
                'source'         => IssuedRegistryAccessToken::class,
                'target'         => IssuedRegistryAccessTokenDto::class,
                'rules'          => [],
                'ignored_source' => [],
            ],
            RegistryAccessTokenResult::class . '->' . RegistryAccessTokenDto::class        => [
                'source'         => RegistryAccessTokenResult::class,
                'target'         => RegistryAccessTokenDto::class,
                'rules'          => [],
                'ignored_source' => [],
            ],
            PasswordResetResult::class . '->' . PasswordResetResultDto::class              => [
                'source'         => PasswordResetResult::class,
                'target'         => PasswordResetResultDto::class,
                'rules'          => [],
                'ignored_source' => [],
            ],
            IssuedApiAccessToken::class . '->' . IssuedApiAccessTokenDto::class            => [
                'source'         => IssuedApiAccessToken::class,
                'target'         => IssuedApiAccessTokenDto::class,
                'rules'          => [
                    'token' => [
                        'property' => 'token',
                        'nested'   => ApiAccessTokenDto::class,
                    ],
                ],
                'ignored_source' => [],
            ],
        ],
    ],
];
