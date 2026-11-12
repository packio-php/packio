<?php

declare(strict_types=1);

namespace PackioTest\Support\Fixture;

use Cycle\Database\DatabaseProviderInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function file_put_contents;
use function getenv;
use function is_string;

final class FailingPostgresSchemaLifecycleTest extends IntegrationTestCase
{
    #[Test]
    public function itFailsAfterCreatingItsSchema(): void
    {
        $schemaPath = getenv('PACKIO_TEST_FAILURE_SCHEMA_FILE');
        if (false === $schemaPath || '' === $schemaPath) {
            throw new RuntimeException('PACKIO_TEST_FAILURE_SCHEMA_FILE is required.');
        }

        $provider = $this->getService('dbal');
        if (! $provider instanceof DatabaseProviderInterface) {
            throw new RuntimeException('Test database provider is not configured.');
        }

        $schema = $provider->database()->query('SELECT current_schema()')->fetchColumn();
        if (! is_string($schema) || '' === $schema) {
            throw new RuntimeException('Unable to determine the test schema.');
        }

        if (false === file_put_contents($schemaPath, $schema)) {
            throw new RuntimeException('Unable to record the test schema.');
        }

        throw new RuntimeException('Intentional failure to verify PostgreSQL schema cleanup.');
    }
}
