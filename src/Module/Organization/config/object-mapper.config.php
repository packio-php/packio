<?php

declare(strict_types=1);

use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Infrastructure\ObjectMapper\InvitationTokenTransformer;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;

return [
    'object_mapper' => [
        'mappings'     => [
            Organization::class . '->' . OrganizationDto::class                           => [
                'source'         => Organization::class,
                'target'         => OrganizationDto::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'id'              => [
                        'method'      => 'getIdentifier',
                        'transformer' => UuidInterfaceToStringTransformer::class,
                    ],
                    'slug'            => [
                        'method' => 'slug',
                    ],
                    'name'            => [
                        'method' => 'name',
                    ],
                    'anonymousAccess' => [
                        'method' => 'anonymousAccess',
                    ],
                    'active'          => [
                        'method' => 'isActive',
                    ],
                    'status'          => [
                        'method' => 'status',
                    ],
                    'createdAt'       => [
                        'method'      => 'getCreatedAt',
                        'transformer' => ChronosToAtomStringTransformer::class,
                    ],
                    'updatedAt'       => [
                        'method'      => 'getUpdatedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                ],
                'ignored_source' => [],
            ],
            OrganizationMember::class . '->' . OrganizationMemberDto::class               => [
                'source'         => OrganizationMember::class,
                'target'         => OrganizationMemberDto::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'id'             => [
                        'method'      => 'getIdentifier',
                        'transformer' => UuidInterfaceToStringTransformer::class,
                    ],
                    'organizationId' => [
                        'method' => 'organizationId',
                    ],
                    'userId'         => [
                        'method' => 'userId',
                    ],
                    'role'           => [
                        'method' => 'role',
                    ],
                    'createdAt'      => [
                        'method'      => 'getCreatedAt',
                        'transformer' => ChronosToAtomStringTransformer::class,
                    ],
                    'updatedAt'      => [
                        'method'      => 'getUpdatedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                ],
                'ignored_source' => [],
            ],
            OrganizationInvitation::class . '->' . OrganizationInvitationDto::class       => [
                'source'         => OrganizationInvitation::class,
                'target'         => OrganizationInvitationDto::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'id'               => [
                        'method'      => 'getIdentifier',
                        'transformer' => UuidInterfaceToStringTransformer::class,
                    ],
                    'organizationId'   => [
                        'method' => 'organizationId',
                    ],
                    'invitedByUserId'  => [
                        'method' => 'invitedByUserId',
                    ],
                    'token'            => [
                        'method'      => 'token',
                        'transformer' => InvitationTokenTransformer::class,
                    ],
                    'role'             => [
                        'method' => 'role',
                    ],
                    'status'           => [
                        'method' => 'status',
                    ],
                    'acceptedByUserId' => [
                        'method' => 'acceptedByUserId',
                    ],
                    'createdAt'        => [
                        'method'      => 'getCreatedAt',
                        'transformer' => ChronosToAtomStringTransformer::class,
                    ],
                    'updatedAt'        => [
                        'method'      => 'getUpdatedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                    'expiresAt'        => [
                        'method'      => 'expiresAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                    'acceptedAt'       => [
                        'method'      => 'acceptedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                    'revokedAt'        => [
                        'method'      => 'revokedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                ],
                'ignored_source' => [],
            ],
        ],
        'transformers' => [
            InvitationTokenTransformer::class,
        ],
    ],
];
