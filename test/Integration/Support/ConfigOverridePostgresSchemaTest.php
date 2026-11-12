<?php

declare(strict_types=1);

namespace PackioTest\Integration\Support;

use Cycle\Database\DatabaseProviderInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function dirname;

final class ConfigOverridePostgresSchemaTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3) . '/config/config.php';

        $config['sources']['providers']['github']['enabled']  = true;
        $this->serviceOverrides['config']                     = $config;

        parent::setUp();
    }

    #[Test]
    public function itKeepsTheIsolatedPostgresSchemaWhenConfigIsOverridden(): void
    {
        $provider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $provider);

        $schema = $provider->database()->query('SELECT current_schema()')->fetchColumn();
        self::assertIsString($schema);
        self::assertMatchesRegularExpression('/^packio_test_\d+_[a-f0-9]{32}$/', $schema);
    }
}
