<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Webhook;

use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Domain\Entity\Source;

interface WebhookProviderAdapterInterface
{
    public function provider(): string;

    public function tokenMissingMessage(): string;

    public function missingCreatedHookIdMessage(): string;

    public function webhookSecretMissingMessage(): string;

    public function validateRegistration(Source $source, string $webhookUrl): ?WebhookRegistrationResult;

    public function canDeregister(Source $source): bool;

    public function hooksUrl(Source $source): string;

    public function deleteHookUrl(Source $source, string $externalId): string;

    /**
     * @return array<string, mixed>
     */
    public function createPayload(Source $source, string $webhookUrl, string $secret): array;

    /**
     * @param null|array<int|string, mixed> $body
     */
    public function findExistingHookId(?array $body, string $webhookUrl): ?string;

    /**
     * @param null|array<int|string, mixed> $body
     */
    public function createdHookId(?array $body): ?string;

    /**
     * @return list<array<string, string>>
     */
    public function authHeaders(string $token): array;

    /**
     * @param array{status: int, body: null|array<int|string, mixed>} $response
     */
    public function describeFailure(array $response, Source $source, string $webhookUrl): string;
}
