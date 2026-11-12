<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Webhook;

use Common\Branding\BrandingConfig;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Domain\Entity\Source;

use function array_key_exists;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;
use function parse_url;
use function rawurlencode;
use function rtrim;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;

final readonly class GitlabSelfHostedWebhookProviderAdapter implements WebhookProviderAdapterInterface
{
    public function __construct(private BrandingConfig $brandingConfig) {}

    public function provider(): string
    {
        return 'gitlab_self_hosted';
    }

    public function tokenMissingMessage(): string
    {
        return 'A Personal Access Token is required to register webhooks automatically.';
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

        if ('' === $this->baseUrl($source)) {
            return new WebhookRegistrationResult(false, '', 'Source is missing base_url in providerConfig.', false);
        }

        $callbackValidationMessage = $this->invalidWebhookUrlMessage($webhookUrl);
        if (null !== $callbackValidationMessage) {
            return new WebhookRegistrationResult(false, '', $callbackValidationMessage, false);
        }

        return null;
    }

    public function canDeregister(Source $source): bool
    {
        return ! in_array('', [$source->owner(), $source->repository(), $this->baseUrl($source)], true);
    }

    public function hooksUrl(Source $source): string
    {
        return sprintf(
            '%s/api/v4/projects/%s/hooks',
            rtrim($this->baseUrl($source), '/'),
            rawurlencode($source->owner() . '/' . $source->repository()),
        );
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
        $baseUrl = $this->baseUrl($source);
        if (403 === $response['status']) {
            return sprintf(
                'GitLab Self-Hosted webhook API at %s returned status 403. The connected GitLab account must have the api OAuth scope and Maintainer or Owner access to the project.',
                $baseUrl,
            );
        }

        $message = is_array($response['body']) ? $this->responseMessage($response['body']) : null;
        if (null !== $message) {
            $hint = 422 === $response['status'] && str_contains(strtolower($message), 'invalid url')
                ? ' Check GitLab Admin Area outbound request filtering and allowlist settings for this callback host.'
                : '';

            return sprintf(
                'GitLab Self-Hosted webhook API error (%d) at %s while registering callback %s: %s%s',
                $response['status'],
                $baseUrl,
                $webhookUrl,
                $message,
                $hint,
            );
        }

        return sprintf('GitLab Self-Hosted webhook API at %s returned status %d.', $baseUrl, $response['status']);
    }

    private function baseUrl(Source $source): string
    {
        return trim((string) ($source->providerConfig()['base_url'] ?? ''));
    }

    private function invalidWebhookUrlMessage(string $webhookUrl): ?string
    {
        $parts = parse_url($webhookUrl);
        if (! is_array($parts)) {
            return sprintf(
                'GitLab Self-Hosted webhook callback URL is invalid: %s. Configure APP_URL as an absolute http(s) URL that GitLab can reach.',
                $webhookUrl,
            );
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = trim((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || '' === $host) {
            return sprintf(
                'GitLab Self-Hosted webhook callback URL is invalid: %s. Configure APP_URL as an absolute http(s) URL that GitLab can reach.',
                $webhookUrl,
            );
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $body
     */
    private function responseMessage(array $body): ?string
    {
        foreach (['message', 'error', 'error_description'] as $key) {
            if (! array_key_exists($key, $body)) {
                continue;
            }

            return $this->stringifyMessage($body[$key]);
        }

        return null;
    }

    private function stringifyMessage(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return '' !== $value ? $value : null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $parts = [];
        foreach ($value as $key => $item) {
            $message = $this->stringifyMessage($item);
            if (null === $message) {
                continue;
            }

            $parts[] = is_string($key)
                ? sprintf('%s %s', $key, $message)
                : $message;
        }

        return [] !== $parts ? implode('; ', $parts) : null;
    }
}
