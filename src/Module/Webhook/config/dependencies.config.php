<?php

declare(strict_types=1);

use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Webhook\Application\Command\WebhookCommandService;
use Packio\Webhook\Application\Port\SourcePort;
use Packio\Webhook\Application\Query\WebhookQueryService;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Packio\Webhook\Infrastructure\Adapter\SourceApiFacadeAdapter;
use Packio\Webhook\Infrastructure\Cli\WebhookEventsPruneCommand;
use Packio\Webhook\Infrastructure\Facade\WebhookApiFacade;
use Packio\Webhook\Infrastructure\Factory\WebhookProviderHandlerRegistryFactory;
use Packio\Webhook\Infrastructure\Handler\ReceiveWebhookHandler;
use Packio\Webhook\Infrastructure\Handler\WebhookDeliveryReprocessHandler;
use Packio\Webhook\Infrastructure\Persistence\Cycle\WebhookEventRepository;
use Packio\Webhook\Infrastructure\Webhook\WebhookDispatcher;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerRegistry;
use Psr\Log\LoggerInterface;
use Sirix\Cycle\Factory\RepositoryFactory;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

return [
    'dependencies'               => [
        'aliases'    => [
            SourcePort::class                      => SourceApiFacadeAdapter::class,
            WebhookEventRepositoryInterface::class => WebhookEventRepository::class,
            WebhookApiFacadeInterface::class       => WebhookApiFacade::class,
        ],
        'factories'  => [
            SourceApiFacadeAdapter::class          => ConfigAbstractFactory::class,
            WebhookDispatcher::class               => ConfigAbstractFactory::class,
            WebhookApiFacade::class                => ConfigAbstractFactory::class,
            WebhookCommandService::class           => ConfigAbstractFactory::class,
            WebhookProviderHandlerRegistry::class  => WebhookProviderHandlerRegistryFactory::class,
            WebhookQueryService::class             => ConfigAbstractFactory::class,
            WebhookEventRepository::class          => RepositoryFactory::class,
            WebhookEventsPruneCommand::class       => ConfigAbstractFactory::class,
            ReceiveWebhookHandler::class           => ConfigAbstractFactory::class,
            WebhookDeliveryReprocessHandler::class => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        WebhookApiFacade::class                => [
            WebhookCommandService::class,
            WebhookDispatcher::class,
            WebhookQueryService::class,
            SourcePort::class,
            ObjectMapperInterface::class,
            LoggerInterface::class,
        ],
        SourceApiFacadeAdapter::class          => [SourceApiFacadeInterface::class],
        WebhookCommandService::class           => [
            WebhookEventRepositoryInterface::class,
        ],
        WebhookDispatcher::class               => [WebhookCommandService::class, WebhookProviderHandlerRegistry::class],
        WebhookQueryService::class             => [
            WebhookEventRepositoryInterface::class,
        ],
        WebhookEventsPruneCommand::class       => [WebhookEventRepositoryInterface::class],
        ReceiveWebhookHandler::class           => [
            WebhookApiFacadeInterface::class,
        ],
        WebhookDeliveryReprocessHandler::class => [
            WebhookApiFacadeInterface::class,
            SourceApiFacadeInterface::class,
            SourcePolicy::class,
            InertiaNotificationMessenger::class,
        ],
    ],
];
