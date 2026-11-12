<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Webhook;

use Common\Branding\BrandingConfig;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Domain\Entity\Source;

use function is_array;
use function is_int;
use function is_string;
use function rawurlencode;
use function sprintf;

final readonly class GitlabWebhookProviderAdapter implements WebhookProviderAdapterInterface
{
    private const string BASE_URL = 'https://gitlab.com';

    public function __construct(private BrandingConfig $brandingConfig) {}

    public function provider(): string
    {
        return 'gitlab';
    }

    public function tokenMissingMessage(): string
    {
        return 'A GitLab Personal Access Token is required to register webhooks automatically.';
    }

    public function missingCreatedHookIdMessage(): string
    {
        return 'Webhook created but upstream did not return an identifier.';
    }

    public function webhookSecretMissingMessage(): string
    {
        return 'Source has no webhook secret — cannot register webhook.';
    }

    public function validateRegistration(Source $source, string $webhookUrl): ?WebhookRegistrationResult
    {
        if ('' === $source->owner() || '' === $source->repository()) {
            return new WebhookRegistrationResult(false, '', 'Source is missing namespace or repository information.', false);
        }

        if ('' === (string) ($source->providerConfig()['webhook_secret'] ?? '')) {
            return new WebhookRegistrationResult(false, '', $this->webhookSecretMissingMessage(), false);
        }

        return null;
    }

    public function canDeregister(Source $source): bool
    {
        return '' !== $source->owner() && '' !== $source->repository();
    }

    public function hooksUrl(Source $source): string
    {
        return sprintf('%s/api/v4/projects/%s/hooks', self::BASE_URL, rawurlencode($source->owner() . '/' . $source->repository()));
    }

    public function deleteHookUrl(Source $source, string $externalId): string
    {
        return sprintf('%s/%s', $this->hooksUrl($source), rawurlencode($externalId));
    }

    public function createPayload(Source $source, string $webhookUrl, string $secret): array
    {
        return [
            'url'                     => $webhookUrl,
            'token'                   => $secret,
            'push_events'             => true,
            'tag_push_events'         => true,
            'merge_requests_events'   => false,
            'enable_ssl_verification' => true,
        ];
    }

    public function findExistingHookId(?array $body, string $webhookUrl): ?string
    {
        if (null === $body) {
            return null;
        }

        foreach ($body as $existing) {
            if (! is_array($existing)) {
                continue;
            }

            if (($existing['url'] ?? null) !== $webhookUrl) {
                continue;
            }
            $id = $existing['id'] ?? null;
            if (is_int($id) || is_string($id)) {
                return (string) $id;
            }
        }

        return null;
    }

    public function createdHookId(?array $body): ?string
    {
        $hookId = $body['id'] ?? null;

        return is_int($hookId) || is_string($hookId) ? (string) $hookId : null;
    }

    public function authHeaders(string $token): array
    {
        return [[
            'Accept'        => 'application/json',
            'User-Agent'    => $this->brandingConfig->httpUserAgent(),
            'Authorization' => sprintf('Bearer %s', $token),
        ]];
    }

    public function describeFailure(array $response, Source $source, string $webhookUrl): string
    {
        if (403 === $response['status']) {
            return 'GitLab webhook API returned status 403. The connected GitLab account must have the api OAuth scope and Maintainer or Owner access to the project.';
        }

        if (
            is_array($response['body'])
            && isset($response['body']['message'])
            && is_string($response['body']['message'])
        ) {
            return sprintf('GitLab webhook API error (%d): %s', $response['status'], $response['body']['message']);
        }

        return sprintf('GitLab webhook API returned status %d.', $response['status']);
    }
}
