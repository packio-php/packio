<?php

declare(strict_types=1);

namespace Packio\Admin\Application\Settings;

final readonly class RuntimeSettingDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $group,
        public string $configPath,
        public bool $fallbackDefault = false,
        public string $helpText = '',
        public ?string $providerKey = null,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     group: string,
     *     config_path: string,
     *     help_text: string,
     *     provider_key: null|string,
     *     allowed_values: list<string>
     * }
     */
    public function toPayload(): array
    {
        return [
            'key'            => $this->key,
            'label'          => $this->label,
            'group'          => $this->group,
            'config_path'    => $this->configPath,
            'help_text'      => $this->helpText,
            'provider_key'   => $this->providerKey,
            'allowed_values' => [
                RuntimeSettingOverride::Inherit->value,
                RuntimeSettingOverride::On->value,
                RuntimeSettingOverride::Off->value,
            ],
        ];
    }
}
