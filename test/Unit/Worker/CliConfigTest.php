<?php

declare(strict_types=1);

namespace PackioTest\Unit\Worker;

use Packio\Worker\Infrastructure\Cli\WorkerRunCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CliConfigTest extends TestCase
{
    #[Test]
    public function cliConfigRegistersWorkerRunCommand(): void
    {
        $config = require __DIR__ . '/../../../src/Module/Worker/config/cli.config.php';

        self::assertIsArray($config);
        self::assertArrayHasKey('laminas-cli', $config);
        self::assertArrayHasKey('commands', $config['laminas-cli']);
        self::assertArrayHasKey('worker:run', $config['laminas-cli']['commands']);
        self::assertSame(
            WorkerRunCommand::class,
            $config['laminas-cli']['commands']['worker:run'],
        );
    }
}
