<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Webhook;

use Packio\Webhook\Application\Command\WebhookInspectionResult;
use Packio\Webhook\Application\Dto\SourceData;

use function hash_equals;
use function hash_hmac;
use function is_array;
use function json_decode;
use function preg_match;
use function rtrim;
use function sha1;
use function sprintf;
use function str_ends_with;
use function trim;

final readonly class CodebergWebhookProviderHandler implements WebhookProviderHandlerInterface
{
    public function __construct(private WebhookProviderHandlerConfig $webhookProviderHandlerConfig) {}

    public function provider(): string
    {
        return $this->webhookProviderHandlerConfig->provider;
    }

    public function envelope(SourceData $sourceData, array $headers, string $payload): WebhookEnvelope
    {
        $delivery = $headers['x-forgejo-delivery'] ?? $headers['x-gitea-delivery'] ?? $headers['x-github-delivery'] ?? '';
        if ('' === $delivery) {
            $delivery = 'missing-' . sha1($payload);
        }

        return new WebhookEnvelope(
            $this->webhookProviderHandlerConfig->provider,
            $delivery,
            $headers['x-forgejo-event'] ?? $headers['x-gitea-event'] ?? $headers['x-github-event'] ?? '',
            $headers,
        );
    }

    public function validateSignature(SourceData $sourceData, WebhookEnvelope $webhookEnvelope, string $payload): ?string
    {
        $secret = $sourceData->webhookSecret;
        if ('' === trim($secret)) {
            return 'Webhook is not configured.';
        }

        $expected  = hash_hmac('sha256', $payload, $secret);
        $signature = $webhookEnvelope->headers['x-forgejo-signature'] ?? '';

        return '' === $signature || ! hash_equals($expected, $signature)
            ? 'Invalid ' . $this->label() . ' signature.'
            : null;
    }

    public function inspectPayload(SourceData $sourceData, WebhookEnvelope $webhookEnvelope, string $payload): WebhookInspectionResult
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return WebhookInspectionResult::rejected('Invalid webhook payload.');
        }

        if (! $this->webhookProviderHandlerConfig->supportsEvent($webhookEnvelope->event)) {
            return WebhookInspectionResult::ignored($this->label() . ' event ignored.');
        }

        $repositoryUrl = $this->resolveRepositoryUrl($decoded, $sourceData->url);
        if ('' === $repositoryUrl) {
            return WebhookInspectionResult::rejected('Repository URL is missing from webhook payload.');
        }

        if (rtrim($repositoryUrl, '/') !== rtrim($sourceData->url, '/')) {
            return WebhookInspectionResult::rejected(WebhookInspectionResult::REPOSITORY_MISMATCH);
        }

        return WebhookInspectionResult::processed(sprintf('Sync scheduled for %s.', $repositoryUrl));
    }

    private function label(): string
    {
        return 'codeberg' === $this->webhookProviderHandlerConfig->provider ? 'Codeberg' : 'Forgejo';
    }

    /** @param array<string, mixed> $payload */
    private function resolveRepositoryUrl(array $payload, string $sourceUrl): string
    {
        $repository = $payload['repository'] ?? null;
        if (! is_array($repository)) {
            return '';
        }

        $htmlUrl = $repository['html_url'] ?? $repository['website'] ?? '';
        if ('' !== $htmlUrl) {
            return (string) $htmlUrl;
        }

        $fullName = $repository['full_name'] ?? '';
        if ('' !== $fullName) {
            return 'codeberg' === $this->webhookProviderHandlerConfig->provider
                ? sprintf('https://codeberg.org/%s', (string) $fullName)
                : $this->instanceRepositoryUrl($sourceUrl, (string) $fullName);
        }

        return '';
    }

    /** Self-hosted fallback: only an exact `owner/repo` that ends the source URL is trusted. */
    private function instanceRepositoryUrl(string $sourceUrl, string $fullName): string
    {
        $sourceUrl = rtrim($sourceUrl, '/');

        return 1 === preg_match('#^[^/]+/[^/]+$#', $fullName) && str_ends_with($sourceUrl, '/' . $fullName) ? $sourceUrl : '';
    }
}
