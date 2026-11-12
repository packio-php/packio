<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use Common\Infrastructure\HttpClient\SafeGitEnvironment;
use Common\Infrastructure\Process\LimitedProcessRunner;
use Common\Infrastructure\Resource\ResourceLimits;
use FilesystemIterator;
use Laminas\Diactoros\Stream;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function array_map;
use function base64_encode;
use function bin2hex;
use function filesize;
use function fopen;
use function getenv;
use function implode;
use function is_dir;
use function is_string;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function trim;
use function unlink;

final readonly class GenericGitDistArchiveFetcher
{
    public function __construct(
        private SafeGitEnvironment $safeGitEnvironment = new SafeGitEnvironment(),
        private LimitedProcessRunner $limitedProcessRunner = new LimitedProcessRunner(),
        private ResourceLimits $resourceLimits = new ResourceLimits()
    ) {}

    /**
     * @param array<string, mixed> $providerConfig
     */
    public function fetchArchive(
        string $repository,
        string $reference,
        ?string $token = null,
        array $providerConfig = []
    ): DistArchiveStream {
        $cloneUrl = trim((string) ($providerConfig['clone_url'] ?? ''));
        if ('' === $cloneUrl) {
            throw new RuntimeException('Generic Git source has no clone URL configured.');
        }

        $tmpRoot     = sys_get_temp_dir() . '/packio-git-archive-' . bin2hex(random_bytes(8));
        $checkoutDir = $tmpRoot . '/repo';
        $archivePath = $tmpRoot . '/archive.zip';

        if (! mkdir($tmpRoot, 0o700, true) && ! is_dir($tmpRoot)) {
            throw new RuntimeException('Failed to create temporary archive directory.');
        }

        try {
            $environment = $this->gitEnvironment(
                $cloneUrl,
                $token,
                (string) ($providerConfig['auth_scheme'] ?? 'bearer'),
                (string) ($providerConfig['auth_username'] ?? 'git'),
            );
            $this->runGit(['git', 'clone', '--quiet', '--no-checkout', $cloneUrl, $checkoutDir], $environment, $tmpRoot, $tmpRoot);
            $this->runGit(['git', 'archive', '--format=zip', '--output=' . $archivePath, $reference], $environment, $checkoutDir, $tmpRoot, $this->resourceLimits->archiveBytes);

            $stream = $this->openArchiveStream($archivePath);

            return new DistArchiveStream(
                body: new Stream($stream),
                fileName: $repository . '-' . $reference . '.zip',
            );
        } finally {
            $this->deleteDirectory($tmpRoot);
        }
    }

    /**
     * @return resource
     */
    private function openArchiveStream(string $archivePath): mixed
    {
        if (filesize($archivePath) > $this->resourceLimits->archiveBytes) {
            throw new RuntimeException('Generated archive exceeds the size limit.');
        }
        $source = fopen($archivePath, 'rb');
        if (false === $source) {
            throw new RuntimeException('Failed to read generated generic Git archive.');
        }

        // POSIX keeps the open archive readable after the workspace is unlinked.
        // Returning this handle avoids a second archive-sized temporary copy.
        return $source;
    }

    /** @param list<string> $command
     * @param array<string, string> $environment
     */
    private function runGit(
        array $command,
        array $environment,
        string $cwd,
        string $workspace,
        ?int $fileLimit = null
    ): void {
        $processResult = $this->limitedProcessRunner->run(implode(' ', array_map(escapeshellarg(...), $command)), $environment, $cwd, $workspace, $fileLimit);
        if (0 !== $processResult->exitCode) {
            throw new RuntimeException('Failed to build generic Git archive.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function gitEnvironment(string $url, ?string $token, string $authScheme, string $authUsername): array
    {
        /** @var array<string, string> $environment */
        $environment = getenv();
        $environment = [
            ...SafeGitEnvironment::sanitize($environment),
            'GIT_TERMINAL_PROMPT' => '0',
            'GCM_INTERACTIVE'     => 'Never',
            'GIT_ASKPASS'         => '',
            'SSH_ASKPASS'         => '',
            'SSH_ASKPASS_REQUIRE' => 'never',
        ];

        if (! is_string($token) || '' === trim($token)) {
            return [...$environment, ...$this->safeGitEnvironment->forUrl($url)];
        }

        $authUsername = '' !== trim($authUsername) ? trim($authUsername) : 'git';
        $header       = match ($authScheme) {
            'basic'  => 'Authorization: Basic ' . base64_encode($authUsername . ':' . trim($token)),
            'bearer' => 'Authorization: Bearer ' . trim($token),
            default  => '',
        };

        if ('' === $header) {
            return [...$environment, ...$this->safeGitEnvironment->forUrl($url)];
        }

        return [
            ...$environment,
            ...$this->safeGitEnvironment->forUrl($url, $header),
        ];
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
