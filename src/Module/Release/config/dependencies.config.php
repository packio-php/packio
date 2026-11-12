<?php

declare(strict_types=1);

use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Release\Application\Command\ReleaseCommandService;
use Packio\Release\Application\Command\ReleaseMaintenanceService;
use Packio\Release\Application\Query\ReleaseQueryService;
use Packio\Release\Application\Service\ReleaseRetentionService;
use Packio\Release\Application\Service\ReleaseVersionComparator;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use Packio\Release\Infrastructure\Cli\ReleaseOrphansCleanupCommand;
use Packio\Release\Infrastructure\Facade\ReleaseApiFacade;
use Packio\Release\Infrastructure\Handler\ReleaseActivityHandler;
use Packio\Release\Infrastructure\Mapper\ReleaseDtoMapper;
use Packio\Release\Infrastructure\Persistence\Cycle\ReleaseRepository;
use Sirix\Cycle\Factory\RepositoryFactory;

return [
    'dependencies'               => [
        'aliases'    => [
            ReleaseRepositoryInterface::class => ReleaseRepository::class,
            ReleaseApiFacadeInterface::class  => ReleaseApiFacade::class,
        ],
        'invokables' => [
            ReleaseDtoMapper::class         => ReleaseDtoMapper::class,
            ReleaseVersionComparator::class => ReleaseVersionComparator::class,
        ],
        'factories'  => [
            ReleaseRepository::class            => RepositoryFactory::class,
            ReleaseCommandService::class        => ConfigAbstractFactory::class,
            ReleaseMaintenanceService::class    => ConfigAbstractFactory::class,
            ReleaseQueryService::class          => ConfigAbstractFactory::class,
            ReleaseRetentionService::class      => ConfigAbstractFactory::class,
            ReleaseApiFacade::class             => ConfigAbstractFactory::class,
            ReleaseActivityHandler::class       => ConfigAbstractFactory::class,
            ReleaseOrphansCleanupCommand::class => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        ReleaseCommandService::class        => [
            ReleaseRepositoryInterface::class,
            ReleaseMaintenanceService::class,
        ],
        ReleaseMaintenanceService::class    => [
            ReleaseRepositoryInterface::class,
        ],
        ReleaseQueryService::class          => [
            ReleaseRepositoryInterface::class,
            ReleaseVersionComparator::class,
        ],
        ReleaseRetentionService::class      => [
            ReleaseRepositoryInterface::class,
            ReleaseVersionComparator::class,
        ],
        ReleaseApiFacade::class             => [
            ReleaseCommandService::class,
            ReleaseQueryService::class,
            ReleaseDtoMapper::class,
            ReleaseRetentionService::class,
        ],
        ReleaseOrphansCleanupCommand::class => [
            ReleaseRepositoryInterface::class,
            ReleaseApiFacadeInterface::class,
            PackageApiFacadeInterface::class,
            ArchiveStorageCleanupApiFacadeInterface::class,
        ],
        ReleaseActivityHandler::class       => [
            ReleaseApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
    ],
];
