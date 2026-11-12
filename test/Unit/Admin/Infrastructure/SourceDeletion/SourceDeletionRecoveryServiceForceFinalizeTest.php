<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Infrastructure\SourceDeletion;

use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryException;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryService;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(SourceDeletionRecoveryService::class)]
final class SourceDeletionRecoveryServiceForceFinalizeTest extends TestCase
{
    private const string ADMIN_USER_ID   = 'admin-user-1';
    private const string SOURCE_ID       = 'src-1';
    private const string ORGANIZATION_ID = 'org-1';

    #[Test]
    public function itAuditsInterruptedForceFinalizeAfterDeletingPackages(): void
    {
        $archiveCleanup = $this->createStub(ArchiveStorageCleanupApiFacadeInterface::class);
        $archiveCleanup->method('schedulePackageArchiveCleanup')->willReturnOnConsecutiveCalls(
            [
                'discovered' => 1,
                'scheduled'  => 1,
                'failed'     => 0,
            ],
            [
                'discovered' => 1,
                'scheduled'  => 0,
                'failed'     => 1,
            ],
        );

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::once())
            ->method('record')
            ->with(
                'source.delete.force_finalize_interrupted_by_admin',
                self::callback(static fn (array $context): bool => 1 === ($context['packages_deleted'] ?? null)
                    && 1 === ($context['archive_cleanup_failed'] ?? null)
                    && ! isset($context['webhook_events_deleted'])),
            )
        ;

        $sourceApiFacade = $this->sourceApiFacade();
        $sourceApiFacade->expects(self::never())->method('deleteSource');

        $this->expectException(SourceDeletionRecoveryException::class);

