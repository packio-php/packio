<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Webhook;

use Common\Branding\BrandingConfig;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Domain\Entity\Source;

use function is_array;
use function is_string;
use function rawurlencode;
use function sprintf;
use function trim;

final readonly class BitbucketWebhookProviderAdapter implements WebhookProviderAdapterInterface
{
    public function __construct(private BrandingConfig $brandingConfig) {}

    public function provider(): string
    {
        return 'bitbucket';
    }

    public function tokenMissingMessage(): string
    {
        return 'A Bitbucket OAuth token with repository and webhook scopes is required to register webhooks automatically.';
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
            return new WebhookRegistrationResult(false, '', 'Source is missing workspace or repository information.', false);
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
        return sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/hooks',
            rawurlencode($source->owner()),
            rawurlencode($source->repository()),
        );
    }

    public function deleteHookUrl(Source $source, string $externalId): string
    {
        return sprintf('%s/%s', $this->hooksUrl($source), rawurlencode($externalId));
    }

    public function createPayload(Source $source, string $webhookUrl, string $secret): array
    {
        return [
            'description' => 'Packio source synchronization',
            'url'         => $webhookUrl,
            'active'      => true,
            'secret'      => $secret,
            'events'      => ['repo:push'],
        ];
    }

    public function findExistingHookId(?array $body, string $webhookUrl): ?string
    {
        if (null === $body) {
            return null;
        }

        foreach ((array) ($body['values'] ?? []) as $existing) {
            if (! is_array($existing)) {
                continue;
            }

            if (($existing['url'] ?? null) !== $webhookUrl) {
                continue;
            }
            $uuid = $existing['uuid'] ?? null;
            if (is_string($uuid) && '' !== trim($uuid)) {
                return trim($uuid, '{}');
            }
        }

        return null;
    }

    public function createdHookId(?array $body): ?string
    {
        $hookUuid = $body['uuid'] ?? null;

        return is_string($hookUuid) && '' !== trim($hookUuid) ? trim($hookUuid, '{}') : null;
    }

    public function authHeaders(string $token): array
    {
        return [[
            'Accept'        => 'application/json',
            'User-Agent'    => $this->brandingConfig->httpUserAgent(),
            'Authorization' => sprintf('Bearer %s', trim($token)),
        ]];
    }

    public function describeFailure(array $response, Source $source, string $webhookUrl): string
    {
        if (403 === $response['status']) {
            return 'Bitbucket webhook API returned status 403. The connected Bitbucket account must have repository admin access and repository/webhook OAuth scopes.';
        }

        $message = $response['body']['error']['message'] ?? $response['body']['message'] ?? null;
        if (is_string($message) && '' !== trim($message)) {
            return sprintf('Bitbucket webhook API error (%d): %s', $response['status'], trim($message));
        }

        return sprintf('Bitbucket webhook API returned status %d.', $response['status']);
    }
}
