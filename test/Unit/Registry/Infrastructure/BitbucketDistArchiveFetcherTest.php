<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure;

use Packio\Registry\Infrastructure\BitbucketDistArchiveFetcher;
use Packio\Registry\Infrastructure\DistArchiveStream;
use Packio\Registry\Infrastructure\GenericGitDistArchiveFetcher;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function mkdir;
use function putenv;
use function str_replace;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(BitbucketDistArchiveFetcher::class)]
#[CoversClass(GenericGitDistArchiveFetcher::class)]
#[CoversClass(DistArchiveStream::class)]
final class BitbucketDistArchiveFetcherTest extends TestCase
{
    #[Test]
    public function itBuildsBitbucketArchiveThroughGitWithXTokenAuth(): void
    {
        $fakeGitBin = sys_get_temp_dir() . '/' . uniqid('packio-fake-git-bin-', true);
        $logPath    = sys_get_temp_dir() . '/' . uniqid('packio-fake-git-log-', true);
        mkdir($fakeGitBin);
        $this->writeFakeGit($fakeGitBin . '/git');

        $originalPath = getenv('PATH') ?: '';
        putenv('PATH=' . $fakeGitBin . ':' . $originalPath);
        putenv('PACKIO_TEST_GIT_LOG=' . $logPath);

        try {
            $distArchiveStream = new BitbucketDistArchiveFetcher(new GenericGitDistArchiveFetcher(OutboundPolicyFixture::git()))
                ->fetchArchive('workspace', 'package', 'feature/Action', 'source-token')
            ;
        } finally {
            putenv('PATH=' . $originalPath);
            putenv('PACKIO_TEST_GIT_LOG');
        }

        self::assertSame('zip-content', $distArchiveStream->body->getContents());
        self::assertSame('package-feature/Action.zip', $distArchiveStream->fileName);

        $log = (string) file_get_contents($logPath);
        self::assertStringContainsString('clone https://bitbucket.org/workspace/package.git', $log);
        self::assertStringContainsString('archive feature/Action', $log);
        self::assertStringContainsString(
            'Authorization: Basic ' . base64_encode('x-token-auth:source-token'),
            $log,
        );
    }

    private function writeFakeGit(string $path): void
    {
        file_put_contents($path, str_replace('__LOG_PATH__', '${PACKIO_TEST_GIT_LOG}', <<<'SH'
            #!/bin/sh
            set -eu

            log="__LOG_PATH__"

            if [ "$1" = "clone" ]; then
                mkdir -p "$5"
                printf 'clone %s\n%s\n' "$4" "${GIT_CONFIG_VALUE_0:-}" >> "$log"
                exit 0
            fi

            if [ "$1" = "archive" ]; then
                output="${3#--output=}"
                printf 'archive %s\n%s\n' "$4" "${GIT_CONFIG_VALUE_0:-}" >> "$log"
                printf 'zip-content' > "$output"
                exit 0
            fi

            exit 1
            SH));
        chmod($path, 0o755);
    }
}
