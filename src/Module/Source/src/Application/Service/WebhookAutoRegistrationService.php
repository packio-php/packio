<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function is_string;
use function sprintf;

/**
 * Best-effort orchestration of automatic webhook registration at the upstream provider.
 *
 * This service never throws — all failures are captured in the returned result.
 * It is intended to be called after source registration, as a non-blocking step.
 */
final readonly class WebhookAutoRegistrationService
{
    public function __construct(
        private WebhookRegistrarRegistryInterface $webhookRegistrarRegistry,
        private SourceRepositoryInterface $sourceRepository,
        private SourceAuthorizationInvalidationService $sourceAuthorizationInvalidationService,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Attempts to register a webhook at the upstream provider for the given source.
     *
     * On success, persists the upstream webhook ID into providerConfig['webhook_external_id']
     * so it can be used later for deletion.
     *
     * Returns a failure result (without throwing) when:
     * - The provider does not support auto-registration
     * - No token is available
     * - The upstream API returns an error
     */
    public function register(Source $source): WebhookRegistrationResult
    {
        $registrar = $this->webhookRegistrarRegistry->forProvider($source->provider());
        if (! $registrar instanceof WebhookRegistrarInterface) {
            return WebhookRegistrationResult::failure(
                sprintf(
                    'Automatic webhook registration is not supported for provider "%s".',
                    $source->provider(),
                ),
            );
        }

        try {
            $result = $registrar->register($source);
        } catch (SourceTokenMissingException|SourceTokenRefreshFailedException|SourceTokenRefreshUnavailableException $e) {
            if ($e instanceof SourceTokenRefreshFailedException && ! $e->requiresReauthorization) {
                return WebhookRegistrationResult::failure(
                    sprintf('Webhook registration failed unexpectedly: %s', $e->getMessage()),
                );
            }

            $this->sourceAuthorizationInvalidationService->markInvalidForSource(
                $source->getIdentifier()->toString(),
            );

            return WebhookRegistrationResult::failure(
                'Provider authorization is invalid or expired. Please reconnect.',
                'reauthorization_required',
                $e->getMessage(),
            );
        } catch (Throwable $e) {
            return WebhookRegistrationResult::failure(
                sprintf('Webhook registration failed unexpectedly: %s', $e->getMessage()),
            );
        }

        if (! $result->success) {
            return WebhookRegistrationResult::failure(
                $result->message,
                $result->authHealth,
                $result->authFailureReason,
            );
        }

        if ('' !== $result->externalId) {
            $this->persistExternalId($source->getIdentifier()->toString(), $result->externalId);
        }

        if ($result->alreadyExists) {
            return WebhookRegistrationResult::alreadyExists($result->externalId, $result->message);
        }

        return WebhookRegistrationResult::success($result->externalId, $result->message);
    }

    /**
     * Attempts to delete a previously registered webhook at the upstream provider.
     *
     * Returns true if deleted, already absent, or provider not supported.
     * Never throws.
     */
    public function deregister(Source $source, ?string $deletionRunId = null): bool
    {
        if (null !== $deletionRunId) {
            $this->logger?->info('Starting idempotent upstream webhook deletion.', [
                'source_id'       => $source->getIdentifier()->toString(),
                'organization_id' => $source->organizationId(),
                'deletion_run_id' => $deletionRunId,
            ]);
        }

        $registrar = $this->webhookRegistrarRegistry->forProvider($source->provider());
        if (! $registrar instanceof WebhookRegistrarInterface) {
            return true;
        }

        try {
            $deleted = $registrar->deregister($source);
        } catch (Throwable) {
            return false;
        }

        if ($deleted) {
            $this->clearExternalId($source->getIdentifier()->toString());
        }

        if (null !== $deletionRunId) {
            $this->logger?->info('Idempotent upstream webhook deletion finished.', [
                'source_id'       => $source->getIdentifier()->toString(),
                'organization_id' => $source->organizationId(),
                'deletion_run_id' => $deletionRunId,
                'deleted'         => $deleted,
            ]);
        }

        return $deleted;
    }

    /**
     * Returns true if the provider supports automatic webhook registration.
     */
    public function supports(string $provider): bool
    {
        return $this->webhookRegistrarRegistry->hasProvider($provider);
    }

    private function persistExternalId(string $sourceId, string $externalId): void
    {
        $entity = $this->sourceRepository->findById($sourceId);
        if (! $entity instanceof Source) {
            return;
        }

        $config = $entity->providerConfig();
        if (
            is_string($config['webhook_external_id'] ?? null)
            && '' !== $config['webhook_external_id']
        ) {
            // Already persisted — do not overwrite.
            return;
        }

        $config['webhook_external_id'] = $externalId;
        $entity->replaceProviderConfig($config);
        $this->sourceRepository->save($entity);
    }

    private function clearExternalId(string $sourceId): void
    {
        $entity = $this->sourceRepository->findById($sourceId);
        if (! $entity instanceof Source) {
            return;
        }

        $config = $entity->providerConfig();
        unset($config['webhook_external_id']);
        $entity->replaceProviderConfig($config);
        $this->sourceRepository->save($entity);
    }
}
