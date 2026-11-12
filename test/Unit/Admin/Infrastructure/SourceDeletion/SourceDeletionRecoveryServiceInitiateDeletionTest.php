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
use Packio\Source\Api\Dto\SourceDeletionInitiationOutcome;
use Packio\Source\Api\Dto\SourceDeletionInitiationResult;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(SourceDeletionRecoveryService::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceDeletionRecoveryServiceInitiateDeletionTest extends TestCase
{
    private const string ADMIN_USER_ID   = 'admin-user-1';
    private const string SOURCE_ID       = 'source-1';
    private const string ORGANIZATION_ID = 'org-1';

    #[Test]
    public function initiateDeletionSchedulesTheStagedJobAndRecordsOneAuditEntry(): void
    {
        $source = $this->source(status: 'active');

        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('findSource')->with(self::SOURCE_ID)->willReturn($source);
        $sourceApiFacade->expects(self::once())
            ->method('initiateSourceDelete')
            ->with(self::ORGANIZATION_ID, self::SOURCE_ID, 'admin-source-delete-initiation')
            ->willReturn(new SourceDeletionInitiationResult(
                SourceDeletionInitiationOutcome::Initiated,
                'job-1',
                true,
                'run-1',
            ))
        ;

        $packageApiFacade = $this->createMock(PackageApiFacadeInterface::class);
        $packageApiFacade->method('packagesForOrganization')->willReturn([]);

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::once())
            ->method('recordOnce')
            ->with(
                'source-delete-initiation:' . self::SOURCE_ID . ':run-1',
                'source.delete.initiated_by_admin',
                self::callback(static fn (array $context): bool => self::ADMIN_USER_ID === ($context['admin_user_id'] ?? null)
                    && self::SOURCE_ID === ($context['source_id'] ?? null)
                    && self::ORGANIZATION_ID === ($context['organization_id'] ?? null)
                    && 'source-delete:' . self::SOURCE_ID . ':run-1' === ($context['job_idempotency_key'] ?? null)
                    && 'admin-source-delete-initiation' === ($context['action_origin'] ?? null)),
                self::ORGANIZATION_ID,
                self::SOURCE_ID,
                '',
                self::ADMIN_USER_ID,
                '',
                '',
            )
            ->willReturn(true)
        ;
        $sourceApiFacade->expects(self::once())
            ->method('publishInitiationAfterAudit')
            ->with(self::ORGANIZATION_ID, 'job-1')
            ->willReturn(true)
        ;

        $message = $this->service(
            $sourceApiFacade,
            $packageApiFacade,
            $auditApiFacade,
        )->initiateDeletion(self::SOURCE_ID, self::ADMIN_USER_ID);

        self::assertSame('Source deletion was initiated.', $message);
    }

    #[Test]
    public function initiateDeletionResumesAnAlreadyDeletingSourceLifecycle(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('findSource')->with(self::SOURCE_ID)
            ->willReturn($this->source(status: 'deleting'))
        ;
        $sourceApiFacade->expects(self::once())
            ->method('initiateSourceDelete')
            ->willReturn(new SourceDeletionInitiationResult(
                SourceDeletionInitiationOutcome::AlreadyDeleting,
                'job-1',
                false,
                'run-1',
            ))
        ;
        $sourceApiFacade->expects(self::once())
            ->method('publishInitiationAfterAudit')
            ->with(self::ORGANIZATION_ID, 'job-1')
            ->willReturn(true)
        ;

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::once())->method('recordOnce')->willReturn(false);

        $message = $this->service(
            $sourceApiFacade,
            $this->createMock(PackageApiFacadeInterface::class),
            $auditApiFacade,
        )->initiateDeletion(self::SOURCE_ID, self::ADMIN_USER_ID);

        self::assertSame('Source deletion lifecycle was resumed.', $message);
    }

    #[Test]
    public function initiateDeletionRejectsAMissingSourceWithoutSideEffects(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('findSource')->with(self::SOURCE_ID)->willReturn(null);
        $sourceApiFacade->expects(self::never())->method('initiateSourceDelete');

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::never())->method('record');

        $this->expectException(SourceDeletionRecoveryException::class);
        $this->expectExceptionMessage('Source not found.');

        $this->service(
            $sourceApiFacade,
            $this->createMock(PackageApiFacadeInterface::class),
            $auditApiFacade,
        )->initiateDeletion(self::SOURCE_ID, self::ADMIN_USER_ID);
    }

    #[Test]
    public function initiateDeletionRejectsWhenTheAtomicOutcomeReportsAlreadyDeleting(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('findSource')->with(self::SOURCE_ID)
            ->willReturn($this->source(status: 'active'))
        ;
        $sourceApiFacade->method('initiateSourceDelete')
            ->willReturn(new SourceDeletionInitiationResult(SourceDeletionInitiationOutcome::AlreadyDeleting))
        ;

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::never())->method('record');

        $this->expectException(SourceDeletionRecoveryException::class);
        $this->expectExceptionMessage('Source deletion lifecycle could not be resumed.');

        $this->service(
            $sourceApiFacade,
            $this->createMock(PackageApiFacadeInterface::class),
            $auditApiFacade,
        )->initiateDeletion(self::SOURCE_ID, self::ADMIN_USER_ID);
    }

    #[Test]
    public function initiateDeletionRejectsWhenTheAtomicOutcomeReportsNotFound(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('findSource')->with(self::SOURCE_ID)
            ->willReturn($this->source(status: 'active'))
        ;
        $sourceApiFacade->method('initiateSourceDelete')
            ->willReturn(new SourceDeletionInitiationResult(SourceDeletionInitiationOutcome::NotFound))
        ;

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::never())->method('record');

        $this->expectException(SourceDeletionRecoveryException::class);
        $this->expectExceptionMessage('Source not found.');

        $this->service(
            $sourceApiFacade,
            $this->createMock(PackageApiFacadeInterface::class),
            $auditApiFacade,
        )->initiateDeletion(self::SOURCE_ID, self::ADMIN_USER_ID);
    }

    #[Test]
    public function initiateDeletionCompensatesTheInitiationWhenTheAuditWriteFails(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('findSource')->with(self::SOURCE_ID)
            ->willReturn($this->source(status: 'active'))
        ;
        $sourceApiFacade->method('initiateSourceDelete')
            ->willReturn(new SourceDeletionInitiationResult(
                SourceDeletionInitiationOutcome::Initiated,
                'job-1',
                false,
                'run-1',
            ))
        ;
        $sourceApiFacade->expects(self::once())
            ->method('revertInitiation')
            ->with(self::ORGANIZATION_ID, self::SOURCE_ID, false, 'job-1', 'run-1')
            ->willReturn(true)
        ;

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::once())
            ->method('recordOnce')
            ->willThrowException(new RuntimeException('Audit storage unavailable.'))
        ;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit storage unavailable.');

        $this->service(
            $sourceApiFacade,
            $this->createMock(PackageApiFacadeInterface::class),
            $auditApiFacade,
        )->initiateDeletion(self::SOURCE_ID, self::ADMIN_USER_ID);
    }

    #[Test]
    public function deletionPreviewReturnsOnlySafeCountsAndIdentifiers(): void
    {
        $sourceDto = new SourceDto(
            id: self::SOURCE_ID,
            provider: 'github',
            type: 'github',
            displayName: '',
            canonicalUrl: 'https://github.com/acme/repo',
            url: 'https://github.com/acme/repo',
            owner: 'acme',
            repository: 'repo',
            defaultRef: 'main',
            organizationId: self::ORGANIZATION_ID,
            providerConfig: [
                'token'             => 'top-secret-token',
                'client_secret'     => 'top-secret-client-secret',
                'configuration'     => [
                    'secret' => 'value',
                ],
                'oauth_identifier'  => 'oauth-id',
            ],
        );

        $packageDto = new PackageDto(
            name: 'acme/repo',
            type: 'library',
            description: 'Package linked to the source',
            visibility: 'private',
            sourceType: 'github',
            sourceUrl: $sourceDto->url,
            createdAt: '2026-06-01T00:00:00+00:00',
            updatedAt: '2026-06-02T00:00:00+00:00',
            organizationId: self::ORGANIZATION_ID,
            sourceId: self::SOURCE_ID,
        );

        $packageApiFacade = $this->createMock(PackageApiFacadeInterface::class);
        $packageApiFacade->method('packagesForOrganization')->willReturn([$packageDto]);

        $releaseApiFacade = $this->createMock(ReleaseApiFacadeInterface::class);
        $releaseApiFacade->method('releasesForPackage')
            ->with('acme/repo', true, self::ORGANIZATION_ID)
            ->willReturn(['release-1', 'release-2'])
        ;

        $preview = $this->service(
            $this->createMock(SourceApiFacadeInterface::class),
            $packageApiFacade,
            $this->createMock(AuditApiFacadeInterface::class),
            $releaseApiFacade,
        )->deletionPreview($sourceDto);

        self::assertSame([
            'source'         => [
                'id'              => self::SOURCE_ID,
                'name'            => 'acme/repo',
                'provider'        => 'github',
                'organization_id' => self::ORGANIZATION_ID,
            ],
            'packages_count' => 1,
            'releases_count' => 2,
        ], $preview);
        self::assertArrayNotHasKey('provider_config', $preview['source']);
    }

    private function source(string $status): SourceDto
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
            status: $status,
        );
    }

    private function service(
        SourceApiFacadeInterface $sourceApiFacade,
        PackageApiFacadeInterface $packageApiFacade,
        AuditApiFacadeInterface $auditApiFacade,
        ?ReleaseApiFacadeInterface $releaseApiFacade = null,
    ): SourceDeletionRecoveryService {
        return new SourceDeletionRecoveryService(
            $sourceApiFacade,
            $this->createMock(JobApiFacadeInterface::class),
            $packageApiFacade,
            $releaseApiFacade ?? $this->createMock(ReleaseApiFacadeInterface::class),
            $this->createMock(ArchiveStorageCleanupApiFacadeInterface::class),
            $this->createMock(WebhookApiFacadeInterface::class),
            $auditApiFacade,
            $this->createMock(LoggerInterface::class),
        );
    }
}
