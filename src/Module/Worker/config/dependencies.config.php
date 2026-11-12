<?php

declare(strict_types=1);

use Common\Runtime\RuntimeResetterInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Worker\Api\JobApiFacadeInterface;
use Packio\Worker\Application\Command\WorkerMaintenanceService;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Application\JobQueueService;
use Packio\Worker\Application\JobRestartService;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Packio\Worker\Infrastructure\Cli\WorkerCleanupCommand;
use Packio\Worker\Infrastructure\Cli\WorkerRunCommand;
use Packio\Worker\Infrastructure\Facade\JobApiFacade;
use Packio\Worker\Infrastructure\Factory\JobProcessorFactory;
use Packio\Worker\Infrastructure\Mapper\JobDtoMapper;
use Packio\Worker\Infrastructure\Persistence\Cycle\JobLogRepository;
use Packio\Worker\Infrastructure\Persistence\Cycle\JobRepository;
use Psr\Log\LoggerInterface;
use Sirix\Cycle\Factory\RepositoryFactory;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Reporter\SentryReporter;

return [
    'dependencies'               => [
        'aliases'    => [
            JobRepositoryInterface::class    => JobRepository::class,
            JobLogRepositoryInterface::class => JobLogRepository::class,
            JobApiFacadeInterface::class     => JobApiFacade::class,
        ],
        'invokables' => [
            JobDtoMapper::class => JobDtoMapper::class,
        ],
        'factories'  => [
            JobRepository::class            => RepositoryFactory::class,
            JobLogRepository::class         => RepositoryFactory::class,
            JobQueueService::class          => ConfigAbstractFactory::class,
            JobRestartService::class        => ConfigAbstractFactory::class,
            JobProcessor::class             => JobProcessorFactory::class,
            WorkerRunCommand::class         => ConfigAbstractFactory::class,
            WorkerCleanupCommand::class     => ConfigAbstractFactory::class,
            WorkerMaintenanceService::class => ConfigAbstractFactory::class,
            JobApiFacade::class             => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        WorkerRunCommand::class         => [
            JobRepositoryInterface::class,
            JobProcessor::class,
            LoggerInterface::class,
            RuntimeResetterInterface::class,
            SentryLifecycle::class,
            SentryReporter::class,
        ],
        WorkerCleanupCommand::class     => [
            WorkerMaintenanceService::class,
        ],
        WorkerMaintenanceService::class => [
            JobRepositoryInterface::class,
            JobLogRepositoryInterface::class,
        ],
        JobQueueService::class          => [
            JobRepositoryInterface::class,
        ],
        JobRestartService::class        => [
            JobRepositoryInterface::class,
            JobLogRepositoryInterface::class,
        ],
        JobApiFacade::class             => [
            JobRepositoryInterface::class,
            JobLogRepositoryInterface::class,
            JobQueueService::class,
            JobRestartService::class,
            JobDtoMapper::class,
            ObjectMapperInterface::class,
        ],
    ],
];
