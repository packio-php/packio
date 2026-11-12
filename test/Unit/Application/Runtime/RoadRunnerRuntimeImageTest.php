<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Runtime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function strpos;

final class RoadRunnerRuntimeImageTest extends TestCase
{
    #[Test]
    public function itCreatesTheGeneratedProxyCacheDirectoryBeforeTheApplicationBoots(): void
    {
        self::assertStringContainsString('mkdir -p /app/data/cache/proxies', $this->entrypoint());
        self::assertStringContainsString('mkdir -p /app/data/cache/proxies', $this->dockerfile());
    }

    #[Test]
    public function itProtectsTheGoridgeProtocolBeforeTheApplicationBootstrap(): void
    {
        $entrypoint = $this->roadRunnerEntrypoint();

        self::assertNotFalse(strpos($entrypoint, 'ob_start'));
        self::assertNotFalse(strpos($entrypoint, "require 'vendor/autoload.php'"));
        self::assertLessThan(
            strpos($entrypoint, "require 'vendor/autoload.php'"),
            strpos($entrypoint, 'ob_start'),
        );
    }

    #[Test]
    public function itExposesTheHttpPort(): void
    {
        self::assertStringContainsString('EXPOSE 80', $this->dockerfile());
    }

    #[Test]
    public function itProbesRoadRunnerStatusHealthInsteadOfTheUnroutedRootPath(): void
    {
        self::assertStringContainsString('http://127.0.0.1:2114/health', $this->read('.docker/healthcheck-roadrunner.sh'));
    }

    #[Test]
    public function itRunsTheProductionTargetWithProductionOnlyConfigurationInRuntimeSmoke(): void
    {
        $runtimeSmokeOverride = $this->read('compose.runtime-smoke.yaml');

        self::assertStringContainsString('target: prod', $runtimeSmokeOverride);
        self::assertStringContainsString('APP_DEBUG: "false"', $runtimeSmokeOverride);
        self::assertStringContainsString('APP_PRODUCTION: "true"', $runtimeSmokeOverride);
        self::assertStringContainsString('APP_SECRET_ENCRYPTION_KEY:', $runtimeSmokeOverride);
        self::assertStringNotContainsString('SESSION_COOKIE_SECURE', $runtimeSmokeOverride);
    }

    private function entrypoint(): string
    {
        return $this->read('.docker/entrypoint-roadrunner.sh');
    }

    private function dockerfile(): string
    {
        return $this->read('.docker/Dockerfile.roadrunner');
    }

    private function roadRunnerEntrypoint(): string
    {
        return $this->read('public/index-roadrunner.php');
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 4) . '/' . $path);

        self::assertIsString($contents);

        return $contents;
    }
}
