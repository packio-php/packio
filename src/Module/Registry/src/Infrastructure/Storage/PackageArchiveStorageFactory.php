<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Storage;

use Aws\S3\S3ClientInterface;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;

use function getcwd;
use function is_dir;
use function is_readable;
use function is_writable;
use function mkdir;
use function realpath;
use function sprintf;
use function str_starts_with;

final class PackageArchiveStorageFactory
{
    public function __invoke(ContainerInterface $container): PackageArchiveStorageInterface
    {
        $containerResolver    = ContainerResolver::forFactory($container, self::class);
        $archiveStorageConfig = ArchiveStorageConfig::fromConfigReader(ConfigReader::fromContainer($containerResolver));

        return match ($archiveStorageConfig->driver) {
            ArchiveStorageConfig::DRIVER_LOCAL => new LocalPackageArchiveStorage(
                $this->ensureLocalPath((string) $archiveStorageConfig->local['path']),
            ),
            ArchiveStorageConfig::DRIVER_S3    => new S3PackageArchiveStorage(
                $containerResolver->getAs(S3ClientInterface::class, S3ClientInterface::class),
                (string) $archiveStorageConfig->s3['bucket'],
                (int) $archiveStorageConfig->s3['multipart_threshold_bytes'],
            ),
            default                            => throw new RuntimeException(sprintf(
                'Archive storage driver "%s" is not supported.',
                $archiveStorageConfig->driver,
            )),
        };
    }

    private function ensureLocalPath(string $path): string
    {
        if (! is_dir($path) && ! mkdir($path, 0o775, true) && ! is_dir($path)) {
            throw new RuntimeException('Failed to create local archive storage path.');
        }

        if (! is_readable($path) || ! is_writable($path)) {
            throw new RuntimeException('Local archive storage path must be readable and writable.');
        }

        $realPath   = realpath($path);
        $publicPath = realpath(getcwd() . '/public');

        if (false === $realPath || false === $publicPath) {
            throw new RuntimeException('Failed to resolve local archive storage path.');
        }

        if ($realPath === $publicPath || str_starts_with($realPath, $publicPath . '/')) {
            throw new RuntimeException('Local archive storage path must be outside the public web root.');
        }

        return $realPath;
    }
}
