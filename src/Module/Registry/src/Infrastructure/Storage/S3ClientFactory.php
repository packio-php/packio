<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Storage;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;

final class S3ClientFactory
{
    public function __invoke(ContainerInterface $container): S3ClientInterface
    {
        $containerResolver    = ContainerResolver::forFactory($container, self::class);
        $archiveStorageConfig = ArchiveStorageConfig::fromConfigReader(ConfigReader::fromContainer($containerResolver));
        $s3                   = $archiveStorageConfig->s3;

        return new S3Client([
            'version'                 => 'latest',
            'region'                  => $s3['region'],
            'endpoint'                => $s3['endpoint'],
            'use_path_style_endpoint' => $s3['path_style_endpoint'],
            'use_ssl'                 => $s3['use_ssl'],
            'credentials'             => [
                'key'    => $s3['access_key'],
                'secret' => $s3['secret_key'],
            ],
        ]);
    }
}
