<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Readiness;

use Cycle\Database\Config\Postgres\TcpConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Packio\Admin\Api\RuntimeSettingKeysApiFacadeInterface;
use Packio\Application\Infrastructure\Readiness\ReadinessChecker;
use Packio\Registry\Api\ArchiveStorageApiFacadeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function usleep;

#[CoversClass(ReadinessChecker::class)]
final class ReadinessCheckerTest extends TestCase
{
    #[Test]
    public function cacheWindowStartsBeforeProbe(): void
    {
        $probes                   = 0;
        $archiveStorageApiFacade  = self::createStub(ArchiveStorageApiFacadeInterface::class);
        $archiveStorageApiFacade->method('localArchiveDirectory')->willReturnCallback(static function() use (&$probes): never {
            ++$probes;
            usleep(600_000);

            throw new RuntimeException('probe stub');
        });
        $readinessChecker = new ReadinessChecker(
            [
                'cycle' => [
                    'db-config' => [
                        'databases'   => [
                            'default' => [
                                'connection' => 'postgres',
                            ],
                        ],
                        'connections' => [
                            'postgres' => new PostgresDriverConfig(connection: new TcpConnectionConfig(database: 'unused')),
                        ],
                    ],
                ],
            ],
            self::createStub(RuntimeSettingKeysApiFacadeInterface::class),
            $archiveStorageApiFacade,
        );

        $readinessChecker->isReady();
        usleep(500_000);
        $readinessChecker->isReady();

        // The second call is 1.1 s after the first probe started, so the one-second cache has expired.
        self::assertSame(2, $probes);
    }
}
