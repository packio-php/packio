<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Command;

use Packio\Publication\Application\Dto\ConnectSourceOutcome;
use Packio\Publication\Application\Dto\ConnectSourcesResult;
use Packio\Publication\Application\Port\SourceConnectPort;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_map;
use function bin2hex;
use function hash;
use function random_bytes;
use function serialize;
use function trim;

class ConnectSourcesUseCase
{
    public function __construct(private readonly SourceConnectPort $sourceConnectPort, private readonly LoggerInterface $logger) {}

    public function execute(ConnectSourcesCommand $connectSourcesCommand): ConnectSourcesResult
    {
        $correlationId = bin2hex(random_bytes(16));
        if (! $connectSourcesCommand->actorContext->isOwner() || '' === trim($connectSourcesCommand->actorContext->organizationId) || '' === trim($connectSourcesCommand->actorContext->actorId)) {
            return $this->batchFailure($connectSourcesCommand, 'forbidden', 'You cannot connect sources in this organization.', $correlationId);
        }

        try {
            $authorizationId = $this->sourceConnectPort->authorize($connectSourcesCommand);
        } catch (Throwable $exception) {
            $this->logFailure($exception, $connectSourcesCommand, $correlationId);

            return $this->batchFailure($connectSourcesCommand, 'internal_error', 'Unable to authorize sources for import.', $correlationId);
        }
        // A token submission has a stable credential identity even if concurrent
        // requests create separate authorization records before either is visible.
        $authorizationIdentity = [] === $connectSourcesCommand->authorizationPayload ? $authorizationId : hash('sha256', serialize($connectSourcesCommand->authorizationPayload));
        $queued                = [];
        $skipped               = [];
        $failed                = [];
        foreach ($connectSourcesCommand->repositories as $repository) {
            try {
                $created = $this->sourceConnectPort->schedule([
                    'organization_id'           => $connectSourcesCommand->actorContext->organizationId,
                    'requested_by_user_id'      => $connectSourcesCommand->actorContext->actorId,
                    'provider'                  => $connectSourcesCommand->provider,
                    'resource'                  => $repository,
                    'default_ref'               => $connectSourcesCommand->defaultRefs[$repository] ?? '',
                    'visibility'                => $connectSourcesCommand->visibility,
                    'provider_authorization_id' => $authorizationId,
                    'provider_instance_id'      => $connectSourcesCommand->providerInstanceId,
                    'keep_last_releases'        => $connectSourcesCommand->keepLastReleases,
                    'idempotency_key'           => 'source-connect:' . hash('sha256', serialize([$connectSourcesCommand->actorContext->organizationId, $connectSourcesCommand->provider, $connectSourcesCommand->providerInstanceId, $authorizationIdentity, $repository])),
                    'settings'                  => $connectSourcesCommand->settings,
                ], $connectSourcesCommand->actorContext->organizationId);
                if ($created) {
                    $queued[] = new ConnectSourceOutcome($repository, 'source_queued', 'Source queued for import.');
                } else {
                    $skipped[] = new ConnectSourceOutcome($repository, 'source_already_queued', 'Source already queued for import.');
                }
            } catch (Throwable $exception) {
                $this->logFailure($exception, $connectSourcesCommand, $correlationId);
                $failed[] = new ConnectSourceOutcome($repository, 'internal_error', 'Unable to queue source for import.');
            }
        }

        return new ConnectSourcesResult($queued, $skipped, $failed, $correlationId);
    }

    private function batchFailure(
        ConnectSourcesCommand $connectSourcesCommand,
        string $code,
        string $message,
        string $correlationId
    ): ConnectSourcesResult {
        return new ConnectSourcesResult(failed: array_map(static fn (string $repository): ConnectSourceOutcome => new ConnectSourceOutcome($repository, $code, $message), $connectSourcesCommand->repositories), correlationId: $correlationId);
    }

    private function logFailure(Throwable $throwable, ConnectSourcesCommand $connectSourcesCommand, string $correlationId): void
    {
        // Provider messages, arguments and repository URLs can contain credentials.
        $this->logger->error('Source connection failed.', [
            'correlation_id'  => $correlationId,
            'organization_id' => $connectSourcesCommand->actorContext->organizationId,
            'provider'        => $connectSourcesCommand->provider,
            'exception_class' => $throwable::class,
            'exception_file'  => $throwable->getFile(),
            'exception_line'  => $throwable->getLine(),
        ]);
    }
}
