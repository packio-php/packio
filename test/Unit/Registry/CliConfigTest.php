<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry;

use Packio\Registry\Infrastructure\Cli\ArchiveBackfillCommand;
use Packio\Registry\Infrastructure\Cli\ArchiveDevCleanupCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CliConfigTest extends TestCase
{
    #[Test]
    public function cliConfigRegistersArchiveBackfillCommand(): void
    {
        $config = require __DIR__ . '/../../../src/Module/Registry/config/cli.config.php';

        self::assertIsArray($config);
        self::assertArrayHasKey('laminas-cli', $config);
        self::assertArrayHasKey('commands', $config['laminas-cli']);
        self::assertArrayHasKey('storage:archives:backfill', $config['laminas-cli']['commands']);
        self::assertSame(
            ArchiveBackfillCommand::class,
            $config['laminas-cli']['commands']['storage:archives:backfill'],
        );
        self::assertArrayHasKey('storage:archives:cleanup-dev', $config['laminas-cli']['commands']);
        self::assertSame(
            ArchiveDevCleanupCommand::class,
            $config['laminas-cli']['commands']['storage:archives:cleanup-dev'],
        );
    }
}
