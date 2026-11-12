<?php

declare(strict_types=1);

namespace PackioTest\Integration\Support;

use const PHP_BINARY;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Organization\Application\Command\OrganizationCommandService;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;

use function dirname;
use function escapeshellarg;
use function fclose;
use function file_get_contents;
use function getenv;
use function is_resource;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

final class PostgresSchemaLifecycleTest extends IntegrationTestCase
{
    /**
     * @return array{organizationId: string, schema: string}
     */
    #[Test]
    public function firstTestReceivesMigratedSharedSchema(): array
    {
        $schema = $this->currentSchema();

        self::assertMatchesRegularExpression('/^packio_test_\d+_[a-f0-9]{32}$/', $schema);
        self::assertSame(1, $this->tableCount('migrations'));

        $organizationCommandService = $this->getService(OrganizationCommandService::class);
        self::assertInstanceOf(OrganizationCommandService::class, $organizationCommandService);

        $organization = $organizationCommandService->registerOrganization('Lifecycle test organization');

        return [
            'organizationId' => $organization->getIdentifier()->toString(),
            'schema'         => $schema,
        ];
    }

    /**
     * @param array{organizationId: string, schema: string} $firstTest
     */
    #[Test]
    #[Depends('firstTestReceivesMigratedSharedSchema')]
    public function secondTestReceivesCleanSharedSchema(array $firstTest): void
    {
        $secondSchema = $this->currentSchema();

        self::assertSame($firstTest['schema'], $secondSchema);
        self::assertSame(1, $this->tableCount('migrations'));
        self::assertSame(0, (int) $this->database()->query(
            'SELECT COUNT(*) FROM organizations WHERE uuid = ?',
            [$firstTest['organizationId']],
        )->fetchColumn());
    }

    #[Test]
    public function failedTestStillDropsItsSchema(): void
    {
        $schemaPath = tempnam(sys_get_temp_dir(), 'packio-failed-schema-');
        self::assertIsString($schemaPath);

        try {
            $result = $this->runFailingLifecycleFixture($schemaPath);

            self::assertNotSame(0, $result['exitCode'], $result['stderr'] . $result['stdout']);
            $schema = trim((string) file_get_contents($schemaPath));
            self::assertMatchesRegularExpression('/^packio_test_\d+_[a-f0-9]{32}$/', $schema);
            self::assertSame(0, (int) $this->database()->query(
                'SELECT COUNT(*) FROM pg_namespace WHERE nspname = ?',
                [$schema],
            )->fetchColumn());
        } finally {
            @unlink($schemaPath);
        }
    }

    #[Test]
    public function connectionsDoNotWaitForSynchronousCommit(): void
    {
        self::assertSame('off', $this->database()->query('SHOW synchronous_commit')->fetchColumn());
    }

    private function database(): DatabaseInterface
    {
        $provider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $provider);

        return $provider->database();
    }

    private function currentSchema(): string
    {
        $schema = $this->database()->query('SELECT current_schema()')->fetchColumn();
        self::assertIsString($schema);
        self::assertNotSame('', $schema);

        return $schema;
    }

    private function tableCount(string $table): int
    {
        return (int) $this->database()->query(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?',
            [$table],
        )->fetchColumn();
    }

    /**
     * @return array{exitCode: int, stderr: string, stdout: string}
     */
    private function runFailingLifecycleFixture(string $schemaPath): array
    {
        $command = sprintf(
            '%s vendor/bin/phpunit --configuration=phpunit.xml.dist --no-coverage %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('test/Support/Fixture/FailingPostgresSchemaLifecycleTest.php'),
        );
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $environment                                    = getenv();
        $environment['PACKIO_TEST_FAILURE_SCHEMA_FILE'] = $schemaPath;
        $process                                        = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 3), $environment);
        self::assertTrue(is_resource($process));

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stderr'   => $stderr,
            'stdout'   => $stdout,
        ];
    }
}
