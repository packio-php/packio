<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Git;

use Common\Infrastructure\HttpClient\SafeGitEnvironment;
use Common\Infrastructure\Process\LimitedProcessRunner;

use function getenv;
use function sys_get_temp_dir;

final readonly class GitCommandRunner implements GitCommandRunnerInterface
{
    public function __construct(private LimitedProcessRunner $limitedProcessRunner = new LimitedProcessRunner()) {}

    public function run(string $command, array $environment = []): GitCommandResult
    {
        $processResult = $this->limitedProcessRunner->run($command, $this->environment($environment), sys_get_temp_dir());

        return new GitCommandResult($processResult->stdout, $processResult->stderr, $processResult->exitCode);
    }

    /**
     * @return array<string, string>
     */
    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function environment(array $overrides): array
    {
        /** @var array<string, string> $environment */
        $environment = getenv();

        return [
            ...SafeGitEnvironment::sanitize($environment),
            'GIT_TERMINAL_PROMPT' => '0',
            'GCM_INTERACTIVE'     => 'Never',
            'GIT_ASKPASS'         => '',
            'SSH_ASKPASS'         => '',
            'SSH_ASKPASS_REQUIRE' => 'never',
            ...$overrides,
        ];
    }
}
