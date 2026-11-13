<?php

declare(strict_types=1);

use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Application\Command\AuditCommandService;
use Packio\Audit\Application\Query\AuditQueryService;
use Packio\Audit\Domain\Repository\AuditRepositoryInterface;
use Packio\Audit\Infrastructure\Cli\AuditLogPruneCommand;
use Packio\Audit\Infrastructure\Facade\AuditApiFacade;
use Packio\Audit\Infrastructure\Persistence\Cycle\AuditRepository;
use Sirix\Cycle\Factory\RepositoryFactory;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

return [
    'dependencies'               => [
        'aliases'    => [
            AuditRepositoryInterface::class => AuditRepository::class,
            AuditApiFacadeInterface::class  => AuditApiFacade::class,
        ],
        'factories'  => [
            AuditApiFacade::class       => ConfigAbstractFactory::class,
            AuditCommandService::class  => ConfigAbstractFactory::class,
            AuditLogPruneCommand::class => ConfigAbstractFactory::class,
            AuditQueryService::class    => ConfigAbstractFactory::class,
            AuditRepository::class      => RepositoryFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        AuditApiFacade::class       => [
            AuditCommandService::class,
            AuditQueryService::class,
            ObjectMapperInterface::class,
        ],
        AuditCommandService::class  => [
            AuditRepositoryInterface::class,
        ],
        AuditLogPruneCommand::class => [
            AuditRepositoryInterface::class,
            'config',
        ],
        AuditQueryService::class    => [
            AuditRepositoryInterface::class,
        ],
    ],
];
