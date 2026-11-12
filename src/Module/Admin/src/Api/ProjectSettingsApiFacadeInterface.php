<?php

declare(strict_types=1);

namespace Packio\Admin\Api;

interface ProjectSettingsApiFacadeInterface
{
    public const string REGISTRATION_OPEN = 'identity.registration_open';
    public const string API_DOCS_PUBLIC   = 'api.docs.public';
    public const string HTTP_CORS_ENABLED = 'http.cors.enabled';

    public function registrationOpen(): bool;

    public function booleanValue(string $key, bool $fallback): bool;

    public function overrideValue(string $key): string;

    /**
     * @return array{
     *     definitions: list<array<string, mixed>>,
     *     values: array<string, array{override: string, effective: bool, fallback: bool, fallback_source: string}>
     * }
     */
    public function settingsPayload(): array;

    /**
     * @param array<string, string> $values
     */
    public function updateOverrides(array $values, ?string $updatedByUserId = null): void;
}
