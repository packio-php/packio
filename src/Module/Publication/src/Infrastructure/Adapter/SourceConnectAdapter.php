<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Adapter;

use Packio\Publication\Application\Command\ConnectSourcesCommand;
use Packio\Publication\Application\Port\SourceConnectPort;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;

final readonly class SourceConnectAdapter implements SourceConnectPort
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private JobApiFacadeInterface $jobApiFacade
    ) {}

    public function authorize(ConnectSourcesCommand $connectSourcesCommand): string
    {
        return $this->sourceApiFacade->resolveConnectAuthorizationInContext(
            $connectSourcesCommand->actorContext,
            $connectSourcesCommand->provider,
            $connectSourcesCommand->authorizationId,
            $connectSourcesCommand->authorizationPayload,
            $connectSourcesCommand->providerInstanceId,
        );
    }

    public function schedule(array $payload, string $organizationId): bool
    {
        return $this->jobApiFacade->enqueueSourceCreation(
            organizationId: $organizationId,
            idempotencyKey: (string) $payload['idempotency_key'],
            payload: $payload,
        );
    }
}
