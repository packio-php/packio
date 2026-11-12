<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Infrastructure\Config;

use InvalidArgumentException;
use Packio\Webhook\Infrastructure\Config\WebhookProviderHandlerConfigCatalog;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\ContainerResolver\ConfigReader;

#[CoversClass(WebhookProviderHandlerConfigCatalog::class)]
#[CoversClass(WebhookProviderHandlerConfig::class)]
final class WebhookProviderHandlerConfigCatalogTest extends TestCase
{
    #[Test]
    public function usesConfiguredGithubSignatureHeaderAndEvents(): void
    {
        $catalog = $this->catalog([
            'github' => [
                'signature_header' => 'X-Custom-Github-Signature',
                'events'           => ['push'],
            ],
        ]);

        $webhookProviderHandlerConfig = $catalog->github();

        self::assertSame('github', $webhookProviderHandlerConfig->provider);
        self::assertSame(['push'], $webhookProviderHandlerConfig->events);
        self::assertSame('X-Custom-Github-Signature', $webhookProviderHandlerConfig->signatureHeader);
    }

    #[Test]
    public function usesConfiguredEventListsForAllProviders(): void
    {
        $catalog = $this->catalog([
            'github'    => [
                'events' => ['release'],
            ],
            'gitlab'    => [
                'events' => ['Merge Request Hook'],
            ],
            'bitbucket' => [
                'events' => ['repo:updated'],
            ],
            'codeberg'  => [
                'events' => ['issues'],
            ],
        ]);

        self::assertSame(['release'], $catalog->github()->events);
        self::assertSame(['Merge Request Hook'], $catalog->gitlab()->events);
        self::assertSame(['repo:updated'], $catalog->bitbucket()->events);
        self::assertSame(['issues'], $catalog->codeberg()->events);
    }

    #[Test]
    public function fallsBackToDefaultProviderConfig(): void
    {
        $catalog = $this->catalog([]);

        self::assertSame(['push', 'create', 'release'], $catalog->github()->events);
        self::assertSame('X-Hub-Signature-256', $catalog->github()->signatureHeader);
        self::assertSame(['Push Hook', 'Tag Push Hook'], $catalog->gitlab()->events);
        self::assertSame(['repo:push'], $catalog->bitbucket()->events);
        self::assertSame(['push', 'create', 'delete'], $catalog->codeberg()->events);
    }

    #[Test]
    public function gitlabSelfHostedReusesGitlabEventConfig(): void
    {
        $catalog = $this->catalog([
            'gitlab'             => [
                'events' => ['Push Hook'],
            ],
            'gitlab_self_hosted' => [
                'events' => ['Unexpected Self-Hosted Event'],
            ],
        ]);

        $webhookProviderHandlerConfig = $catalog->gitlabSelfHosted();

        self::assertSame('gitlab_self_hosted', $webhookProviderHandlerConfig->provider);
        self::assertSame(['Push Hook'], $webhookProviderHandlerConfig->events);
    }

    #[Test]
    public function eventConfigMustBeAListOfStrings(): void
    {
        $catalog = $this->catalog([
            'github' => [
                'events' => ['push', 42],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('github.events');

        $catalog->github();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function catalog(array $config): WebhookProviderHandlerConfigCatalog
    {
        return WebhookProviderHandlerConfigCatalog::fromConfigReader(ConfigReader::fromArray($config));
    }
}
