<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Git;

use Packio\Publication\Infrastructure\Git\GitCommandResult;
use Packio\Publication\Infrastructure\Git\GitCommandRunnerInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function count;
use function sprintf;

final class GitRemoteReferenceReaderTest extends TestCase
{
    #[Test]
    public function lsRemoteTriesHeaderValuesUntilOneSucceeds(): void
    {
        $runner = new class implements GitCommandRunnerInterface {
            /** @var list<string> */
            public array $commands = [];

            /** @var list<array<string, string>> */
            public array $environments = [];

            public function run(string $command, array $environment = []): GitCommandResult
            {
                $this->commands[]     = $command;
                $this->environments[] = $environment;

                if (1 === count($this->commands)) {
                    return new GitCommandResult('', 'authentication failed', 128);
                }

                return new GitCommandResult(<<<'OUTPUT'
                    ref: refs/heads/main	HEAD
                    1111111111111111111111111111111111111111	refs/heads/main
                    2222222222222222222222222222222222222222	refs/tags/v1.0.0
                    3333333333333333333333333333333333333333	refs/tags/v1.0.0^{}
                    OUTPUT, '', 0);
            }
        };

        $refs = new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git())->lsRemote(
            'https://example.test/acme/demo.git',
            ['Authorization: Bearer first', 'Authorization: token second'],
        );

        self::assertSame('main', $refs['default_branch']);
        self::assertCount(1, $refs['branches']);
        self::assertSame('main', $refs['branches'][0]->name);
        self::assertCount(1, $refs['tags']);
        self::assertEquals(new GitReference('v1.0.0', '3333333333333333333333333333333333333333', GitReference::TYPE_TAG), $refs['tags'][0]);
        self::assertStringNotContainsString('Authorization: Bearer first', $runner->commands[0]);
        self::assertStringNotContainsString('Authorization: token second', $runner->commands[1]);
        self::assertSame('Authorization: Bearer first', $runner->environments[0]['GIT_CONFIG_VALUE_0'] ?? null);
        self::assertSame('Authorization: token second', $runner->environments[1]['GIT_CONFIG_VALUE_0'] ?? null);
        self::assertSame('http.extraHeader', $runner->environments[0]['GIT_CONFIG_KEY_0'] ?? null);
    }

    #[Test]
    public function lsRemoteThrowsLastFailureWhenAllHeaderValuesFail(): void
    {
        $runner = new class implements GitCommandRunnerInterface {
            public function run(string $command, array $environment = []): GitCommandResult
            {
                unset($environment);

                return new GitCommandResult('', sprintf('failed for %s', $command), 128);
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to inspect remote git references: failed for git');

        new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git())->lsRemote(
            'https://example.test/acme/demo.git',
            ['Authorization: Bearer first'],
        );
    }
}
