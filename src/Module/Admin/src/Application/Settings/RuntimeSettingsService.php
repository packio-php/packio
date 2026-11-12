<?php

declare(strict_types=1);

namespace Packio\Admin\Application\Settings;

use Packio\Admin\Domain\Entity\ProjectSetting;
use Packio\Admin\Domain\Repository\ProjectSettingRepositoryInterface;

use function array_key_exists;
use function explode;
use function is_array;

final readonly class RuntimeSettingsService
{
    public function __construct(
        private ProjectSettingRepositoryInterface $projectSettingRepository,
        private RuntimeSettingCatalog $runtimeSettingCatalog,
    ) {}

    public function booleanValue(string $key, bool $fallback): bool
    {
        return $this->overrideValue($key)->booleanValue($fallback);
    }

    public function overrideValue(string $key): RuntimeSettingOverride
    {
        $this->runtimeSettingCatalog->definition($key);

        $setting = $this->projectSettingRepository->findByKey($key);

        return RuntimeSettingOverride::fromStoredValue($setting?->value());
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{
     *     definitions: list<array<string, mixed>>,
     *     values: array<string, array{override: string, effective: bool, fallback: bool, fallback_source: string}>
     * }
     */
    public function settingsPayload(array $config): array
    {
        $definitions = [];
        $values      = [];
        $stored      = [];
        foreach ($this->projectSettingRepository->findAll() as $projectSetting) {
            $stored[$projectSetting->key()] = $projectSetting->value();
        }

        foreach ($this->runtimeSettingCatalog->definitions() as $runtimeSettingDefinition) {
            $fallback                               = $this->configBoolean($config, $runtimeSettingDefinition->configPath, $runtimeSettingDefinition->fallbackDefault);
            $override                               = RuntimeSettingOverride::fromStoredValue($stored[$runtimeSettingDefinition->key] ?? null);
            $definitions[]                          = $runtimeSettingDefinition->toPayload();
            $values[$runtimeSettingDefinition->key] = [
                'override'        => $override->value,
                'effective'       => $override->booleanValue($fallback),
                'fallback'        => $fallback,
                'fallback_source' => $runtimeSettingDefinition->configPath,
            ];
        }

        return [
            'definitions' => $definitions,
            'values'      => $values,
        ];
    }

    /**
     * @param array<string, string> $values
     */
    public function updateOverrides(array $values, ?string $updatedByUserId): void
    {
        $overrides = [];
        foreach ($values as $key => $value) {
            $this->runtimeSettingCatalog->definition($key);
            $overrides[$key] = RuntimeSettingOverride::fromInput($value);
        }

        foreach ($overrides as $key => $override) {
            $setting = $this->projectSettingRepository->findByKey($key);
            if (! $setting instanceof ProjectSetting) {
                $setting = ProjectSetting::put($key, $override->value, $updatedByUserId);
            } else {
                $setting->updateValue($override->value, $updatedByUserId);
            }

            $this->projectSettingRepository->save($setting);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configBoolean(array $config, string $path, bool $default): bool
    {
        $current = $config;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return (bool) $current;
    }
}
