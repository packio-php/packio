<?php

declare(strict_types=1);

use Common\Infrastructure\Cli\ObjectMapperWarmupCommand;
use Common\Infrastructure\ObjectMapper\ObjectMapperFactory;
use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\EmptyStringToNullTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToNullableAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Psr\Log\LoggerInterface;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;
use Sirix\ObjectMapper\Contract\WarmableObjectMapperInterface;
use Sirix\ObjectMapper\Runtime\ObjectMapper;

return [
    'dependencies'               => [
        'aliases'    => [
            ObjectMapper::class                  => ObjectMapperInterface::class,
            WarmableObjectMapperInterface::class => ObjectMapperInterface::class,
        ],
        'factories'  => [
            ObjectMapperInterface::class     => ObjectMapperFactory::class,
            ObjectMapperWarmupCommand::class => ConfigAbstractFactory::class,
        ],
        'invokables' => [
            EmptyStringToNullTransformer::class                   => EmptyStringToNullTransformer::class,
            ChronosToAtomStringTransformer::class                 => ChronosToAtomStringTransformer::class,
            NullableChronosToAtomStringTransformer::class         => NullableChronosToAtomStringTransformer::class,
            NullableChronosToNullableAtomStringTransformer::class => NullableChronosToNullableAtomStringTransformer::class,
            UuidInterfaceToStringTransformer::class               => UuidInterfaceToStringTransformer::class,
        ],
    ],
    'object_mapper'              => [
        'transformers' => [
            EmptyStringToNullTransformer::class,
            ChronosToAtomStringTransformer::class,
            NullableChronosToAtomStringTransformer::class,
            NullableChronosToNullableAtomStringTransformer::class,
            UuidInterfaceToStringTransformer::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        ObjectMapperWarmupCommand::class => [
            ObjectMapper::class,
            LoggerInterface::class,
        ],
    ],
];
