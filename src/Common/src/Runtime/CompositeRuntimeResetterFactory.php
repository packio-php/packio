<?php

declare(strict_types=1);

namespace Common\Runtime;

use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;

final class CompositeRuntimeResetterFactory
{
    public function __invoke(ContainerInterface $container): CompositeRuntimeResetter
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);

        $resetters = [];

        foreach ($configReader->nonEmptyStringList('runtime_resetters', []) as $resetterId) {
            $resetters[] = $containerResolver->getAs($resetterId, RuntimeResetterInterface::class);
        }

        return new CompositeRuntimeResetter($resetters);
    }
}
