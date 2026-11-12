<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Infrastructure\Webhook;

use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Infrastructure\Webhook\BitbucketWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\CodebergWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\GithubWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\GitlabWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerConfig;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function hash_hmac;
use function sha1;

final class ProviderVerificationTest extends TestCase
{
    /** @param array<string, string> $headers */
    #[DataProvider('providers')]
    public function testSignaturesAndNormalizedPayloadRemainCompatible(
        WebhookProviderHandlerInterface $webhookProviderHandler,
        array $headers,
        string $payload
    ): void {
        $sourceData = new SourceData(id: '', organizationId: '', provider: '', url: 'https://example.test/owner/repo', active: false, webhookSecret: 'secret');
        $envelope   = $webhookProviderHandler->envelope($sourceData, $headers, $payload);

        self::assertSame('delivery', $envelope->deliveryId);
        self::assertNull($webhookProviderHandler->validateSignature($sourceData, $envelope, $payload));
        self::assertSame('processed', $webhookProviderHandler->inspectPayload($sourceData, $envelope, $payload)->status);
        self::assertSame('Invalid webhook payload.', $webhookProviderHandler->inspectPayload($sourceData, $envelope, '{')->message);
        self::assertSame('Webhook repository does not match the configured source.', $webhookProviderHandler->inspectPayload(new SourceData(id: '', organizationId: '', provider: '', url: 'https://other.test', active: false, webhookSecret: ''), $envelope, $payload)->message);

        $webhookEnvelope = $webhookProviderHandler->envelope($sourceData, [], $payload);
        self::assertNotNull($webhookProviderHandler->validateSignature($sourceData, $webhookEnvelope, $payload));
        self::assertSame('missing-' . sha1($payload), $webhookEnvelope->deliveryId);
        self::assertSame('ignored', $webhookProviderHandler->inspectPayload($sourceData, $webhookEnvelope, $payload)->status);
        self::assertSame('Webhook is not configured.', $webhookProviderHandler->validateSignature(new SourceData(id: '', organizationId: '', provider: '', url: '', active: false, webhookSecret: ''), $webhookEnvelope, $payload));
    }

    /** @return iterable<string, array{WebhookProviderHandlerInterface, array<string, string>, string}> */
    public static function providers(): iterable
    {
        $payload = '{"repository":{"html_url":"https://example.test/owner/repo"}}';

        yield 'github' => [new GithubWebhookProviderHandler(new WebhookProviderHandlerConfig('github', ['push'])), [
            'x-github-delivery'   => 'delivery',
            'x-github-event'      => 'push',
            'x-hub-signature-256' => 'sha256=' . hash_hmac('sha256', $payload, 'secret'),
        ], $payload];

        yield 'codeberg' => [new CodebergWebhookProviderHandler(new WebhookProviderHandlerConfig('codeberg', ['push'])), [
            'x-forgejo-delivery'  => 'delivery',
            'x-forgejo-event'     => 'push',
            'x-forgejo-signature' => hash_hmac('sha256', $payload, 'secret'),
        ], $payload];
        $payload = '{"project":{"web_url":"https://example.test/owner/repo"}}';

        yield 'gitlab' => [new GitlabWebhookProviderHandler(new WebhookProviderHandlerConfig('gitlab', ['Push Hook'])), [
            'x-gitlab-event-uuid' => 'delivery',
            'x-gitlab-event'      => 'Push Hook',
            'x-gitlab-token'      => 'secret',
        ], $payload];
        $payload = '{"repository":{"links":{"html":{"href":"https://example.test/owner/repo"}}}}';

        yield 'bitbucket' => [new BitbucketWebhookProviderHandler(new WebhookProviderHandlerConfig('bitbucket', ['repo:push'])), [
            'x-request-uuid'  => 'delivery',
            'x-event-key'     => 'repo:push',
            'x-hub-signature' => 'sha256=' . hash_hmac('sha256', $payload, 'secret'),
        ], $payload];
    }

    public function testGitlabEnvelopeUsesSourceProvider(): void
    {
        $gitlabWebhookProviderHandler = new GitlabWebhookProviderHandler(new WebhookProviderHandlerConfig('gitlab', ['Push Hook']));
        $sourceData                   = new SourceData(id: '', organizationId: '', provider: 'gitlab_self_hosted', url: '', active: false, webhookSecret: '');

        self::assertSame('gitlab_self_hosted', $gitlabWebhookProviderHandler->envelope($sourceData, [], '{}')->provider);
    }
}
