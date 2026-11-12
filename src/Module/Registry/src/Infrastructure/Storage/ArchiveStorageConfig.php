<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Storage;

use InvalidArgumentException;
use Sirix\ContainerResolver\ConfigReader;

use function getcwd;
use function in_array;
use function parse_url;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function trim;

final readonly class ArchiveStorageConfig
{
    public const string DRIVER_LOCAL = 'local';
    public const string DRIVER_S3    = 's3';

    /**
     * @param array<string, mixed> $local
     * @param array<string, mixed> $s3
     */
    private function __construct(public string $driver, public array $local, public array $s3, public bool $productionMode) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromApplicationConfig(array $config): self
    {
        return self::fromConfigReader(ConfigReader::fromArray($config, self::class));
    }

    public static function fromConfigReader(ConfigReader $configReader): self
    {
        $driver = trim($configReader->string('storage.archive.driver', self::DRIVER_LOCAL));
        if (! in_array($driver, [self::DRIVER_LOCAL, self::DRIVER_S3], true)) {
            throw new InvalidArgumentException(sprintf('Archive storage driver "%s" is not supported.', $driver));
        }

        $local          = self::readLocalConfig($configReader);
        $s3             = self::readS3Config($configReader);
        $productionMode = $configReader->bool('packio.production_mode', false);

        $instance = new self($driver, $local, $s3, $productionMode);
        $instance->validate();

        return $instance;
    }

    public function activePrefix(): string
    {
        return self::DRIVER_S3 === $this->driver
            ? (string) $this->s3['prefix']
            : (string) $this->local['prefix'];
    }

    private function validate(): void
    {
        $this->assertPrefix((string) $this->local['prefix'], 'local');
        $this->assertPrefix((string) $this->s3['prefix'], 's3');

        if (self::DRIVER_LOCAL === $this->driver) {
            $path = (string) $this->local['path'];
            if ('' === $path) {
                throw new InvalidArgumentException('Local archive storage path must not be empty.');
            }

            $normalizedPath = rtrim($path, '/');
            $publicPath     = rtrim((string) getcwd(), '/') . '/public';
            if (
                'public' === $normalizedPath
                || str_starts_with($normalizedPath, 'public/')
                || $publicPath === $normalizedPath
                || str_starts_with($normalizedPath, $publicPath . '/')
            ) {
                throw new InvalidArgumentException('Local archive storage path must be outside the public web root.');
            }
        }

        if (self::DRIVER_S3 === $this->driver) {
            foreach (['bucket', 'endpoint', 'access_key', 'secret_key'] as $key) {
                if ('' === trim((string) $this->s3[$key])) {
                    throw new InvalidArgumentException(sprintf('S3 archive storage config "%s" must not be empty.', $key));
                }
            }

            $endpoint = trim((string) $this->s3['endpoint']);
            $scheme   = parse_url($endpoint, PHP_URL_SCHEME);
            if (! in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException('S3 archive storage endpoint must be an HTTP URL.');
            }

            if ($this->productionMode && 'https' !== $scheme) {
                throw new InvalidArgumentException('S3 archive storage endpoint must use HTTPS in production mode.');
            }

            if ((int) $this->s3['multipart_threshold_bytes'] < 5_242_880) {
                throw new InvalidArgumentException('S3 archive storage multipart threshold must be at least 5 MiB.');
            }
        }
    }

    private function assertPrefix(string $prefix, string $driver): void
    {
        if ('' === $prefix || str_starts_with($prefix, '/') || str_ends_with($prefix, '/')) {
            throw new InvalidArgumentException(sprintf(
                '%s archive storage prefix must be non-empty and must not start or end with "/".',
                $driver,
            ));
        }
    }

    /** @return array{path: string, prefix: string} */
    private static function readLocalConfig(ConfigReader $configReader): array
    {
        return [
            'path'   => $configReader->string('storage.archive.local.path', 'data/package-archives'),
            'prefix' => $configReader->string('storage.archive.local.prefix', 'private'),
        ];
    }

    /**
     * @return array{
     *     bucket: string,
     *     region: string,
     *     endpoint: string,
     *     access_key: string,
     *     secret_key: string,
     *     path_style_endpoint: bool,
     *     use_ssl: bool,
     *     prefix: string,
     *     multipart_threshold_bytes: int,
     *     temporary_url_ttl_seconds: int
     * }
     */
    private static function readS3Config(ConfigReader $configReader): array
    {
        return [
            'bucket'                    => $configReader->string('storage.archive.s3.bucket', ''),
            'region'                    => $configReader->string('storage.archive.s3.region', 'fsn1'),
            'endpoint'                  => $configReader->string('storage.archive.s3.endpoint', ''),
            'access_key'                => $configReader->string('storage.archive.s3.access_key', ''),
            'secret_key'                => $configReader->string('storage.archive.s3.secret_key', ''),
            'path_style_endpoint'       => $configReader->bool('storage.archive.s3.path_style_endpoint', false),
            'use_ssl'                   => $configReader->bool('storage.archive.s3.use_ssl', true),
            'prefix'                    => $configReader->string('storage.archive.s3.prefix', 'private'),
            'multipart_threshold_bytes' => $configReader->int('storage.archive.s3.multipart_threshold_bytes', 16_777_216),
            'temporary_url_ttl_seconds' => $configReader->int('storage.archive.s3.temporary_url_ttl_seconds', 300),
        ];
    }
}
