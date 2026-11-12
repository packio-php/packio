<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Infrastructure\Factory;

use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Infrastructure\Config\WebhookProviderHandlerConfigCatalog;
use Packio\Webhook\Infrastructure\Factory\WebhookProviderHandlerRegistryFactory;
use Packio\Webhook\Infrastructure\Webhook\BitbucketWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\CodebergWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\GithubWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\GitlabWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function json_encode;

#[CoversClass(WebhookProviderHandlerRegistryFactory::class)]
#[CoversClass(WebhookProviderHandlerConfigCatalog::class)]
#[CoversClass(WebhookProviderHandlerRegistry::class)]
final class WebhookProviderHandlerRegistryFactoryTest extends TestCase
{
    #[Test]
    public function createsRegistryWithAllIncomingWebhookProviders(): void
    {
        $registry = (new WebhookProviderHandlerRegistryFactory())($this->container([]));

        self::assertInstanceOf(GithubWebhookProviderHandler::class, $registry->handler('github'));
        self::assertInstanceOf(BitbucketWebhookProviderHandler::class, $registry->handler('bitbucket'));
        self::assertInstanceOf(CodebergWebhookProviderHandler::class, $registry->handler('codeberg'));
        self::assertInstanceOf(GitlabWebhookProviderHandler::class, $registry->handler('gitlab'));
        self::assertInstanceOf(GitlabWebhookProviderHandler::class, $registry->handler('gitlab_self_hosted'));
    }

    #[Test]
    public function usesDedicatedWebhookSubConfigService(): void
    {
        $registry = (new WebhookProviderHandlerRegistryFactory())($this->container(
            [
                'github' => [
                    'events' => ['custom-event'],
                ],
            ],
            [
                'sources' => [
                    'webhooks' => [
                        'github' => [
                            'events' => ['push'],
                        ],
                    ],
                ],
            ],
        ));

        $handler = $registry->handler('github');
        self::assertInstanceOf(GithubWebhookProviderHandler::class, $handler);

        $payload = json_encode([
            'repository' => [
                'html_url' => 'https://github.com/acme/package',
            ],
        ], JSON_THROW_ON_ERROR);
        $sourceData                  = new SourceData(id: '', organizationId: '', provider: 'github', url: 'https://github.com/acme/package', active: false, webhookSecret: '');
        $webhookInspectionResult     = $handler->inspectPayload(
            $sourceData,
            $handler->envelope(
                $sourceData,
                [
                    'x-github-delivery' => 'delivery-1',
                    'x-github-event'    => 'custom-event',
                ],
                $payload,
            ),
            $payload,
        );

        self::assertSame('processed', $webhookInspectionResult->status);
        self::assertSame('Sync scheduled for https://github.com/acme/package.', $webhookInspectionResult->message);
    }

    /**
     * @param array<string, mixed> $webhookConfig
     * @param array<string, mixed> $applicationConfig
     */
    private function container(array $webhookConfig, array $applicationConfig = []): ContainerInterface
    {
        return new readonly class($webhookConfig, $applicationConfig) implements ContainerInterface {
            /**
             * @param array<string, mixed> $webhookConfig
             * @param array<string, mixed> $applicationConfig
             */
            public function __construct(private array $webhookConfig, private array $applicationConfig) {}

            public function get(string $id): mixed
            {
                if ('config' === $id) {
                    return $this->applicationConfig;
                }

                if ('config.sources.webhooks' === $id) {
                    return $this->webhookConfig;
                }

                throw new RuntimeException('Unknown service.');
            }

            public function has(string $id): bool
            {
                return 'config' === $id || 'config.sources.webhooks' === $id;
            }
        };
    }
}
