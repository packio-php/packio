<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Facade;

use Packio\Registry\Api\ArchiveStorageApiFacadeInterface;
use Packio\Registry\Infrastructure\Storage\ArchiveStorageConfig;

final readonly class ArchiveStorageApiFacade implements ArchiveStorageApiFacadeInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config) {}

    public function localArchiveDirectory(): ?string
    {
        $archiveStorageConfig = ArchiveStorageConfig::fromApplicationConfig($this->config);

        return ArchiveStorageConfig::DRIVER_LOCAL === $archiveStorageConfig->driver
            ? (string) $archiveStorageConfig->local['path']
            : null;
    }
}
