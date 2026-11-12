<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter\Worker;

use Packio\Source\Application\Service\SourceOAuthRefreshMaintenanceService;
use Packio\Source\Application\Service\SourceOAuthRefreshReason;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;

use function is_string;

final readonly class SourceOAuthRefreshJobHandler implements JobHandlerInterface
{
    public const string TYPE = 'source.oauth.refresh';

    public function __construct(private SourceOAuthRefreshMaintenanceService $sourceOAuthRefreshMaintenanceService) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        $sourceOAuthAccountId = $payload['source_oauth_account_id'] ?? null;
        if (! is_string($sourceOAuthAccountId) || '' === $sourceOAuthAccountId) {
            return JobResult::failure('Source OAuth account ID is required.', shouldRetry: false);
        }

        $providerAuthorizationId = $payload['provider_authorization_id'] ?? null;
        if (null !== $providerAuthorizationId && ! is_string($providerAuthorizationId)) {
            return JobResult::failure('Provider authorization ID must be a string.', shouldRetry: false);
        }

        $sourceOAuthRefreshReason = SourceOAuthRefreshReason::fromPayload(
            is_string($payload['reason'] ?? null) ? $payload['reason'] : null,
        );

        $jobLogger->info('Starting Source OAuth refresh', [
            'source_oauth_account_id'   => $sourceOAuthAccountId,
            'provider_authorization_id' => $providerAuthorizationId,
            'reason'                    => $sourceOAuthRefreshReason->value,
        ]);

        try {
            $result = $this->sourceOAuthRefreshMaintenanceService->refreshAccount(
                $sourceOAuthAccountId,
                $providerAuthorizationId,
                $sourceOAuthRefreshReason,
            );
        } catch (SourceTokenMissingException|SourceTokenRefreshFailedException|SourceTokenRefreshUnavailableException $exception) {
            if ($exception instanceof SourceTokenRefreshFailedException && ! $exception->requiresReauthorization) {
                $jobLogger->warning('Source OAuth refresh failed transiently, will retry', [
                    'source_oauth_account_id'   => $sourceOAuthAccountId,
                    'provider_authorization_id' => $providerAuthorizationId,
                    'reason'                    => $sourceOAuthRefreshReason->value,
                    'error'                     => $exception->getMessage(),
                ]);

                return JobResult::failure($exception->getMessage());
            }

            $jobLogger->warning('Source OAuth refresh requires reconnection', [
                'source_oauth_account_id'   => $sourceOAuthAccountId,
                'provider_authorization_id' => $providerAuthorizationId,
                'reason'                    => $sourceOAuthRefreshReason->value,
                'error'                     => $exception->getMessage(),
            ]);

            return JobResult::failure($exception->getMessage(), shouldRetry: false);
        }

        $jobLogger->info('Source OAuth refresh completed', [
            'source_oauth_account_id'   => $result->sourceOAuthAccountId,
            'provider_authorization_id' => $result->providerAuthorizationId,
            'provider'                  => $result->provider,
            'reason'                    => $result->reason->value,
            'refreshed'                 => $result->refreshed,
        ]);

        return JobResult::success([
            'source_oauth_account_id'   => $result->sourceOAuthAccountId,
            'provider_authorization_id' => $result->providerAuthorizationId,
            'provider'                  => $result->provider,
            'reason'                    => $result->reason->value,
            'refreshed'                 => $result->refreshed,
        ]);
    }
}
