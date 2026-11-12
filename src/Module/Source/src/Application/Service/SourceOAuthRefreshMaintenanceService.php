<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Cake\Chronos\Chronos;
use Packio\Source\Application\Dto\SourceOAuthRefreshCandidate;
use Packio\Source\Application\Dto\SourceOAuthRefreshExecutionResult;
use Packio\Source\Application\Dto\SourceOAuthRefreshInlineResult;
use Packio\Source\Application\Dto\SourceOAuthRefreshScheduleResult;
use Packio\Source\Application\Port\JobSchedulerPort;
use Packio\Source\Domain\Dto\SourceOAuthRefreshCriteria;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;

use function sprintf;
use function trim;

final readonly class SourceOAuthRefreshMaintenanceService
{
    public function __construct(
        private SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository,
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private SourceOAuthTokenResolver $sourceOAuthTokenResolver,
        private SourceOAuthRefreshPolicy $sourceOAuthRefreshPolicy,
        private JobSchedulerPort $jobSchedulerPort,
        private SourceAuthorizationInvalidationService $sourceAuthorizationInvalidationService,
    ) {}

    public function schedule(SourceOAuthRefreshCriteria $sourceOAuthRefreshCriteria, bool $dryRun = false): SourceOAuthRefreshScheduleResult
    {
        $candidates = [];
        $scheduled  = [];
        $orphaned   = [];

        foreach ($this->sourceOAuthAccountRepository->findRefreshCandidates($sourceOAuthRefreshCriteria) as $sourceOAuthAccount) {
            $candidate    = $this->candidate($sourceOAuthAccount, $sourceOAuthRefreshCriteria);
            $candidates[] = $candidate;

            $authorization = $this->firstActiveAuthorization(
                $sourceOAuthAccount->getIdentifier()->toString(),
                $sourceOAuthRefreshCriteria->organizationId,
            );
            if (! $authorization instanceof ProviderAuthorization) {
                $orphaned[] = $candidate;

                continue;
            }

            if (! $dryRun) {
                $this->enqueueRefreshJob($sourceOAuthAccount, $authorization, $candidate->reason);
            }

            $scheduled[] = $candidate;
        }

        return new SourceOAuthRefreshScheduleResult(
            candidates: $candidates,
            scheduled: $scheduled,
            orphaned: $orphaned,
            dryRun: $dryRun,
        );
    }

    public function runInline(SourceOAuthRefreshCriteria $sourceOAuthRefreshCriteria): SourceOAuthRefreshInlineResult
    {
        $candidates = [];
        $refreshed  = [];
        $orphaned   = [];
        $failed     = [];

        foreach ($this->sourceOAuthAccountRepository->findRefreshCandidates($sourceOAuthRefreshCriteria) as $sourceOAuthAccount) {
            $candidate    = $this->candidate($sourceOAuthAccount, $sourceOAuthRefreshCriteria);
            $candidates[] = $candidate;

            $authorization = $this->firstActiveAuthorization(
                $sourceOAuthAccount->getIdentifier()->toString(),
                $sourceOAuthRefreshCriteria->organizationId,
            );
            if (! $authorization instanceof ProviderAuthorization) {
                $orphaned[] = $candidate;

                continue;
            }

            try {
                $refreshed[] = $this->refreshAccount(
                    $sourceOAuthAccount->getIdentifier()->toString(),
                    $authorization->getIdentifier()->toString(),
                    $candidate->reason,
                );
            } catch (SourceTokenMissingException|SourceTokenRefreshFailedException|SourceTokenRefreshUnavailableException $exception) {
                $failed[] = [
                    'candidate' => $candidate,
                    'error'     => $exception->getMessage(),
                ];
            }
        }

        return new SourceOAuthRefreshInlineResult(
            candidates: $candidates,
            refreshed: $refreshed,
            orphaned: $orphaned,
            failed: $failed,
        );
    }

    /**
     * @throws SourceTokenMissingException
     * @throws SourceTokenRefreshUnavailableException
     * @throws SourceTokenRefreshFailedException
     */
    public function refreshAccount(
        string $sourceOAuthAccountId,
        ?string $providerAuthorizationId,
        SourceOAuthRefreshReason $sourceOAuthRefreshReason,
    ): SourceOAuthRefreshExecutionResult {
        $account = $this->sourceOAuthAccountRepository->findById($sourceOAuthAccountId);
        if (! $account instanceof SourceOAuthAccount) {
            throw new SourceTokenMissingException('Source OAuth account not found.');
        }

        if (! $account->isActive()) {
            throw new SourceTokenRefreshUnavailableException('Source OAuth account is inactive. Reauthorization required.');
        }

        $authorization = $this->authorizationForAccount($account, $providerAuthorizationId);
        if (! $authorization instanceof ProviderAuthorization) {
            throw new SourceTokenRefreshUnavailableException('No active provider authorization is linked to this Source OAuth account.');
        }

        try {
            $result = $this->sourceOAuthTokenResolver->refreshForProviderAuthorization($authorization);
        } catch (SourceTokenMissingException|SourceTokenRefreshFailedException|SourceTokenRefreshUnavailableException $exception) {
            if (! $exception instanceof SourceTokenRefreshFailedException || $exception->requiresReauthorization) {
                $this->sourceAuthorizationInvalidationService->markInvalid($authorization);
            }

            throw $exception;
        }

        return new SourceOAuthRefreshExecutionResult(
            sourceOAuthAccountId: $account->getIdentifier()->toString(),
            providerAuthorizationId: $authorization->getIdentifier()->toString(),
            provider: $account->provider(),
            reason: $sourceOAuthRefreshReason,
            refreshed: $result['refreshed'],
        );
    }

    private function enqueueRefreshJob(
        SourceOAuthAccount $sourceOAuthAccount,
        ProviderAuthorization $providerAuthorization,
        SourceOAuthRefreshReason $sourceOAuthRefreshReason,
    ): void {
        $accountId = $sourceOAuthAccount->getIdentifier()->toString();

        $this->jobSchedulerPort->enqueueOnce(
            organizationId: $providerAuthorization->organizationId(),
            type: 'source.oauth.refresh',
            idempotencyKey: sprintf(
                'source-oauth-refresh:%s:%s',
                $accountId,
                new Chronos()->toDateString(),
            ),
            payload: [
                'source_oauth_account_id'   => $accountId,
                'provider_authorization_id' => $providerAuthorization->getIdentifier()->toString(),
                'reason'                    => $sourceOAuthRefreshReason->value,
            ],
        );
    }

    private function candidate(
        SourceOAuthAccount $sourceOAuthAccount,
        SourceOAuthRefreshCriteria $sourceOAuthRefreshCriteria
    ): SourceOAuthRefreshCandidate {
        return new SourceOAuthRefreshCandidate(
            sourceOAuthAccountId: $sourceOAuthAccount->getIdentifier()->toString(),
            provider: $sourceOAuthAccount->provider(),
            reason: $this->sourceOAuthRefreshPolicy->reasonFor($sourceOAuthAccount, $sourceOAuthRefreshCriteria),
            providerInstanceId: $sourceOAuthAccount->providerInstanceId(),
            tokenExpiresAt: $sourceOAuthAccount->tokenExpiresAt(),
            lastUsedAt: $sourceOAuthAccount->lastUsedAt(),
        );
    }

    private function authorizationForAccount(
        SourceOAuthAccount $sourceOAuthAccount,
        ?string $providerAuthorizationId,
    ): ?ProviderAuthorization {
        $accountId       = $sourceOAuthAccount->getIdentifier()->toString();
        $authorizationId = trim((string) $providerAuthorizationId);
        if ('' !== $authorizationId) {
            $authorization = $this->providerAuthorizationRepository->findById($authorizationId);

            return $this->authorizationMatchesAccount($authorization, $accountId)
                ? $authorization
                : null;
        }

        return $this->firstActiveAuthorization($accountId, null);
    }

    private function firstActiveAuthorization(string $sourceOAuthAccountId, ?string $organizationId): ?ProviderAuthorization
    {
        return $this->providerAuthorizationRepository->findActiveBySourceOAuthAccountId($sourceOAuthAccountId, $organizationId)[0] ?? null;
    }

    private function authorizationMatchesAccount(?ProviderAuthorization $providerAuthorization, string $sourceOAuthAccountId): bool
    {
        return $providerAuthorization instanceof ProviderAuthorization
            && 'active' === $providerAuthorization->status()
            && $providerAuthorization->sourceOAuthAccountId() === $sourceOAuthAccountId;
    }
}
