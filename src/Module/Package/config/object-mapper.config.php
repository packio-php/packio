<?php

declare(strict_types=1);

use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToAtomStringTransformer;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Domain\Entity\Package;

return [
    'object_mapper' => [
        'mappings' => [
            Package::class . '->' . PackageDto::class => [
                'source'         => Package::class,
                'target'         => PackageDto::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'name'           => [
                        'method' => 'name',
                    ],
                    'type'           => [
                        'method' => 'type',
                    ],
                    'description'    => [
                        'method' => 'description',
                    ],
                    'visibility'     => [
                        'method' => 'visibility',
                    ],
                    'sourceType'     => [
                        'method' => 'sourceType',
                    ],
                    'sourceUrl'      => [
                        'method' => 'sourceUrl',
                    ],
                    'createdAt'      => [
                        'method'      => 'getCreatedAt',
                        'transformer' => ChronosToAtomStringTransformer::class,
                    ],
                    'updatedAt'      => [
                        'method'      => 'getUpdatedAt',
                        'transformer' => NullableChronosToAtomStringTransformer::class,
                    ],
                    'organizationId' => [
                        'method' => 'organizationId',
                    ],
                    'active'         => [
                        'method' => 'isActive',
                    ],
                    'readmeHtml'     => [
                        'method' => 'readmeHtml',
                    ],
                    'sourceId'       => [
                        'method' => 'sourceId',
                    ],
                    'downloadCount'  => [
                        'method' => 'downloadCount',
                    ],
                ],
                'ignored_source' => [],
            ],
        ],
    ],
];
