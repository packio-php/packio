<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity;

use Packio\Identity\Infrastructure\Cli\MakeUserAdminCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CliConfigTest extends TestCase
{
    #[Test]
    public function cliConfigRegistersMakeUserAdminCommand(): void
    {
        $config = require __DIR__ . '/../../../src/Module/Identity/config/cli.config.php';

        self::assertIsArray($config);
        self::assertArrayHasKey('laminas-cli', $config);
        self::assertArrayHasKey('commands', $config['laminas-cli']);
        self::assertArrayHasKey('user:make-admin', $config['laminas-cli']['commands']);
        self::assertSame(
            MakeUserAdminCommand::class,
            $config['laminas-cli']['commands']['user:make-admin'],
        );
    }
}
