<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Common\Logging\SafeExceptionContext;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Dto\ArchiveSchedulingResult;
use Packio\Source\Application\Dto\SourceSyncExecutionResult;
use Packio\Source\Application\Dto\SourceSyncResult;
use Packio\Source\Application\Port\JobSchedulerPort;
use Packio\Source\Application\Port\PublicationSyncPort;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function count;
use function in_array;
use function is_string;

final readonly class SourceSyncExecutionService implements SourceSyncExecutionInterface
{
    public function __construct(
        private PublicationSyncPort $publicationSyncPort,
        private SourceCommandService $sourceCommandService,
        private JobSchedulerPort $jobSchedulerPort,
        private SourceRepositoryInterface $sourceRepository,
    ) {}

    public function execute(string $sourceId, string $organizationId, LoggerInterface $jobLogger): SourceSyncExecutionResult
    {
        try {
            if (! $this->sourceRepository->findByIdInOrganization($sourceId, $organizationId) instanceof Source) {
                return new SourceSyncExecutionResult(SourceSyncOutcome::NotFound, error: 'Source not found.');
            }

            $result = $this->publicationSyncPort->synchronizeSource($sourceId);

            $this->logReadmeSync($result, $jobLogger);

            $archiveScheduling = $this->scheduleArchiveStorageJobs($result, $sourceId, $organizationId, $jobLogger);
            $this->sourceCommandService->markSyncSucceeded($sourceId);

            $jobLogger->info('Source sync completed', [
                'source_id'                   => $sourceId,
                'package_name'                => $result->packageName,
                'release_count'               => count($result->releases),
                'archive_job_count'           => $archiveScheduling->scheduled,
                'archive_job_failed_count'    => $archiveScheduling->failed,
                'outcome'                     => $archiveScheduling->isDegraded() ? 'degraded_success' : 'synchronized',
                'created'                     => $result->created,
                'retention_kept_count'        => (int) ($result->retention['kept_count'] ?? 0),
                'retention_deactivated_count' => (int) ($result->retention['deactivated_count'] ?? 0),
            ]);

            return new SourceSyncExecutionResult($archiveScheduling->isDegraded() ? SourceSyncOutcome::Degraded : SourceSyncOutcome::Synchronized, [
                'package_name'             => $result->packageName,
                'release_count'            => count($result->releases),
                'archive_job_count'        => $archiveScheduling->scheduled,
                'archive_job_failed_count' => $archiveScheduling->failed,
                'outcome'                  => $archiveScheduling->isDegraded() ? 'degraded_success' : 'synchronized',
                'created'                  => $result->created,
                'retention'                => $result->retention,
            ]);
        } catch (SourceTokenRefreshUnavailableException $e) {
            $jobLogger->warning('Source sync failed due to auth requiring reauthorization', [
                'source_id'         => $sourceId,
                'error'             => $e->getMessage(),
                'auth_failure_type' => 'reauthorization_required',
            ]);

            $this->sourceCommandService->markSourceAuthorizationInvalidForSource($sourceId);
            $this->sourceCommandService->markSyncFailed($sourceId, 'Provider authorization requires reconnection: ' . SafeExceptionContext::redact($e->getMessage()));

            return new SourceSyncExecutionResult(SourceSyncOutcome::AuthorizationRequired, error: SafeExceptionContext::redact($e->getMessage()));
        } catch (SourceTokenMissingException $e) {
            $jobLogger->warning('Source sync failed due to missing auth token', [
                'source_id'         => $sourceId,
                'error'             => $e->getMessage(),
                'auth_failure_type' => 'token_missing',
            ]);

            $this->sourceCommandService->markSourceAuthorizationInvalidForSource($sourceId);
            $this->sourceCommandService->markSyncFailed($sourceId, 'Provider token is missing: ' . SafeExceptionContext::redact($e->getMessage()));

            return new SourceSyncExecutionResult(SourceSyncOutcome::AuthorizationRequired, error: SafeExceptionContext::redact($e->getMessage()));
        } catch (SourceTokenRefreshFailedException $e) {
            if (! $e->requiresReauthorization) {
                $jobLogger->error('Source sync failed due to transient token refresh failure', [
                    'source_id'         => $sourceId,
                    'error'             => $e->getMessage(),
                    'auth_failure_type' => 'refresh_transient',
                ]);

                $this->sourceCommandService->markSyncFailed($sourceId, SafeExceptionContext::redact($e->getMessage()));

                return new SourceSyncExecutionResult(SourceSyncOutcome::RetryableFailure, error: SafeExceptionContext::redact($e->getMessage()));
            }

            $jobLogger->error('Source sync failed due to token refresh failure', [
                'source_id'         => $sourceId,
                'error'             => $e->getMessage(),
                'auth_failure_type' => 'refresh_failed',
            ]);

            $this->sourceCommandService->markSourceAuthorizationInvalidForSource($sourceId);
            $this->sourceCommandService->markSyncFailed($sourceId, 'Provider token refresh failed: ' . SafeExceptionContext::redact($e->getMessage()));

            return new SourceSyncExecutionResult(SourceSyncOutcome::AuthorizationRequired, error: SafeExceptionContext::redact($e->getMessage()));
        } catch (RuntimeException $e) {
            $jobLogger->error('Source sync failed', [
                'source_id' => $sourceId,
                'error'     => $e->getMessage(),
            ]);

            $this->sourceCommandService->markSyncFailed($sourceId, SafeExceptionContext::redact($e->getMessage()));

            return new SourceSyncExecutionResult(SourceSyncOutcome::RetryableFailure, error: SafeExceptionContext::redact($e->getMessage()));
        }
    }

    private function scheduleArchiveStorageJobs(
        SourceSyncResult $sourceSyncResult,
        string $sourceId,
        string $organizationId,
        LoggerInterface $jobLogger,
    ): ArchiveSchedulingResult {
        $scheduled = 0;
        $failed    = 0;
        foreach ($sourceSyncResult->releases as $release) {
            $version = $release['version'] ?? null;
            if (! is_string($version)) {
                continue;
            }

            if ('' === $version) {
                continue;
            }

            $reference = $release['reference'] ?? '';

            try {
                $this->jobSchedulerPort->enqueue(
                    organizationId: $organizationId,
                    type: 'store.release.archive',
                    payload: [
                        'organization_id' => $organizationId,
                        'package_name'    => $sourceSyncResult->packageName,
                        'version'         => $version,
                        'source_id'       => $sourceId,
                        'reference'       => $reference,
                    ],
                );
                ++$scheduled;
            } catch (RuntimeException $exception) {
                ++$failed;
                $jobLogger->warning('Archive storage job enqueue failed', [
                    'source_id'    => $sourceId,
                    'package_name' => $sourceSyncResult->packageName,
                    'version'      => $version,
                    'error'        => $exception->getMessage(),
                ]);
            }
        }

        return new ArchiveSchedulingResult($scheduled, $failed);
    }

    private function logReadmeSync(SourceSyncResult $sourceSyncResult, LoggerInterface $jobLogger): void
    {
        if ([] === $sourceSyncResult->readmeSync) {
            return;
        }

        $status  = (string) ($sourceSyncResult->readmeSync['status'] ?? '');
        $message = $this->publicReadmeSyncMessage($status);
        $context = [
            'package_name'   => $sourceSyncResult->packageName,
            'readme_status'  => $status,
            'readme_message' => $message,
        ];

        if ('failed' === $status) {
            $jobLogger->warning('Source README sync failed', $context);

            return;
        }

        if ('skipped' === $status) {
            $jobLogger->info('Source README sync skipped', $context);

            return;
        }

        if (in_array($status, ['updated', 'cleared'], true)) {
            $jobLogger->info('Source README sync completed', $context);

            return;
        }

        $jobLogger->info('Source README sync status recorded', $context);
    }

    private function publicReadmeSyncMessage(string $status): string
    {
        return match ($status) {
            'updated' => 'README synchronized successfully.',
            'cleared' => 'README was not found upstream; stored README was cleared.',
            'skipped' => 'README synchronization was skipped.',
            'failed'  => 'README synchronization failed. Existing README was left unchanged.',
            default   => 'README synchronization status recorded.',
        };
    }
}
