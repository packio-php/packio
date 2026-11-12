<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Git;

use const PHP_BINARY;

use Packio\Publication\Infrastructure\Git\GitCommandRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function sprintf;

final class GitCommandRunnerTest extends TestCase
{
    #[Test]
    public function runAppliesNonInteractiveGitEnvironment(): void
    {
        $gitCommandResult = new GitCommandRunner()->run(sprintf(
            '%s -r %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('echo getenv("GIT_TERMINAL_PROMPT") . "|" . getenv("GCM_INTERACTIVE") . "|" . getenv("SSH_ASKPASS_REQUIRE");'),
        ));

        self::assertTrue($gitCommandResult->successful());
        self::assertSame('0|Never|never', $gitCommandResult->stdout);
    }

    #[Test]
    public function runReturnsStderrAndExitCodeForFailedCommand(): void
    {
        $gitCommandResult = new GitCommandRunner()->run(sprintf(
            '%s -r %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('fwrite(STDERR, "upstream failed"); exit(17);'),
        ));

        self::assertFalse($gitCommandResult->successful());
        self::assertSame('upstream failed', $gitCommandResult->stderr);
        self::assertSame(17, $gitCommandResult->exitCode);
    }
}
