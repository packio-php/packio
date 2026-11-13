<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Webhook;

use Packio\Webhook\Application\Command\WebhookInspectionResult;
use Packio\Webhook\Application\Dto\SourceData;

use function hash_equals;
use function hash_hmac;
use function is_array;
use function json_decode;
use function sha1;
use function sprintf;
use function strtolower;
use function trim;

final readonly class GithubWebhookProviderHandler implements WebhookProviderHandlerInterface
{
    public function __construct(private WebhookProviderHandlerConfig $webhookProviderHandlerConfig) {}

    public function provider(): string
    {
        return $this->webhookProviderHandlerConfig->provider;
    }

    public function envelope(SourceData $sourceData, array $headers, string $payload): WebhookEnvelope
    {
        $delivery = $headers['x-github-delivery'] ?? '';
        if ('' === $delivery) {
            $delivery = 'missing-' . sha1($payload);
        }

        return new WebhookEnvelope('github', $delivery, $headers['x-github-event'] ?? '', $headers);
    }

    public function validateSignature(SourceData $sourceData, WebhookEnvelope $webhookEnvelope, string $payload): ?string
    {
        $signatureHeader = $this->webhookProviderHandlerConfig->signatureHeaderOr('X-Hub-Signature-256');
        $signature       = $webhookEnvelope->headers[strtolower($signatureHeader)] ?? '';
        $secret          = $sourceData->webhookSecret;
        if ('' === trim($secret)) {
            return 'Webhook is not configured.';
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return '' === $signature || ! hash_equals($expected, (string) $signature)
            ? 'Invalid GitHub signature.'
            : null;
    }

    public function inspectPayload(SourceData $sourceData, WebhookEnvelope $webhookEnvelope, string $payload): WebhookInspectionResult
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return WebhookInspectionResult::rejected('Invalid webhook payload.');
        }

        if ('ping' === $webhookEnvelope->event) {
            return WebhookInspectionResult::ignored('GitHub webhook ping accepted.');
        }

        if (! $this->webhookProviderHandlerConfig->supportsEvent($webhookEnvelope->event)) {
            return WebhookInspectionResult::ignored('GitHub event ignored.');
        }

        $repositoryUrl = (string) ($decoded['repository']['html_url'] ?? '');
        if ('' === $repositoryUrl) {
            return WebhookInspectionResult::rejected('Repository URL is missing from webhook payload.');
        }

        if ($repositoryUrl !== $sourceData->url) {
            return WebhookInspectionResult::rejected(WebhookInspectionResult::REPOSITORY_MISMATCH);
        }

        return WebhookInspectionResult::processed(sprintf('Sync scheduled for %s.', $repositoryUrl));
    }
}
