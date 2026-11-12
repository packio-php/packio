<?php

declare(strict_types=1);

use Common\Infrastructure\Process\LimitedProcessRunner;
use Common\Infrastructure\Resource\ResourceLimits;
use Common\Infrastructure\Resource\ResourceLimitsFactory;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;

return [
    'dependencies'               => [
        'factories' => [
            ResourceLimits::class             => ResourceLimitsFactory::class,
            LimitedProcessRunner::class       => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        LimitedProcessRunner::class       => [ResourceLimits::class],
    ],
];
