<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Config;

use InvalidArgumentException;
use Laminas\ServiceManager\ServiceManager;
use Packio\Application\ConfigProvider;
use Packio\Application\Infrastructure\Cli\RateLimitCleanupCommand;
use Packio\Application\Security\RateLimiter\FileRateLimiter;
use Packio\Application\Security\RateLimiter\FileRateLimiterFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function dirname;
use function getenv;
use function is_dir;
use function putenv;
use function random_bytes;
use function sys_get_temp_dir;

final class RateLimiterConfigurationTest extends TestCase
{
    #[Test]
    public function defaultRetentionCoversFinalMergedPolicyOverrides(): void
    {
        $limiter = $this->limiter([
            'custom' => [
                'decay_seconds' => 7200,
            ],
        ]);
        self::assertSame([
            'scanned' => 0,
            'deleted' => 0,
            'skipped' => 0,
        ], $limiter->cleanup(7200, 1, true));
        $this->expectException(InvalidArgumentException::class);
        $limiter->cleanup(7199, 1, true);
    }

    #[Test]
    public function explicitRetentionRejectsLargerFinalPolicyOverride(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->limiter([
            'retention_seconds' => 3600,
            'custom'            => [
                'decay_seconds' => 7200,
            ],
        ]);
    }

    #[Test]
    public function nativeCommandRegistrationAndDryRunDoNotCreateStorage(): void
    {
        $config = (new ConfigProvider())();
        self::assertSame(RateLimitCleanupCommand::class, $config['laminas-cli']['commands']['rate-limit:cleanup'] ?? null);
        $directory        = sys_get_temp_dir() . '/packio-o2-cli-' . bin2hex(random_bytes(8));
        $commandTester    = new CommandTester(new RateLimitCleanupCommand(new FileRateLimiter([
            'storage_dir' => $directory,
        ])));
        self::assertSame(0, $commandTester->execute([
            '--retention-seconds' => '3600',
            '--dry-run'           => true,
        ]));
        self::assertStringContainsString('scanned=0 deleted=0 skipped=0', $commandTester->getDisplay());
        self::assertFalse(is_dir($directory));
    }

    #[Test]
    public function commandRejectsInvalidNumericOptionsWithoutOpeningStorage(): void
    {
        $config = (new ConfigProvider())();
        self::assertSame(RateLimitCleanupCommand::class, $config['laminas-cli']['commands']['rate-limit:cleanup'] ?? null);
        $directory        = sys_get_temp_dir() . '/packio-o2-cli-' . bin2hex(random_bytes(8));
        $commandTester    = new CommandTester(new RateLimitCleanupCommand(new FileRateLimiter([
            'storage_dir' => $directory,
        ])));
        foreach ([[
            '--retention-seconds' => '3600oops',
        ], [
            '--retention-seconds' => '-1',
        ], [
            '--retention-seconds' => '3600',
            '--limit'             => '1001',
        ], [
            '--retention-seconds' => '3600',
            '--limit'             => '0',
        ]] as $options) {
            self::assertSame(2, $commandTester->execute($options));
            self::assertFalse(is_dir($directory));
        }
    }

    #[Test]
    public function malformedRetentionEnvironmentIsRejectedBeforeConfigurationIsReturned(): void
    {
        $previous = getenv('RATE_LIMIT_RETENTION_SECONDS');
        putenv('RATE_LIMIT_RETENTION_SECONDS=3600oops');

        try {
            $this->expectException(InvalidArgumentException::class);

            require dirname(__DIR__, 4) . '/config/autoload/packio.global.php';
        } finally {
            putenv(false === $previous ? 'RATE_LIMIT_RETENTION_SECONDS' : 'RATE_LIMIT_RETENTION_SECONDS=' . $previous);
        }
    }

    /** @param array<string, mixed> $rateLimits */
    private function limiter(array $rateLimits): FileRateLimiter
    {
        $config = require dirname(__DIR__, 4) . '/src/Application/config/dependencies.config.php';
        self::assertSame(FileRateLimiterFactory::class, $config['dependencies']['factories'][FileRateLimiter::class]);
        $rateLimits['storage_dir'] = sys_get_temp_dir() . '/packio-o2-config-' . bin2hex(random_bytes(8));
        $container                 = new ServiceManager([
            'services' => [
                'config'              => [
                    'security' => [
                        'rate_limits' => $rateLimits,
                    ],
                ],
            ],
        ]);

        return (new FileRateLimiterFactory())($container);
    }
}
