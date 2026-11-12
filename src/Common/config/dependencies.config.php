<?php

declare(strict_types=1);

use Common\Provider\SourceProviderMetadataCatalog;
use Common\Runtime\CompositeRuntimeResetter;
use Common\Runtime\CompositeRuntimeResetterFactory;
use Common\Runtime\CycleOrmHeapResetter;
use Common\Runtime\LoggerRuntimeResetter;
use Common\Runtime\PhpSessionRuntimeResetter;
use Common\Runtime\RuntimeResetterInterface;
use Cycle\ORM\ORMInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Psr\Log\LoggerInterface;
use Yiisoft\Validator\Validator;
use Yiisoft\Validator\ValidatorInterface;

return [
    'dependencies'               => [
        'invokables' => [
            SourceProviderMetadataCatalog::class => SourceProviderMetadataCatalog::class,
            PhpSessionRuntimeResetter::class     => PhpSessionRuntimeResetter::class,
        ],
        'factories'  => [
            CompositeRuntimeResetter::class  => CompositeRuntimeResetterFactory::class,
            CycleOrmHeapResetter::class      => ConfigAbstractFactory::class,
            Validator::class                 => ConfigAbstractFactory::class,
            LoggerRuntimeResetter::class     => ConfigAbstractFactory::class,
        ],
        'aliases'    => [
            RuntimeResetterInterface::class => CompositeRuntimeResetter::class,
            ValidatorInterface::class       => Validator::class,
        ],
    ],
    'runtime_resetters'          => [
        CycleOrmHeapResetter::class,
        LoggerRuntimeResetter::class,
        PhpSessionRuntimeResetter::class,
    ],
    ConfigAbstractFactory::class => [
        CycleOrmHeapResetter::class  => [
            ORMInterface::class,
        ],
        LoggerRuntimeResetter::class => [
            LoggerInterface::class,
        ],
        Validator::class             => [],
    ],
];
