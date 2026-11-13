<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Webhook;

use Packio\Webhook\Application\Command\WebhookInspectionResult;
use Packio\Webhook\Application\Dto\SourceData;

use function hash_equals;
use function is_array;
use function json_decode;
use function rtrim;
use function sha1;
use function sprintf;
use function trim;

final readonly class GitlabWebhookProviderHandler implements WebhookProviderHandlerInterface
{
    public function __construct(private WebhookProviderHandlerConfig $webhookProviderHandlerConfig) {}

    public function provider(): string
    {
        return $this->webhookProviderHandlerConfig->provider;
    }

    public function envelope(SourceData $sourceData, array $headers, string $payload): WebhookEnvelope
    {
        $delivery = $headers['x-gitlab-event-uuid'] ?? '';
        if ('' === $delivery) {
            $delivery = 'missing-' . sha1($payload);
        }

        return new WebhookEnvelope($sourceData->provider, $delivery, $headers['x-gitlab-event'] ?? '', $headers);
    }

    public function validateSignature(SourceData $sourceData, WebhookEnvelope $webhookEnvelope, string $payload): ?string
    {
        $secret = $sourceData->webhookSecret;
        if ('' === trim($secret)) {
            return 'Webhook is not configured.';
        }

        $token = $webhookEnvelope->headers['x-gitlab-token'] ?? '';

        return hash_equals($secret, $token) ? null : 'Invalid GitLab webhook token.';
    }

    public function inspectPayload(SourceData $sourceData, WebhookEnvelope $webhookEnvelope, string $payload): WebhookInspectionResult
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return WebhookInspectionResult::rejected('Invalid webhook payload.');
        }

        if (! $this->webhookProviderHandlerConfig->supportsEvent($webhookEnvelope->event)) {
            return WebhookInspectionResult::ignored('GitLab event ignored.');
        }

        $repositoryUrl = $this->resolveRepositoryUrl($decoded);
        if ('' === $repositoryUrl) {
            return WebhookInspectionResult::rejected('Repository URL is missing from webhook payload.');
        }

        if (rtrim($repositoryUrl, '/') !== rtrim($sourceData->url, '/')) {
            return WebhookInspectionResult::rejected(WebhookInspectionResult::REPOSITORY_MISMATCH);
        }

        return WebhookInspectionResult::processed(sprintf('Sync scheduled for %s.', $repositoryUrl));
    }

    /** @param array<string, mixed> $payload */
    private function resolveRepositoryUrl(array $payload): string
    {
        $project = $payload['project'] ?? null;
        if (is_array($project)) {
            $projectUrl = $project['web_url'] ?? $project['homepage'] ?? '';
            if ('' !== $projectUrl) {
                return (string) $projectUrl;
            }
        }

        $repository = $payload['repository'] ?? null;
        if (is_array($repository)) {
            $repositoryUrl = $repository['homepage'] ?? '';
            if ('' !== $repositoryUrl) {
                return (string) $repositoryUrl;
            }
        }

        return '';
    }
}
