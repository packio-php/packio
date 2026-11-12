<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Facade;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingsService;

final readonly class ProjectSettingsApiFacade implements ProjectSettingsApiFacadeInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private RuntimeSettingsService $runtimeSettingsService,
        private array $config,
    ) {}

    public function registrationOpen(): bool
    {
        return $this->runtimeSettingsService->booleanValue(
            ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN,
            (bool) ($this->config['identity']['registration_open'] ?? true),
        );
    }

    public function booleanValue(string $key, bool $fallback): bool
    {
        return $this->runtimeSettingsService->booleanValue($key, $fallback);
    }

    public function overrideValue(string $key): string
    {
        return $this->runtimeSettingsService->overrideValue($key)->value;
    }

    public function settingsPayload(): array
    {
        return $this->runtimeSettingsService->settingsPayload($this->config);
    }

    public function updateOverrides(array $values, ?string $updatedByUserId = null): void
    {
        $this->runtimeSettingsService->updateOverrides($values, $updatedByUserId);
    }
}
