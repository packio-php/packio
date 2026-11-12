<?php

declare(strict_types=1);

use Cycle\ORM\ORMInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Package\Api\Policy\PackagePolicy;
use Packio\Package\Application\Command\DownloadCountTransactionInterface;
use Packio\Package\Application\Command\PackageCommandService;
use Packio\Package\Application\Command\PackageMaintenanceService;
use Packio\Package\Application\Deletion\PackageDeletionScheduler;
use Packio\Package\Application\Deletion\PackageDeletionService;
use Packio\Package\Application\Listener\IncrementDownloadCountListener;
use Packio\Package\Application\Port\JobSchedulerPort;
use Packio\Package\Application\Port\PackageArchiveCleanupPort;
use Packio\Package\Application\Port\PackageReleaseCleanupPort;
use Packio\Package\Application\Port\ReleaseServicePort;
use Packio\Package\Application\Query\PackageQueryService;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use Packio\Package\Infrastructure\Adapter\RegistryArchiveCleanupAdapter;
use Packio\Package\Infrastructure\Adapter\ReleaseApiFacadeAdapter;
use Packio\Package\Infrastructure\Adapter\Worker\IncrementDownloadCountJobHandler;
use Packio\Package\Infrastructure\Adapter\Worker\PackageDeleteJobHandler;
use Packio\Package\Infrastructure\Adapter\WorkerJobSchedulerAdapter;
use Packio\Package\Infrastructure\Facade\PackageApiFacade;
use Packio\Package\Infrastructure\Handler\PackageDetailsHandler;
use Packio\Package\Infrastructure\Handler\PackagesHandler;
use Packio\Package\Infrastructure\Handler\SourcePackageActivityHandler;
use Packio\Package\Infrastructure\Page\PackageDetailsPageDataBuilder;
use Packio\Package\Infrastructure\Page\PackagesPageDataBuilder;
use Packio\Package\Infrastructure\Persistence\Cycle\DownloadCountTransaction;
use Packio\Package\Infrastructure\Persistence\Cycle\PackageRepository;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Log\LoggerInterface;
use Sirix\Cycle\Factory\RepositoryFactory;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

return [
    'dependencies'               => [
        'aliases'    => [
            PackageRepositoryInterface::class        => PackageRepository::class,
            PackageApiFacadeInterface::class         => PackageApiFacade::class,
            DownloadCountTransactionInterface::class => DownloadCountTransaction::class,
            JobSchedulerPort::class                  => WorkerJobSchedulerAdapter::class,
            ReleaseServicePort::class                => ReleaseApiFacadeAdapter::class,
            PackageReleaseCleanupPort::class         => ReleaseApiFacadeAdapter::class,
            PackageArchiveCleanupPort::class         => RegistryArchiveCleanupAdapter::class,
        ],
        'invokables' => [
            PackagePolicy::class => PackagePolicy::class,
        ],
        'factories'  => [
            PackageRepository::class                      => RepositoryFactory::class,
            PackageQueryService::class                    => ConfigAbstractFactory::class,
            PackageCommandService::class                  => ConfigAbstractFactory::class,
            PackageMaintenanceService::class              => ConfigAbstractFactory::class,
            PackageDeletionService::class                 => ConfigAbstractFactory::class,
            PackageDeletionScheduler::class               => ConfigAbstractFactory::class,
            PackageApiFacade::class                       => ConfigAbstractFactory::class,
            ReleaseApiFacadeAdapter::class                => ConfigAbstractFactory::class,
            RegistryArchiveCleanupAdapter::class          => ConfigAbstractFactory::class,
            DownloadCountTransaction::class               => ConfigAbstractFactory::class,
            WorkerJobSchedulerAdapter::class              => ConfigAbstractFactory::class,
            PackagesHandler::class                        => ConfigAbstractFactory::class,
            PackagesPageDataBuilder::class                => ConfigAbstractFactory::class,
            PackageDetailsPageDataBuilder::class          => ConfigAbstractFactory::class,
            PackageDetailsHandler::class                  => ConfigAbstractFactory::class,
            IncrementDownloadCountJobHandler::class       => ConfigAbstractFactory::class,
            PackageDeleteJobHandler::class                => ConfigAbstractFactory::class,
            IncrementDownloadCountListener::class         => ConfigAbstractFactory::class,
            SourcePackageActivityHandler::class           => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        PackageQueryService::class                    => [
            PackageRepositoryInterface::class,
        ],
        ReleaseApiFacadeAdapter::class                => [
            ReleaseApiFacadeInterface::class,
        ],
        RegistryArchiveCleanupAdapter::class          => [
            ArchiveStorageCleanupApiFacadeInterface::class,
        ],
        DownloadCountTransaction::class               => [
            ORMInterface::class,
        ],
        WorkerJobSchedulerAdapter::class              => [
            JobApiFacadeInterface::class,
        ],
        PackageCommandService::class                  => [
            PackageRepositoryInterface::class,
            PackageMaintenanceService::class,
            ReleaseServicePort::class,
            PackageDeletionService::class,
            DownloadCountTransactionInterface::class,
        ],
        PackageMaintenanceService::class              => [
            PackageRepositoryInterface::class,
        ],
        PackageApiFacade::class                       => [
            PackageCommandService::class,
            PackageQueryService::class,
            ObjectMapperInterface::class,
            PackageDeletionScheduler::class,
        ],
        PackageDeletionScheduler::class               => [
            PackageQueryService::class,
            JobSchedulerPort::class,
        ],
        PackagesPageDataBuilder::class                => [
            PackageApiFacadeInterface::class,
            ReleaseApiFacadeInterface::class,
            SourceApiFacadeInterface::class,
        ],
        PackagesHandler::class                        => [
            PackagesPageDataBuilder::class,
        ],
        PackageDetailsPageDataBuilder::class          => [
            ReleaseApiFacadeInterface::class,
            SourceApiFacadeInterface::class,
            PackagePolicy::class,
        ],
        PackageDetailsHandler::class                  => [
            PackageApiFacadeInterface::class,
            PackageDetailsPageDataBuilder::class,
            InertiaNotificationMessenger::class,
        ],
        IncrementDownloadCountJobHandler::class       => [
            PackageCommandService::class,
        ],
        PackageDeleteJobHandler::class                => [
            PackageDeletionService::class,
        ],
        PackageDeletionService::class                 => [
            PackageMaintenanceService::class,
            PackageArchiveCleanupPort::class,
            PackageReleaseCleanupPort::class,
        ],
        IncrementDownloadCountListener::class         => [
            JobSchedulerPort::class,
            LoggerInterface::class,
        ],
        SourcePackageActivityHandler::class           => [
            SourceApiFacadeInterface::class,
            PackageApiFacadeInterface::class,
            SourcePolicy::class,
            InertiaNotificationMessenger::class,
        ],
    ],

    'worker'                     => [
        'job_handlers' => [
            'increment.download.count'    => IncrementDownloadCountJobHandler::class,
            PackageDeleteJobHandler::TYPE => PackageDeleteJobHandler::class,
            // TODO: Remove this legacy alias after old job logs/queued records are cleaned up.
            'increment.install.count'     => IncrementDownloadCountJobHandler::class,
        ],
    ],
];
