<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Infrastructure\Webhook;

use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookProviderHandlerConfig::class)]
final class WebhookProviderHandlerConfigTest extends TestCase
{
    #[Test]
    public function supportsEventUsesStrictEventMatching(): void
    {
        $webhookProviderHandlerConfig = new WebhookProviderHandlerConfig('github', ['push']);

        self::assertTrue($webhookProviderHandlerConfig->supportsEvent('push'));
        self::assertFalse($webhookProviderHandlerConfig->supportsEvent('Push'));
    }

    #[Test]
    public function signatureHeaderFallsBackWhenNotConfigured(): void
    {
        $webhookProviderHandlerConfig = new WebhookProviderHandlerConfig('github', ['push']);

        self::assertSame('X-Hub-Signature-256', $webhookProviderHandlerConfig->signatureHeaderOr('X-Hub-Signature-256'));
    }

    #[Test]
    public function signatureHeaderUsesConfiguredValue(): void
    {
        $webhookProviderHandlerConfig = new WebhookProviderHandlerConfig('github', ['push'], 'X-Custom-Signature');

        self::assertSame('X-Custom-Signature', $webhookProviderHandlerConfig->signatureHeaderOr('X-Hub-Signature-256'));
    }
}
