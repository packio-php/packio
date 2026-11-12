<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Webhook;

use Common\Branding\BrandingConfig;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SecretResolverInterface;
use Packio\Source\Application\Service\WebhookRegistrarInterface;
use Packio\Source\Domain\Entity\Source;

use function sprintf;
use function trim;

final readonly class WebhookRegistrationTemplate implements WebhookRegistrarInterface
{
    public function __construct(
        private BrandingConfig $brandingConfig,
        private SecretResolverInterface $secretResolver,
        private WebhookHttpRequestor $webhookHttpRequestor,
        private WebhookProviderAdapterInterface $webhookProviderAdapter,
        private SourceWebhookSecrets $sourceWebhookSecrets,
    ) {}

    public function provider(): string
    {
        return $this->webhookProviderAdapter->provider();
    }

    public function register(Source $source): WebhookRegistrationResult
    {
        $token = $this->secretResolver->resolveSourceAuthorizationSecret(
            $source->getIdentifier()->toString(),
        );

        if (null === $token || '' === trim($token)) {
            return new WebhookRegistrationResult(false, '', $this->webhookProviderAdapter->tokenMissingMessage(), false);
        }

        $webhookUrl = $this->webhookUrl($source);
        $validation = $this->webhookProviderAdapter->validateRegistration($source, $webhookUrl);
        if ($validation instanceof WebhookRegistrationResult) {
            return $validation;
        }

        // The stored value is non-empty here; it decrypts to '' when the encryption key changed.
        $secret = $this->sourceWebhookSecrets->decrypt((string) ($source->providerConfig()['webhook_secret'] ?? ''));
        if ('' === $secret) {
            return new WebhookRegistrationResult(false, '', $this->webhookProviderAdapter->webhookSecretMissingMessage(), false);
        }

        $listResponse = $this->requestWithAuthRetry('GET', $this->webhookProviderAdapter->hooksUrl($source), $token, null);
        if (200 === $listResponse['status']) {
            $existingHookId = $this->webhookProviderAdapter->findExistingHookId($listResponse['body'], $webhookUrl);
            if (null !== $existingHookId) {
                return new WebhookRegistrationResult(true, $existingHookId, 'Webhook already registered.', true);
            }
        }

        $response = $this->requestWithAuthRetry(
            'POST',
            $this->webhookProviderAdapter->hooksUrl($source),
            $token,
            $this->webhookProviderAdapter->createPayload($source, $webhookUrl, $secret),
        );
        if (201 !== $response['status'] || null === $response['body']) {
            return new WebhookRegistrationResult(
                false,
                '',
                $this->webhookProviderAdapter->describeFailure($response, $source, $webhookUrl),
                false,
            );
        }

        $hookId = $this->webhookProviderAdapter->createdHookId($response['body']);
        if (null === $hookId) {
            return new WebhookRegistrationResult(false, '', $this->webhookProviderAdapter->missingCreatedHookIdMessage(), false);
        }

        return new WebhookRegistrationResult(true, $hookId, 'Webhook registered successfully.', false);
    }

    public function deregister(Source $source): bool
    {
        $token = $this->secretResolver->resolveSourceAuthorizationSecret(
            $source->getIdentifier()->toString(),
        );

        $externalId = (string) ($source->providerConfig()['webhook_external_id'] ?? '');
        if (null === $token || '' === trim($token) || '' === $externalId || ! $this->webhookProviderAdapter->canDeregister($source)) {
            return false;
        }

        $response = $this->requestWithAuthRetry(
            'DELETE',
            $this->webhookProviderAdapter->deleteHookUrl($source, $externalId),
            $token,
            null,
        );

        return 204 === $response['status'] || 404 === $response['status'];
    }

    /**
     * @param null|array<string, mixed> $body
     *
     * @return array{status: int, body: null|array<int|string, mixed>}
     */
    private function requestWithAuthRetry(string $method, string $url, string $token, ?array $body): array
    {
        $lastResponse = [
            'status' => 0,
            'body'   => null,
        ];

        foreach ($this->webhookProviderAdapter->authHeaders($token) as $headers) {
            $lastResponse = $this->webhookHttpRequestor->request($method, $url, $headers, $body);
            if (401 !== $lastResponse['status'] && 403 !== $lastResponse['status']) {
                return $lastResponse;
            }
        }

        return $lastResponse;
    }

    private function webhookUrl(Source $source): string
    {
        return sprintf(
            '%s/webhooks/%s/%s',
            trim($this->brandingConfig->appBaseUrl(), '/'),
            $source->provider(),
            $source->getIdentifier()->toString(),
        );
    }
}
