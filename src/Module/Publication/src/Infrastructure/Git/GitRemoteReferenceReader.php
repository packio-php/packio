<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Git;

use Common\Infrastructure\HttpClient\SafeGitEnvironment;
use RuntimeException;

use function escapeshellarg;
use function sprintf;
use function trim;

final readonly class GitRemoteReferenceReader
{
    public function __construct(
        private GitCommandRunnerInterface $gitCommandRunner,
        private SafeGitEnvironment $safeGitEnvironment = new SafeGitEnvironment()
    ) {}

    /**
     * @param list<string> $headerValues
     *
     * @return array{default_branch: string, tags: list<GitReference>, branches: list<GitReference>}
     */
    public function lsRemote(string $remoteUrl, array $headerValues = []): array
    {
        $headers       = [] === $headerValues ? [''] : $headerValues;
        $lastException = new RuntimeException('Failed to inspect remote git references: git ls-remote failed.');

        foreach ($headers as $header) {
            try {
                $result = $this->gitCommandRunner->run(
                    $this->lsRemoteCommand($remoteUrl),
                    $this->safeGitEnvironment->forUrl($remoteUrl, $header),
                );
                if (! $result->successful()) {
                    throw new RuntimeException(sprintf(
                        'Failed to inspect remote git references: %s',
                        '' !== trim($result->stderr) ? trim($result->stderr) : 'git ls-remote failed.',
                    ));
                }

                return GitReferenceParser::parseLsRemote($result->stdout);
            } catch (RuntimeException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException;
    }

    private function lsRemoteCommand(string $remoteUrl): string
    {
        return sprintf(
            "git ls-remote --symref %s HEAD 'refs/tags/*' 'refs/heads/*'",
            escapeshellarg($remoteUrl),
        );
    }
}
