<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_key_exists;
use function dirname;
use function getenv;
use function putenv;

final class SupportedDatabaseConfigurationTest extends TestCase
{
    #[Test]
    public function missingDefaultDatabaseConnectionSelectsPostgres(): void
    {
        $config = $this->loadConfigWithDatabaseConnection(null);

        self::assertSame('postgres', $config['cycle']['db-config']['databases']['default']['connection']);
    }

    #[Test]
    public function explicitPostgresDatabaseConnectionIsAccepted(): void
    {
        $config = $this->loadConfigWithDatabaseConnection('postgres');

        self::assertSame('postgres', $config['cycle']['db-config']['databases']['default']['connection']);
    }

    #[Test]
    public function unsupportedDatabaseConnectionIsRejectedBeforeConfigurationIsReturned(): void
    {
        foreach (['sqlite', 'unknown'] as $connection) {
            try {
                $this->loadConfigWithDatabaseConnection($connection);
                self::fail('Expected unsupported database connection to be rejected.');
            } catch (RuntimeException $exception) {
                self::assertSame('Packio supports PostgreSQL only for this release.', $exception->getMessage());
            }
        }
    }

    /** @return array{cycle: array<string, mixed>} */
    private function loadConfigWithDatabaseConnection(?string $connection): array
    {
        $environment        = [];

        foreach (['DEFAULT_DB_CONNECTION'] as $key) {
            $environment[$key] = [
                'getenv'        => getenv($key),
                'env_exists'    => array_key_exists($key, $_ENV),
                'env'           => $_ENV[$key] ?? null,
                'server_exists' => array_key_exists($key, $_SERVER),
                'server'        => $_SERVER[$key] ?? null,
            ];
        }

        try {
            $this->setEnvironmentValue('DEFAULT_DB_CONNECTION', $connection);

            return require dirname(__DIR__, 4) . '/config/autoload/cycle-orm.global.php';
        } finally {
            foreach ($environment as $key => $previous) {
                if (false === $previous['getenv']) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $previous['getenv']);
                }

                if ($previous['env_exists']) {
                    $_ENV[$key] = $previous['env'];
                } else {
                    unset($_ENV[$key]);
                }

                if ($previous['server_exists']) {
                    $_SERVER[$key] = $previous['server'];
                } else {
                    unset($_SERVER[$key]);
                }
            }
        }
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if (null === $value) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key . '=' . $value);
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
}
