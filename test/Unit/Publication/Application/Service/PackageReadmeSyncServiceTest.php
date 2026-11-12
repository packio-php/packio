<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Application\Service;

use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

#[CoversClass(PackageReadmeSyncService::class)]
#[AllowMockObjectsWithoutExpectations]
final class PackageReadmeSyncServiceTest extends TestCase
{
    public function testSynchronizeUpdatesReadme(): void
    {
        $port = $this->createMock(SynchronizerDependenciesPort::class);
        $port
            ->expects(self::once())
            ->method('updatePackageReadmeHtml')
            ->with('org-1', 'vendor/package', '<p>README</p>')
        ;

        $result = new PackageReadmeSyncService($port)->synchronize(
            'org-1',
            'vendor/package',
            static fn (): string => '<p>README</p>',
        );

        self::assertSame('updated', $result['status']);
    }

    public function testSynchronizeLogsReadmeExtractionFromSharedService(): void
    {
        $port = $this->createMock(SynchronizerDependenciesPort::class);
        $port
            ->expects(self::once())
            ->method('updatePackageReadmeHtml')
            ->with('org-1', 'vendor/package', '<p>README</p>')
        ;

        /** @var list<string> $loggedMessages */
        $loggedMessages = [];
        $logger         = $this->createMock(LoggerInterface::class);
        $logger
            ->method('info')
            ->willReturnCallback(static function(string $message, array $context = []) use (&$loggedMessages): void {
                $loggedMessages[] = $message;
            })
        ;

        $result = new PackageReadmeSyncService($port, $logger)->synchronize(
            'org-1',
            'vendor/package',
            static fn (): string => '<p>README</p>',
        );

        self::assertSame('updated', $result['status']);
        self::assertSame(
            [
                'Package README sync started',
                'Package README extracted',
            ],
            $loggedMessages,
        );
    }

    public function testSynchronizeClearsReadmeWhenExtractorReturnsNull(): void
    {
        $port = $this->createMock(SynchronizerDependenciesPort::class);
        $port
            ->expects(self::once())
            ->method('updatePackageReadmeHtml')
            ->with('org-1', 'vendor/package', null)
        ;

        $result = new PackageReadmeSyncService($port)->synchronize(
            'org-1',
            'vendor/package',
            static fn (): ?string => null,
        );

        self::assertSame('cleared', $result['status']);
    }

    public function testSynchronizeReturnsFailureAndDoesNotUpdateWhenExtractorFails(): void
    {
        $port = $this->createMock(SynchronizerDependenciesPort::class);
        $port
            ->expects(self::never())
            ->method('updatePackageReadmeHtml')
        ;

        $captured = null;
        $result   = new PackageReadmeSyncService($port)->synchronize(
            'org-1',
            'vendor/package',
            static fn (): ?string => throw new RuntimeException('Provider failed.'),
            null,
            static function(Throwable $throwable) use (&$captured): void {
                $captured = $throwable->getMessage();
            },
        );

        self::assertSame('failed', $result['status']);
        self::assertSame('Provider failed.', $result['message']);
        self::assertSame('Provider failed.', $captured);
    }
}
