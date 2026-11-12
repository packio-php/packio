<?php

declare(strict_types=1);

namespace Packio\Application\Service;

use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;

final readonly class CorsOptionsFactory
{
    public function __invoke(ContainerInterface $container): CorsOptions
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);

        return new CorsOptions(
            enabledFallback: $configReader->bool('packio.cors.enabled', true),
            runtimeSettingKey: $configReader->nonEmptyString(
                'packio.cors.runtime_setting_key',
                CorsOptions::DEFAULT_RUNTIME_SETTING_KEY,
            ),
        );
    }
}
