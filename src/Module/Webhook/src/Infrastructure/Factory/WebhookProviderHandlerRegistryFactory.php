<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Factory;

use Packio\Webhook\Infrastructure\Config\WebhookProviderHandlerConfigCatalog;
use Packio\Webhook\Infrastructure\Webhook\BitbucketWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\CodebergWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\GithubWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\GitlabWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerRegistry;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;

final class WebhookProviderHandlerRegistryFactory
{
    public function __invoke(ContainerInterface $container): WebhookProviderHandlerRegistry
    {
        $containerResolver                   = ContainerResolver::forFactory($container, self::class);
        $webhookProviderHandlerConfigCatalog = WebhookProviderHandlerConfigCatalog::fromConfigReader(
            ConfigReader::fromContainer($containerResolver, 'config.sources.webhooks'),
        );

        return new WebhookProviderHandlerRegistry([
            new GithubWebhookProviderHandler($webhookProviderHandlerConfigCatalog->github()),
            new BitbucketWebhookProviderHandler($webhookProviderHandlerConfigCatalog->bitbucket()),
            new CodebergWebhookProviderHandler($webhookProviderHandlerConfigCatalog->codeberg()),
            new GitlabWebhookProviderHandler($webhookProviderHandlerConfigCatalog->gitlab()),
            new GitlabWebhookProviderHandler($webhookProviderHandlerConfigCatalog->gitlabSelfHosted()),
        ]);
    }
}
