<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter\Worker;

use Packio\Source\Application\Command\RegisterSourceCommand;
use Packio\Source\Application\Service\SourceCreationExecutionInterface;
use Packio\Source\Application\Service\SourceCreationOutcome;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;

use function in_array;
use function is_array;
use function is_string;
use function trim;

final readonly class SourceCreateJobHandler implements JobHandlerInterface
{
    public function __construct(private SourceCreationExecutionInterface $sourceCreationExecution) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        $organizationId          = $payload['organization_id'] ?? null;
        $requestedByUserId       = $payload['requested_by_user_id'] ?? null;
        $provider                = $payload['provider'] ?? null;
        $resource                = $payload['resource'] ?? null;
        $defaultRef              = $payload['default_ref'] ?? '';
        $visibility              = $payload['visibility'] ?? 'public';
        $providerAuthorizationId = $payload['provider_authorization_id'] ?? '';
        $providerInstanceId      = $payload['provider_instance_id'] ?? '';
        $keepLastReleases        = (int) ($payload['keep_last_releases'] ?? 0);
        $idempotencyKey          = $payload['idempotency_key'] ?? null;
        $settings                = $payload['settings'] ?? [];

        if (! is_string($organizationId) || '' === $organizationId) {
            return JobResult::failure('Organization ID is required.');
        }

        if (! is_string($provider) || '' === $provider) {
            return JobResult::failure('Provider is required.');
        }

        if (! is_string($resource) || '' === $resource) {
            return JobResult::failure('Resource URL is required.');
        }

        if (! is_string($requestedByUserId) || '' === trim($requestedByUserId)) {
            return JobResult::failure('Requested by user ID is required.');
        }

        if ($keepLastReleases < 0) {
            return JobResult::failure('Keep last releases must be zero or greater.');
        }

        $jobLogger->info('Starting source creation', [
            'provider'           => $provider,
            'resource'           => $resource,
            'organization_id'    => $organizationId,
            'idempotency_key'    => $idempotencyKey,
            'keep_last_releases' => $keepLastReleases,
        ]);

        $sourceCreationExecutionResult = $this->sourceCreationExecution->execute(
            new RegisterSourceCommand(
                organizationId: $organizationId,
                provider: $provider,
                resourceId: $resource,
                name: $resource,
                defaultRef: trim((string) $defaultRef),
                visibility: in_array($visibility, ['private', 'public'], true) ? $visibility : 'public',
                providerAuthorizationId: is_string($providerAuthorizationId) ? $providerAuthorizationId : '',
                providerInstanceId: is_string($providerInstanceId) ? $providerInstanceId : '',
                settings: is_array($settings) ? $settings : [],
                keepLastReleases: $keepLastReleases,
            ),
            $requestedByUserId,
            new SourceJobLogger($jobLogger),
        );
        $data = [
            ...$sourceCreationExecutionResult->data,
            'outcome'        => $sourceCreationExecutionResult->outcome->value,
            'webhook_status' => $sourceCreationExecutionResult->webhook->value,
        ];

        return match ($sourceCreationExecutionResult->outcome) {
            SourceCreationOutcome::Created          => JobResult::success($data),
            SourceCreationOutcome::Forbidden        => JobResult::failure($sourceCreationExecutionResult->error ?? 'Source creation forbidden.', $data, shouldRetry: false),
            SourceCreationOutcome::RetryableFailure => JobResult::failure($sourceCreationExecutionResult->error ?? 'Source creation failed.', $data),
        };
    }
}
