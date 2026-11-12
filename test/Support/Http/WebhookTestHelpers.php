<?php

declare(strict_types=1);

namespace PackioTest\Support\Http;

use const JSON_THROW_ON_ERROR;

use JsonException;
use RuntimeException;

use function array_key_exists;
use function array_merge;
use function dirname;
use function file_get_contents;
use function hash_hmac;
use function is_array;
use function json_decode;
use function json_encode;
use function random_int;
use function sprintf;

/**
 * Helpers for feature-level webhook tests (payload fixtures + signature headers).
 */
trait WebhookTestHelpers
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected function loadWebhookPayload(string $provider, string $fixture, array $overrides = []): array
    {
        $path = sprintf(
            '%s/Support/Fixture/%s/%s.json',
            $this->projectRoot(),
            $provider,
            $fixture,
        );
        $payload = $this->readFixture($path);

        if (! is_array($payload)) {
            throw new RuntimeException(sprintf('Webhook fixture is not an array: %s', $path));
        }

        return $this->mergePayload($payload, $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function fixtureJson(string $provider, string $fixture, array $overrides = []): string
    {
        try {
            return json_encode(
                $this->loadWebhookPayload($provider, $fixture, $overrides),
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new RuntimeException('Could not encode webhook fixture payload to JSON');
        }
    }

    protected function githubWebhookSignature(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    protected function bitbucketWebhookSignature(string $payload, string $secret): string
    {
        return $this->githubWebhookSignature($payload, $secret);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    protected function githubHeaders(string $payload, string $secret, string $event, array $headers = []): array
    {
        $delivery = $headers['X-GitHub-Delivery'] ?? $this->webhookDeliveryId();

        return array_merge([
            'Content-Type'        => 'application/json',
            'X-GitHub-Event'      => $event,
            'X-GitHub-Delivery'   => $delivery,
            'X-Hub-Signature-256' => $this->githubWebhookSignature($payload, $secret),
        ], $headers);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    protected function gitlabHeaders(string $secret, string $event, array $headers = []): array
    {
        return array_merge([
            'Content-Type'        => 'application/json',
            'X-Gitlab-Event'      => $event,
            'X-Gitlab-Event-UUID' => $this->webhookDeliveryId(),
            'X-Gitlab-Token'      => $secret,
        ], $headers);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    protected function bitbucketHeaders(string $payload, string $secret, string $event, array $headers = []): array
    {
        return array_merge([
            'Content-Type'    => 'application/json',
            'X-Event-Key'     => $event,
            'X-Request-UUID'  => $this->webhookDeliveryId(),
            'X-Hub-Signature' => $this->bitbucketWebhookSignature($payload, $secret),
        ], $headers);
    }

    protected function webhookDeliveryId(): string
    {
        $random = random_int(0, 0xFFFFFF);

        return sprintf('delivery-%06x', $random);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return array<string, mixed>
     */
    private function mergePayload(array $left, array $right): array
    {
        foreach ($right as $key => $value) {
            if (array_key_exists($key, $left) && is_array($left[$key]) && is_array($value)) {
                $left[$key] = $this->mergePayload($left[$key], $value);

                continue;
            }

            $left[$key] = $value;
        }

        return $left;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function readFixture(string $path): mixed
    {
        $content = file_get_contents($path);
        if (false === $content) {
            throw new RuntimeException('Failed to read fixture: ' . $path);
        }

        $decoded = json_decode($content, true);
        if (null === $decoded) {
            throw new RuntimeException('Failed to decode fixture: ' . $path);
        }

        return $decoded;
    }
}
