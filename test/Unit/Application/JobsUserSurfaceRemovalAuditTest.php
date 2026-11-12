<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application;

use FilesystemIterator;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_exists;
use function file_get_contents;
use function implode;
use function in_array;
use function str_contains;
use function strrpos;
use function strtolower;
use function substr;

final class JobsUserSurfaceRemovalAuditTest extends TestCase
{
    private const array SCANNED_EXTENSIONS = ['php', 'svelte', 'ts', 'md'];

    private const array FORBIDDEN_PATTERNS = [
        'Pages/Jobs',
        'Jobs/Index',
        'Jobs/Show',
        'jobs.view',
        'jobs.manage',
        'jobs:read',
        'jobs:write',
        'organizations/{organization}/jobs',
        ':organization/jobs',
        'You can track progress in the Jobs section.',
        'Sources, webhooks, packages, releases, and jobs',
        'Repository webhooks and sync jobs',
    ];

    #[DataProvider('scannedTreesProvider')]
    #[Test]
    public function sourceTreeHasNoUserFacingJobsSurfaceReferences(string $treePath): void
    {
        $treeLabel  = strtolower(substr($treePath, (int) strrpos($treePath, '/')) ?: $treePath);
        $violations = [];

        foreach ($this->scan($treePath) as $file => $contents) {
            if ($this->isAllowed($file)) {
                continue;
            }

            foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $violations[] = $file . ' contains "' . $pattern . '"';
                }
            }
        }

        self::assertSame([], $violations, 'User-facing Jobs surface references found in "' . $treeLabel . '": ' . "\n" . implode("\n", $violations));
    }

    public static function scannedTreesProvider(): Generator
    {
        $root = dirname(__DIR__, 3);

        yield 'application sources with docs' => [$root . '/src'];

        yield 'route and access configuration' => [$root . '/config'];

        yield 'frontend sources' => [$root . '/resources/js'];

        yield 'documentation' => [$root . '/docs'];
    }

    #[Test]
    public function generatedOpenApiContractHasNoOrganizationJobsPaths(): void
    {
        $openApiPath = dirname(__DIR__, 3) . '/public/openapi.json';

        if (! file_exists($openApiPath)) {
            $this->markTestSkipped('The generated OpenAPI artifact is not present.');
        }

        $violations = [];
        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (str_contains((string) file_get_contents($openApiPath), $pattern)) {
                $violations[] = $pattern;
            }
        }

        self::assertSame([], $violations, 'The generated OpenAPI artifact still contains organization Jobs references');
    }

    /**
     * @return Generator<string, string>
     */
    private function scan(string $directory): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if (! $fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower($fileInfo->getExtension());
            if (! in_array($extension, self::SCANNED_EXTENSIONS, true)) {
                continue;
            }

            yield $fileInfo->getPathname() => (string) file_get_contents($fileInfo->getPathname());
        }
    }

    private function isAllowed(string $filePath): bool
    {
        $path = strtolower($filePath);

        // The generic Inertia partial-reload fixture is not product surface.
        if (str_contains($path, 'e2e/fixture') || str_contains($path, 'e2e/inertia-smoke')) {
            return true;
        }

        // Operational background-worker documentation kept intentionally.
        return str_contains($path, '/docs/commands.md') || str_contains($path, '/docs/docker.md') || str_contains($path, '/docs/oauth.md');
    }
}