        $this->service(
            $sourceApiFacade,
            $this->packageApiFacade([$this->package('acme/one'), $this->package('acme/two')]),
            $auditApiFacade,
            $archiveCleanup,
        )->forceFinalizeDeletion(self::SOURCE_ID, self::ADMIN_USER_ID, 'FORCE DELETE SOURCE', false);
    }

    #[Test]
    public function itDoesNotAuditInterruptedForceFinalizeWhenNothingWasDeleted(): void
    {
        $archiveCleanup = $this->createStub(ArchiveStorageCleanupApiFacadeInterface::class);
        $archiveCleanup->method('schedulePackageArchiveCleanup')
            ->willReturn([
                'discovered' => 1,
                'scheduled'  => 0,
                'failed'     => 1,
            ])
        ;

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::never())->method('record');

        $this->expectException(SourceDeletionRecoveryException::class);

        $this->service(
            $this->sourceApiFacade(),
            $this->packageApiFacade([$this->package('acme/one'), $this->package('acme/two')]),
            $auditApiFacade,
            $archiveCleanup,
        )->forceFinalizeDeletion(self::SOURCE_ID, self::ADMIN_USER_ID, 'FORCE DELETE SOURCE', false);
    }

    #[Test]
    public function itStoresAFixedArchiveWarningAndLogsTheException(): void
    {
        $archiveCleanup = $this->createStub(ArchiveStorageCleanupApiFacadeInterface::class);
        $archiveCleanup->method('schedulePackageArchiveCleanup')
            ->willThrowException(new RuntimeException('SQLSTATE secret'))
        ;

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::once())
            ->method('record')
            ->with(
                'source.delete.force_finalized_by_admin',
                self::callback(static fn (array $context): bool => 'Archive cleanup could not be scheduled.' === ($context['archive_cleanup_warnings'][0]['error'] ?? null)),
            )
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $sourceApiFacade = $this->sourceApiFacade();
        $sourceApiFacade->method('deleteSource')->willReturn(true);

        $this->service(
            $sourceApiFacade,
            $this->packageApiFacade([$this->package('acme/one')]),
            $auditApiFacade,
            $archiveCleanup,
            $logger,
        )->forceFinalizeDeletion(self::SOURCE_ID, self::ADMIN_USER_ID, 'FORCE DELETE SOURCE', true);
    }

    #[Test]
    public function itAuditsInterruptedForceFinalizeWhenDeleteSourceReturnsFalse(): void
    {
        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::once())
            ->method('record')
            ->with(
                'source.delete.force_finalize_interrupted_by_admin',
                self::callback(static fn (array $context): bool => 1 === ($context['packages_deleted'] ?? null)
                    && 0 === ($context['webhook_events_deleted'] ?? null)),
            )
        ;

        $sourceApiFacade = $this->sourceApiFacade();
        $sourceApiFacade->method('deleteSource')->willReturn(false);

        $this->expectException(SourceDeletionRecoveryException::class);
        $this->expectExceptionMessage('Source row could not be deleted.');

        $this->service(
            $sourceApiFacade,
            $this->packageApiFacade([$this->package('acme/one')]),
            $auditApiFacade,
            $this->cleanupScheduling(),
        )->forceFinalizeDeletion(self::SOURCE_ID, self::ADMIN_USER_ID, 'FORCE DELETE SOURCE', true);
    }

    #[Test]
    public function itRethrowsTheOriginalExceptionAndLogsWhenTheLateInterruptedAuditFails(): void
    {
        $webhookApiFacade = $this->createStub(WebhookApiFacadeInterface::class);
        $webhookApiFacade->method('deleteEventsForSource')->willThrowException(new RuntimeException('original failure'));

        $auditApiFacade = $this->createStub(AuditApiFacadeInterface::class);
        $auditApiFacade->method('record')->willThrowException(new RuntimeException('audit failure'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Source force-finalize interrupted audit failed.',
            self::callback(static fn (array $context): bool => self::SOURCE_ID === ($context['source_id'] ?? null)),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('original failure');

        $this->service(
            $this->sourceApiFacade(),
            $this->packageApiFacade([$this->package('acme/one')]),
            $auditApiFacade,
            $this->cleanupScheduling(),
            $logger,
            $webhookApiFacade,
        )->forceFinalizeDeletion(self::SOURCE_ID, self::ADMIN_USER_ID, 'FORCE DELETE SOURCE', true);
    }

    #[Test]
    public function itRethrowsTheOriginalExceptionWhenTheInLoopInterruptedAuditFails(): void
    {
        $archiveCleanup = $this->createStub(ArchiveStorageCleanupApiFacadeInterface::class);
        $archiveCleanup->method('schedulePackageArchiveCleanup')->willReturnOnConsecutiveCalls(
            [
                'discovered' => 1,
                'scheduled'  => 1,
                'failed'     => 0,
            ],
            [
                'discovered' => 1,
                'scheduled'  => 0,
                'failed'     => 1,
            ],
        );

        $auditApiFacade = $this->createStub(AuditApiFacadeInterface::class);
        $auditApiFacade->method('record')->willThrowException(new RuntimeException('audit failure'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Source force-finalize interrupted audit failed.',
            self::callback(static fn (array $context): bool => self::SOURCE_ID === ($context['source_id'] ?? null)),
        );

        $this->expectException(SourceDeletionRecoveryException::class);
        $this->expectExceptionMessage('Archive cleanup could not be fully scheduled.');

        $this->service(
            $this->sourceApiFacade(),
            $this->packageApiFacade([$this->package('acme/one'), $this->package('acme/two')]),
            $auditApiFacade,
            $archiveCleanup,
            $logger,
        )->forceFinalizeDeletion(self::SOURCE_ID, self::ADMIN_USER_ID, 'FORCE DELETE SOURCE', false);
    }

    #[Test]
    public function itLoadsPackagesOfTheSourceOrganizationOnly(): void
    {
        $packageApiFacade = $this->createMock(PackageApiFacadeInterface::class);
        $packageApiFacade->expects(self::never())->method('allPackages');
        $packageApiFacade->expects(self::once())
            ->method('packagesForOrganization')
            ->with(self::ORGANIZATION_ID)
            ->willReturn([$this->package('acme/one'), $this->package('acme/other', 'src-2')])
        ;

        $preview = $this->service(
            $this->createStub(SourceApiFacadeInterface::class),
            $packageApiFacade,
            $this->createStub(AuditApiFacadeInterface::class),
            $this->createStub(ArchiveStorageCleanupApiFacadeInterface::class),
        )->deletionPreview($this->source());

        self::assertSame(1, $preview['packages_count']);
    }

    private function cleanupScheduling(): ArchiveStorageCleanupApiFacadeInterface
    {
        $archiveCleanup = $this->createStub(ArchiveStorageCleanupApiFacadeInterface::class);
        $archiveCleanup->method('schedulePackageArchiveCleanup')->willReturn([
            'discovered' => 1,
            'scheduled'  => 1,
            'failed'     => 0,
        ]);

        return $archiveCleanup;
    }

    private function sourceApiFacade(): MockObject&SourceApiFacadeInterface
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('findSource')->with(self::SOURCE_ID)->willReturn($this->source());

        return $sourceApiFacade;
    }

    /**
     * @param list<PackageDto> $packages
     */
    private function packageApiFacade(array $packages): PackageApiFacadeInterface
    {
        $packageApiFacade = $this->createStub(PackageApiFacadeInterface::class);
        $packageApiFacade->method('packagesForOrganization')->willReturn($packages);
        $packageApiFacade->method('deletePackage')->willReturn(true);

        return $packageApiFacade;
    }

    private function package(string $name, string $sourceId = self::SOURCE_ID): PackageDto
    {
        return new PackageDto(
            name: $name,
            type: 'library',
            description: '',
            visibility: 'private',
            sourceType: 'github',
            sourceUrl: 'https://github.com/acme/repo',
            createdAt: '2026-06-01T00:00:00+00:00',
            updatedAt: '2026-06-02T00:00:00+00:00',
            organizationId: self::ORGANIZATION_ID,
            sourceId: $sourceId,
        );
    }

    private function source(): SourceDto
    {
        return new SourceDto(
            id: self::SOURCE_ID,
            provider: 'github',
            type: 'github',
            displayName: 'acme/repo',
            canonicalUrl: 'https://github.com/acme/repo',
            url: 'https://github.com/acme/repo',
            owner: 'acme',
            repository: 'repo',
            defaultRef: 'main',
            organizationId: self::ORGANIZATION_ID,
            status: 'deleting',
        );
    }

    private function service(
        SourceApiFacadeInterface $sourceApiFacade,
        PackageApiFacadeInterface $packageApiFacade,
        AuditApiFacadeInterface $auditApiFacade,
        ArchiveStorageCleanupApiFacadeInterface $archiveStorageCleanupApiFacade,
        ?LoggerInterface $logger = null,
        ?WebhookApiFacadeInterface $webhookApiFacade = null,
    ): SourceDeletionRecoveryService {
        return new SourceDeletionRecoveryService(
            $sourceApiFacade,
            $this->createStub(JobApiFacadeInterface::class),
            $packageApiFacade,
            $this->createStub(ReleaseApiFacadeInterface::class),
            $archiveStorageCleanupApiFacade,
            $webhookApiFacade ?? $this->createStub(WebhookApiFacadeInterface::class),
            $auditApiFacade,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
