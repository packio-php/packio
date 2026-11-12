<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Runtime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;

final class RoadRunnerConfigurationTest extends TestCase
{
    #[Test]
    public function itKeepsTheRequestQueueBoundedInsideTheHttpPool(): void
    {
        $configuration = $this->configuration();

        self::assertMatchesRegularExpression(
            '/pool:\n(?:[ ]{4,}[^\n]+\n)*[ ]{4,}max_queue_size: \$\{RR_MAX_QUEUE_SIZE\}/',
            $configuration,
        );
    }

    #[Test]
    public function itUsesAnAbsoluteWorkerEntrypointPath(): void
    {
        self::assertStringContainsString('command: "php /app/public/index-roadrunner.php"', $this->configuration());
    }

    #[Test]
    public function itUsesProductionLogsWithoutUnredactedHttpAccessLogs(): void
    {
        $configuration = $this->configuration();

        self::assertStringContainsString('access_logs: false', $configuration);
        self::assertStringContainsString("logs:\n  mode: production\n  level: warn\n  encoding: json", $configuration);
        self::assertStringContainsString('output: ["stderr"]', $configuration);
        self::assertStringContainsString('err_output: ["stderr"]', $configuration);
    }

    #[Test]
    public function itServesOnlyAllowlistedPublicAssets(): void
    {
        $configuration = $this->configuration();

        self::assertStringContainsString('allow: [".css", ".ico", ".js", ".json", ".png", ".svg", ".woff2"]', $configuration);
        self::assertStringContainsString('".php"', $configuration);
        self::assertStringContainsString('".bak"', $configuration);
        self::assertStringContainsString('".env"', $configuration);
    }

    private function configuration(): string
    {
        $configuration = file_get_contents(dirname(__DIR__, 4) . '/config/roadrunner/.rr.yaml');

        self::assertIsString($configuration);

        return $configuration;
    }
}
