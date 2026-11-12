<?php

declare(strict_types=1);

namespace Packio\Application\Service;

final readonly class CorsOptions
{
    public const string DEFAULT_RUNTIME_SETTING_KEY = 'http.cors.enabled';

    public function __construct(
        public bool $enabledFallback,
        public string $runtimeSettingKey = self::DEFAULT_RUNTIME_SETTING_KEY,
    ) {}
}
