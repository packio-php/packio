<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Application\Command\RegisterSourceCommand;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Dto\MembershipData;
use Packio\Source\Application\Dto\SourceCreationExecutionResult;
use Packio\Source\Application\Port\JobSchedulerPort;
use Packio\Source\Application\Port\OrganizationMembershipPort;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final readonly class SourceCreationExecutionService implements SourceCreationExecutionInterface
{
    public function __construct(
        private SourceCommandService $sourceCommandService,
        private OrganizationMembershipPort $organizationMembershipPort,
        private JobSchedulerPort $jobSchedulerPort,
    ) {}

    public function execute(
        RegisterSourceCommand $registerSourceCommand,
        string $requestedByUserId,
        LoggerInterface $jobLogger
    ): SourceCreationExecutionResult {
        $organizationId = $registerSourceCommand->organizationId;
        $provider       = $registerSourceCommand->provider;
        $resource       = $registerSourceCommand->resourceId;
        $webhook        = SourceWebhookRegistrationOutcome::Skipped;

        try {
            $membership = $this->organizationMembershipPort->findMembership(
                $organizationId,
                $requestedByUserId,
            );

            if (
                ! $membership instanceof MembershipData
                || 'owner' !== $membership->role
            ) {
                return new SourceCreationExecutionResult(SourceCreationOutcome::Forbidden, error: 'Valid owner actor context is required.');
            }

            $actorContext = new ActorContext(
                organizationId: $organizationId,
                actorId: $membership->userId,
                actorRole: $membership->role,
            );

            $source = $this->sourceCommandService->registerSourceInContext(
                $actorContext,
                $registerSourceCommand,
            );

            $created  = '' === $source->lastSyncStatus() || 'pending' === $source->lastSyncStatus();
            $sourceId = $source->getIdentifier()->toString();

            $jobLogger->info('Source registered', [
                'source_id'          => $sourceId,
                'canonical_url'      => $source->canonicalUrl(),
                'created'            => $created,
                'keep_last_releases' => $source->keepLastReleases(),
            ]);

            if ($this->sourceCommandService->supportsWebhookAutoRegistration($provider)) {
                try {
                    $webhookResult  = $this->sourceCommandService->tryRegisterWebhook($sourceId);
                    $webhook        = $webhookResult->success
                        ? ($webhookResult->alreadyExists ? SourceWebhookRegistrationOutcome::AlreadyRegistered : SourceWebhookRegistrationOutcome::Registered)
                        : SourceWebhookRegistrationOutcome::Warning;
                    $webhookContext = [
                        'source_id'      => $sourceId,
                        'success'        => $webhookResult->success,
                        'already_exists' => $webhookResult->alreadyExists,
                        'message'        => $webhookResult->message,
                    ];

                    if ($webhookResult->success) {
                        $jobLogger->info(
                            $webhookResult->alreadyExists
                                ? 'Webhook already registered'
                                : 'Webhook registered successfully',
                            $webhookContext,
                        );
                    } else {
                        $jobLogger->warning(
                            'Webhook registration failed (non-fatal): ' . $webhookResult->message,
                            $webhookContext,
                        );
                    }
                } catch (Throwable $e) {
                    $webhook = SourceWebhookRegistrationOutcome::Warning;
                    $jobLogger->warning('Webhook registration failed (non-fatal)', [
                        'source_id' => $sourceId,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            $this->jobSchedulerPort->enqueue(
                organizationId: $organizationId,
                type: 'source.sync',
                payload: [
                    'source_id'       => $sourceId,
                    'organization_id' => $organizationId,
                ],
                priority: 5,
            );

            $jobLogger->info('Source sync scheduled', [
                'source_id' => $sourceId,
            ]);

            return new SourceCreationExecutionResult(SourceCreationOutcome::Created, [
                'source_id'     => $sourceId,
                'canonical_url' => $source->canonicalUrl(),
                'created'       => $created,
                'provider'      => $provider,
            ], webhook: $webhook);
        } catch (RuntimeException $e) {
            $jobLogger->error('Source creation failed', [
                'provider' => $provider,
                'resource' => $resource,
                'error'    => $e->getMessage(),
            ]);

            return new SourceCreationExecutionResult(SourceCreationOutcome::RetryableFailure, [
                'provider' => $provider,
                'resource' => $resource,
            ], $e->getMessage(), $webhook);
        }
    }
}
