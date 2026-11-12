<?php

declare(strict_types=1);

use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Packio\Source\Api\Dto\ProviderResourceDto;
use Packio\Source\Api\Dto\SourceOAuthAccountDto;
use Packio\Source\Api\Dto\SourceProviderInstanceDependenciesDto;
use Packio\Source\Api\Dto\WebhookRegistrationResultDto;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceProviderInstanceDependencies;
use Packio\Source\Domain\Entity\SourceOAuthAccount;

return [
    'object_mapper' => [
        'mappings' => [
            ProviderResourceData::class . '->' . ProviderResourceDto::class                                 => [
                'source'         => ProviderResourceData::class,
                'target'         => ProviderResourceDto::class,
                'rules'          => [
                    'provider'     => [
                        'property' => 'type',
                    ],
                    'canonicalUrl' => [
                        'property' => 'url',
                    ],
                    'defaultRef'   => [
                        'property' => 'defaultBranch',
                    ],
                ],
                'ignored_source' => ['description'],
            ],
            SourceOAuthAccount::class . '->' . SourceOAuthAccountDto::class                                 => [
                'source'         => SourceOAuthAccount::class,
                'target'         => SourceOAuthAccountDto::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'id'               => [
                        'method'      => 'getIdentifier',
                        'transformer' => UuidInterfaceToStringTransformer::class,
                    ],
                    'provider'         => [
                        'method' => 'provider',
                    ],
                    'providerUsername' => [
                        'method' => 'providerUsername',
                    ],
                    'providerEmail'    => [
                        'method' => 'providerEmail',
                    ],
                ],
                'ignored_source' => [],
            ],
            SourceProviderInstanceDependencies::class . '->' . SourceProviderInstanceDependenciesDto::class => [
                'source'         => SourceProviderInstanceDependencies::class,
                'target'         => SourceProviderInstanceDependenciesDto::class,
                'rules'          => [],
                'ignored_source' => [],
            ],
            WebhookRegistrationResult::class . '->' . WebhookRegistrationResultDto::class                   => [
                'source'         => WebhookRegistrationResult::class,
                'target'         => WebhookRegistrationResultDto::class,
                'rules'          => [],
                'ignored_source' => [],
            ],
        ],
    ],
];
