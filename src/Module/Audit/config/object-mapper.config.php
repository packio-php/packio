<?php

declare(strict_types=1);

use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Audit\Domain\Entity\AuditEntry;

return [
    'object_mapper' => [
        'mappings' => [
            AuditEntry::class . '->' . AuditEntryDto::class => [
                'source'         => AuditEntry::class,
                'target'         => AuditEntryDto::class,
                'source_match'   => 'cycle_proxy',
                'rules'          => [
                    'id'          => [
                        'method'      => 'getIdentifier',
                        'transformer' => UuidInterfaceToStringTransformer::class,
                    ],
                    'event'       => [
                        'method' => 'event',
                    ],
                    'occurredAt'  => [
                        'method'      => 'occurredAt',
                        'transformer' => ChronosToAtomStringTransformer::class,
                    ],
                    'context'     => [
                        'method' => 'context',
                    ],
                    'actorId'     => [
                        'method' => 'actorId',
                    ],
                    'subjectType' => [
                        'method' => 'subjectType',
                    ],
                    'subjectId'   => [
                        'method' => 'subjectId',
                    ],
                ],
                'ignored_source' => [],
            ],
        ],
    ],
];
