<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Storage;

use Packio\Registry\Application\Storage\PackageArchiveKeyGenerator;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;

final class PackageArchiveKeyGeneratorFactory
{
    public function __invoke(ContainerInterface $container): PackageArchiveKeyGenerator
    {
        $containerResolver    = ContainerResolver::forFactory($container, self::class);
        $archiveStorageConfig = ArchiveStorageConfig::fromConfigReader(ConfigReader::fromContainer($containerResolver));

        return new PackageArchiveKeyGenerator($archiveStorageConfig->activePrefix());
    }
}
