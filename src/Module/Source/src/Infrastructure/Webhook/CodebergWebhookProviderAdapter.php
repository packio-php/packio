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
use function rtrim;
use function sprintf;
use function trim;

final readonly class CodebergWebhookProviderAdapter implements WebhookProviderAdapterInterface
{
    private const string BASE_URL = 'https://codeberg.org';

    public function __construct(
        private BrandingConfig $brandingConfig,
        private string $provider = 'codeberg',
        private string $label = 'Codeberg',
    ) {}

    public function provider(): string
    {
        return $this->provider;
    }

    public function tokenMissingMessage(): string
    {
        return sprintf('A %s OAuth token or access token is required to register webhooks automatically.', $this->label);
    }

    public function missingCreatedHookIdMessage(): string
    {
        return sprintf('Webhook created but %s did not return an identifier.', $this->label);
    }

    public function webhookSecretMissingMessage(): string
    {
        return 'Source has no webhook secret; cannot register webhook.';
    }

    public function validateRegistration(Source $source, string $webhookUrl): ?WebhookRegistrationResult
    {
        if ('' === $source->owner() || '' === $source->repository()) {
            return new WebhookRegistrationResult(false, '', 'Source is missing owner or repository information.', false);
        }

        if ('' === (string) ($source->providerConfig()['webhook_secret'] ?? '')) {
            return new WebhookRegistrationResult(false, '', $this->webhookSecretMissingMessage(), false);
        }

        if ('' === $this->baseUrl($source)) {
            return new WebhookRegistrationResult(false, '', 'Source is missing base_url in providerConfig.', false);
        }

        return null;
    }

    public function canDeregister(Source $source): bool
    {
        return '' !== $source->owner() && '' !== $source->repository() && '' !== $this->baseUrl($source);
    }

    public function hooksUrl(Source $source): string
    {
        return sprintf(
            '%s/api/v1/repos/%s/%s/hooks',
            $this->baseUrl($source),
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
            'type'   => 'forgejo',
            'config' => [
                'url'          => $webhookUrl,
                'content_type' => 'json',
                'secret'       => $secret,
            ],
            'events' => ['push', 'create', 'delete'],
            'active' => true,
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

            $config = $existing['config'] ?? null;
            if (is_array($config) && ($config['url'] ?? null) === $webhookUrl) {
                $id = $existing['id'] ?? null;
                if (is_int($id) || is_string($id)) {
                    return (string) $id;
                }
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
        return [
            [
                'Accept'        => 'application/json',
                'User-Agent'    => $this->brandingConfig->httpUserAgent(),
                'Authorization' => sprintf('Bearer %s', trim($token)),
            ],
            [
                'Accept'        => 'application/json',
                'User-Agent'    => $this->brandingConfig->httpUserAgent(),
                'Authorization' => sprintf('token %s', trim($token)),
            ],
        ];
    }

    public function describeFailure(array $response, Source $source, string $webhookUrl): string
    {
        $body = $response['body'];
        if (is_array($body)) {
            $message = $body['message'] ?? $body['error_description'] ?? $body['error'] ?? null;
            if (is_string($message) && '' !== trim($message)) {
                return sprintf('%s webhook API error (%d): %s', $this->label, $response['status'], trim($message));
            }
        }

        if (403 === $response['status']) {
            return sprintf('%1$s webhook API returned status 403. The connected %1$s account must have repository administration rights.', $this->label);
        }

        return sprintf('%s webhook API returned status %d.', $this->label, $response['status']);
    }

    private function baseUrl(Source $source): string
    {
        // Codeberg never trusts providerConfig; self-hosted instances must carry their own base_url.
        if ('codeberg' === $this->provider) {
            return self::BASE_URL;
        }

        $baseUrl = $source->providerConfig()['base_url'] ?? null;

        return is_string($baseUrl) ? rtrim($baseUrl, '/') : '';
    }
}
