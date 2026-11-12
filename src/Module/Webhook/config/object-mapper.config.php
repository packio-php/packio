<?php

declare(strict_types=1);

use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToNullableAtomStringTransformer;
use Packio\Webhook\Api\Dto\WebhookEventDto;
use Packio\Webhook\Domain\Entity\WebhookEvent;

return [
    'object_mapper' => [
        'mappings' => [
            WebhookEvent::class . '->' . WebhookEventDto::class => [
                'source'         => WebhookEvent::class,
                'target'         => WebhookEventDto::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'sourceId'       => [
                        'method' => 'sourceId',
                    ],
                    'provider'       => [
                        'method' => 'provider',
                    ],
                    'deliveryId'     => [
                        'method' => 'deliveryId',
                    ],
                    'event'          => [
                        'method' => 'event',
                    ],
                    'status'         => [
                        'method' => 'status',
                    ],
                    'message'        => [
                        'method' => 'message',
                    ],
                    'createdAt'      => [
                        'method'      => 'getCreatedAt',
                        'transformer' => ChronosToAtomStringTransformer::class,
                    ],
                    'processedAt'    => [
                        'method'      => 'processedAt',
                        'transformer' => NullableChronosToNullableAtomStringTransformer::class,
                    ],
                    'scheduledJobId' => [
                        'method' => 'scheduledJobId',
                    ],
                    'reprocessCount' => [
                        'method' => 'reprocessCount',
                    ],
                ],
                'ignored_source' => [],
            ],
        ],
    ],
];
