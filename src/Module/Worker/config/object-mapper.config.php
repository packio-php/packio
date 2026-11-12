<?php

declare(strict_types=1);

use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Packio\Worker\Api\Dto\JobLogDto;
use Packio\Worker\Domain\Entity\JobLog;

return [
    'object_mapper' => [
        'mappings' => [
            JobLog::class . '->' . JobLogDto::class => [
                'source'         => JobLog::class,
                'target'         => JobLogDto::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'id'        => [
                        'method'      => 'getIdentifier',
                        'transformer' => UuidInterfaceToStringTransformer::class,
                    ],
                    'jobId'     => [
                        'method' => 'jobId',
                    ],
                    'level'     => [
                        'method' => 'level',
                    ],
                    'message'   => [
                        'method' => 'message',
                    ],
                    'context'   => [
                        'method' => 'context',
                    ],
                    'createdAt' => [
                        'method'      => 'getCreatedAt',
                        'transformer' => ChronosToAtomStringTransformer::class,
                    ],
                ],
                'ignored_source' => [],
            ],
        ],
    ],
];
