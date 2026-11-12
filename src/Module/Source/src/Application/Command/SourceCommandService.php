<?php

declare(strict_types=1);

namespace Packio\Source\Application\Command;

use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Exception\SourceInputException;
use Packio\Source\Application\Dto\SourceOAuthAuthorizationData;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Application\Exception\SourceOAuthAuthorizationException;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Security\ProviderAuthorizationFactory;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipStatus;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifierInterface;
use Packio\Source\Application\Service\WebhookAutoRegistrationService;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use Packio\Source\Domain\Value\SourceDeletionInitiation;

use function array_any;
use function count;
use function in_array;
use function preg_replace;
use function trim;

final readonly class SourceCommandService
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private SourceProviderRegistryInterface $sourceProviderRegistry,
        private ProviderAuthorizationFactory $providerAuthorizationFactory,
        private ProviderAuthorizationSecrets $providerAuthorizationSecrets,
        private SourceAuthorizationStrategyResolver $sourceAuthorizationStrategyResolver,
        private SourceMaintenanceService $sourceMaintenanceService,
        private SourceOAuthAccountOwnershipVerifierInterface $sourceOAuthAccountOwnershipVerifier,
        private SourceProviderInstanceRepositoryInterface $sourceProviderInstanceRepository,
        private SourceAuthorizationInvalidationService $sourceAuthorizationInvalidationService,
        private WebhookAutoRegistrationService $webhookAutoRegistrationService,
        private SourceWebhookSecrets $sourceWebhookSecrets,
    ) {}

    public function authorizeProvider(AuthorizeProviderCommand $authorizeProviderCommand): ProviderAuthorization
    {
        $organizationId = trim($authorizeProviderCommand->organizationId);
        if ('' === $organizationId) {
            throw new SourceInputException('Organization is required to authorize providers.');
        }

        $provider = trim($authorizeProviderCommand->provider);
        if ('' === $provider) {
            throw new SourceInputException('Provider is required to authorize providers.');
        }

        $payload = $this->payloadWithProviderInstance(
            $organizationId,
            $provider,
            $authorizeProviderCommand->payload,
        );

        $providerAuthorization = $this->providerAuthorizationFactory->create(
            organizationId: $organizationId,
            provider: $provider,
            authStrategy: $this->normalizeAuthorizationStrategy($provider, $authorizeProviderCommand->authorizationStrategy),
            payload: $payload,
            accountName: $this->resolveAuthorizationAccountName($provider, $payload),
        );
        $providerAuthorization->assignProviderInstance(
            '' !== trim((string) ($payload['provider_instance_id'] ?? ''))
                ? trim((string) ($payload['provider_instance_id'] ?? ''))
                : null,
        );

        return $this->providerAuthorizationRepository->save($providerAuthorization);
    }

    public function authorizeProviderInContext(
        ActorContext $actorContext,
        AuthorizeProviderCommand $authorizeProviderCommand
    ): ProviderAuthorization {
        $this->assertOwnerContext($actorContext);
        $this->assertContextOrganization($actorContext, $authorizeProviderCommand->organizationId);

        return $this->authorizeProvider($authorizeProviderCommand);
    }

    public function authorizeProviderFromSourceOAuth(
        ActorContext $actorContext,
        string $currentUserId,
        string $provider,
        string $sourceOAuthAccountId,
    ): ProviderAuthorization {
        $sourceOAuthAccountOwnershipDecision = $this->sourceOAuthAccountOwnershipVerifier->verify(
            $actorContext,
            $currentUserId,
            $provider,
            $sourceOAuthAccountId,
        );
        if (! $sourceOAuthAccountOwnershipDecision->isAllowed()) {
            throw SourceOAuthAuthorizationException::fromDecision($sourceOAuthAccountOwnershipDecision);
        }

        return $this->authorizeProviderWithOAuthAccountData(
            $actorContext,
            $provider,
            $sourceOAuthAccountOwnershipDecision->requireAuthorizationData(),
        );
    }

    public function registerSource(RegisterSourceCommand $registerSourceCommand): Source
    {
        $organizationId = trim($registerSourceCommand->organizationId);
        if ('' === $organizationId) {
            throw new SourceInputException('Organization is required to register sources.');
        }
        $providerName    = trim($registerSourceCommand->provider);
        $provider        = $this->sourceProviderRegistry->provider($providerName);
        $authorizationId = trim($registerSourceCommand->providerAuthorizationId);
        $authorization   = null;

        if ('' !== $authorizationId) {
            $authorization = $this->providerAuthorizationRepository->findByIdInOrganization(
                $authorizationId,
                $organizationId,
            );

            if (! $authorization instanceof ProviderAuthorization) {
                throw new SourceInputException(
                    'Provider authorization is not available in this organization.',
                );
            }

            if ($authorization->provider() !== $providerName) {
                throw new SourceInputException(
                    'Provider authorization does not match the selected provider.',
                );
            }

            $providerInstanceId = trim((string) $authorization->providerInstanceId());
            if ('gitlab_self_hosted' === $providerName && '' !== $providerInstanceId) {
                if (! $this->sourceProviderInstanceRepository->findActiveByIdInOrganization($providerInstanceId, $organizationId) instanceof SourceProviderInstance) {
                    throw new SourceInputException('Source provider instance is not available.');
                }
            }

            if ('gitlab_self_hosted' === $providerName) {
                $requestedProviderInstanceId     = trim($registerSourceCommand->providerInstanceId);
                $authorizationProviderInstanceId = trim((string) $authorization->providerInstanceId());
                if ('' !== $requestedProviderInstanceId) {
                    if (! $this->sourceProviderInstanceRepository->findActiveByIdInOrganization($requestedProviderInstanceId, $organizationId) instanceof SourceProviderInstance
                        || $authorizationProviderInstanceId !== $requestedProviderInstanceId) {
                        throw new SourceInputException('Source provider instance does not match the selected authorization.');
                    }
                } elseif ('' === $authorizationProviderInstanceId && $this->hasActiveGitlabSelfHostedInstance($organizationId)) {
                    throw new SourceInputException('An active GitLab instance must be selected for this authorization.');
                }
            }
        }

        $sourceRegistrationData = new SourceRegistrationData(
            provider: $registerSourceCommand->provider,
            organizationId: $organizationId,
            resourceId: $registerSourceCommand->resourceId,
            name: $registerSourceCommand->name,
            type: $providerName,
            url: $registerSourceCommand->resourceId,
            defaultBranch: trim($registerSourceCommand->defaultRef),
            visibility: $registerSourceCommand->visibility,
            authorizationId: '' !== $authorizationId ? $authorizationId : null,
            composerJsonPath: trim((string) ($registerSourceCommand->settings['composer_json_path'] ?? 'composer.json')),
        );

        $source = $provider->registerSource(
            $organizationId,
            $sourceRegistrationData,
            $authorization instanceof ProviderAuthorization
                ? $authorization
                : null,
        );
        $source->replaceProviderConfig($this->sourceWebhookSecrets->encryptConfiguration($source->providerConfig()));
        $source->updateKeepLastReleases($registerSourceCommand->keepLastReleases);
        $existing = $this->sourceRepository->findByUrl(
            $source->canonicalUrl(),
            $organizationId,
        );
        if ($existing instanceof Source) {
            $existing->updateDefaultRef($source->defaultRef());
            $existing->updateVisibility($registerSourceCommand->visibility);
            $existing->updateKeepLastReleases($registerSourceCommand->keepLastReleases);
            if (
                $authorization instanceof ProviderAuthorization
                && $existing->providerAuthorizationId() !== $authorization->getIdentifier()->toString()
            ) {
                $existing->assignProviderAuthorization(
                    $authorization->getIdentifier()->toString(),
                );
            }

            if ('gitlab_self_hosted' === $providerName) {
                $existing->assignProviderInstance($source->providerInstanceId());
                $existingConfig = $existing->providerConfig();
                $sourceConfig   = $source->providerConfig();
                foreach (['base_url', 'provider_instance_id'] as $key) {
                    if (isset($sourceConfig[$key])) {
                        $existingConfig[$key] = $sourceConfig[$key];
                    } else {
                        unset($existingConfig[$key]);
                    }
                }
                $existing->replaceProviderConfig($existingConfig);
            }

            $this->ensureWebhookSecret($existing, $source);

            return $this->sourceRepository->save($existing);
        }

        return $this->sourceRepository->save($source);
    }

    public function registerSourceInContext(ActorContext $actorContext, RegisterSourceCommand $registerSourceCommand): Source
    {
        $this->assertOwnerContext($actorContext);
        $this->assertContextOrganization($actorContext, $registerSourceCommand->organizationId);

        return $this->registerSource($registerSourceCommand);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertSourceAuthorization(string $id, string $authStrategy, array $payload): ?Source
    {
        $entity = $this->sourceRepository->findById($id);
        if (! $entity instanceof Source) {
            return null;
        }

        $normalizedStrategy = '' !== trim($authStrategy)
            ? trim($authStrategy)
            : 'token';

        $authorization = '' !== $entity->providerAuthorizationId()
            ? $this->providerAuthorizationRepository->findByIdInOrganization(
                $entity->providerAuthorizationId(),
                $entity->organizationId(),
            )
            : null;

        if (
            ! $authorization instanceof ProviderAuthorization
            || $authorization->provider() !== $entity->provider()
        ) {
            $authorization = $this->providerAuthorizationRepository->save(
                $this->providerAuthorizationFactory->create(
                    organizationId: $entity->organizationId(),
                    provider: $entity->provider(),
                    authStrategy: $normalizedStrategy,
                    payload: $payload,
                ),
            );
        } else {
            $authorization->updateStatus('active');
            $authorization->replaceConfiguration($this->providerAuthorizationSecrets->encryptConfiguration($payload));
            $this->providerAuthorizationRepository->save($authorization);
        }

        if ('' === $entity->providerAuthorizationId()) {
            $entity->assignProviderAuthorization(
                $authorization->getIdentifier()->toString(),
            );
            $this->sourceRepository->save($entity);
        }

        return $entity;
    }

    public function tryRegisterWebhook(string $id): WebhookRegistrationResult
    {
        $entity = $this->sourceRepository->findById($id);
        if (! $entity instanceof Source) {
            return WebhookRegistrationResult::failure('Source not found.');
        }

        $this->ensureWebhookSecret($entity);

        return $this->webhookAutoRegistrationService->register($entity);
    }

    public function supportsWebhookAutoRegistration(string $provider): bool
    {
        return $this->webhookAutoRegistrationService->supports($provider);
    }

    public function rotateWebhookSecret(string $id): ?Source
    {
        $entity = $this->sourceRepository->findById($id);
        if (! $entity instanceof Source) {
            return null;
        }

        $provider  = $this->sourceProviderRegistry->provider($entity->provider());
        $newSecret = $provider->generateWebhookSecret();

        $config                   = $entity->providerConfig();
        $config['webhook_secret'] = $newSecret;
        $entity->replaceProviderConfig($this->sourceWebhookSecrets->encryptConfiguration($config));

        return $this->sourceRepository->save($entity);
    }

    public function rotateWebhookSecretInContext(ActorContext $actorContext, string $id): ?Source
    {
        $this->assertOwnerContext($actorContext);

        return $this->sourceRepository->findByIdInOrganization(
            $id,
            $actorContext->organizationId,
        ) instanceof Source
            ? $this->rotateWebhookSecret($id)
            : null;
    }

    public function updateSettings(string $id, string $visibility, bool $active, int $keepLastReleases): ?Source
    {
        $entity = $this->sourceRepository->findById($id);
        if (! $entity instanceof Source) {
            return null;
        }

        $entity->updateVisibility($visibility);
        $entity->updateKeepLastReleases($keepLastReleases);
        if ($entity->isArchived()) {
            $entity->deactivate();
        } elseif ($active) {
            $entity->activate();
        } else {
            $entity->deactivate();
        }

        return $this->sourceRepository->save($entity);
    }

    public function updateSettingsInContext(
        ActorContext $actorContext,
        string $id,
        string $visibility,
        bool $active,
        int $keepLastReleases
    ): ?Source {
        $this->assertOwnerContext($actorContext);

        return $this->sourceRepository->findByIdInOrganization(
            $id,
            $actorContext->organizationId,
        ) instanceof Source
            ? $this->updateSettings($id, $visibility, $active, $keepLastReleases)
            : null;
    }

    public function archive(string $id): ?Source
    {
        $entity = $this->sourceRepository->findById($id);
        if (! $entity instanceof Source) {
            return null;
        }

        $entity->archive();

        return $this->sourceRepository->save($entity);
    }

    public function archiveInContext(ActorContext $actorContext, string $id): ?Source
    {
        $this->assertOwnerContext($actorContext);

        return $this->sourceRepository->findByIdInOrganization(
            $id,
            $actorContext->organizationId,
        ) instanceof Source
            ? $this->archive($id)
            : null;
    }

    public function initiateDeletion(string $id, string $organizationId): SourceDeletionInitiation
    {
        return $this->sourceRepository->initiateDeletion($id, $organizationId);
    }

    public function initiateDeletionInContext(ActorContext $actorContext, string $id): SourceDeletionInitiation
    {
        $this->assertOwnerContext($actorContext);

        return $this->sourceRepository->initiateDeletion($id, $actorContext->organizationId);
    }

    public function deletionRunId(string $id, string $organizationId): ?string
    {
        return $this->sourceRepository->deletionRunId($id, $organizationId);
    }

    public function recoverDeletionRun(string $id, string $organizationId, bool $force): ?string
    {
        return $this->sourceRepository->recoverDeletionRun($id, $organizationId, $force);
    }

    public function ensureDeletionRun(string $id, string $organizationId, string $deletionRunId): bool
    {
        return $this->sourceRepository->ensureDeletionRun($id, $organizationId, $deletionRunId);
    }

    public function revertInitiation(
        string $id,
        string $organizationId,
        bool $previousActive,
        string $deletionRunId,
    ): void {
        $this->sourceRepository->revertInitiation($id, $organizationId, $previousActive, $deletionRunId);
    }

    public function restore(string $id): ?Source
    {
        $entity = $this->sourceRepository->findById($id);
        if (! $entity instanceof Source) {
            return null;
        }

        $entity->restore();

        return $this->sourceRepository->save($entity);
    }

    public function restoreInContext(ActorContext $actorContext, string $id): ?Source
    {
        $this->assertOwnerContext($actorContext);

        return $this->sourceRepository->findByIdInOrganization(
            $id,
            $actorContext->organizationId,
        ) instanceof Source
            ? $this->restore($id)
            : null;
    }

    public function cancelDeletingAsInactive(string $id, string $organizationId): ?Source
    {
        $source = $this->sourceRepository->findByIdInOrganization($id, $organizationId);
        if (! $source instanceof Source || ! $source->isDeleting()) {
            return null;
        }

        $source->cancelDeletingAsInactive();

        return $this->sourceRepository->save($source);
    }

    /**
     * @return array{
     *     source_found: bool,
     *     deregistration_required: bool,
     *     deregistered: bool,
     *     deleted: bool
     * }
     */
    public function deleteSourceAfterWebhookCleanup(
        string $id,
        ?string $organizationId = null,
        ?string $deletionRunId = null,
    ): array {
        $source = null !== $organizationId
            ? $this->sourceRepository->findByIdInOrganization($id, $organizationId)
            : $this->sourceRepository->findById($id);

        if (! $source instanceof Source) {
            return [
                'source_found'            => false,
                'deregistration_required' => false,
                'deregistered'            => false,
                'deleted'                 => true,
            ];
        }

        if (
            null !== $deletionRunId
            && ! $this->sourceRepository->isDeletionRunCurrent($id, (string) $organizationId, $deletionRunId)
        ) {
            return [
                'source_found'            => true,
                'deregistration_required' => false,
                'deregistered'            => false,
                'deleted'                 => false,
            ];
        }

        $deregistrationRequired = $this->webhookAutoRegistrationService->supports($source->provider())
            && '' !== (string) ($source->providerConfig()['webhook_external_id'] ?? '');
        $deregistered = false;

        if ($deregistrationRequired) {
            $deregistered = $this->webhookAutoRegistrationService->deregister($source, $deletionRunId);
        }

        return [
            'source_found'            => true,
            'deregistration_required' => $deregistrationRequired,
            'deregistered'            => $deregistered,
            'deleted'                 => $this->sourceMaintenanceService->deleteSource($id),
        ];
    }

    public function deleteSource(string $id): bool
    {
        if (! $this->sourceRepository->findById($id) instanceof Source) {
            return false;
        }

        return $this->deleteSourceAfterWebhookCleanup($id)['deleted'];
    }

    /**
     * @return array{success: bool, reassigned?: int, error?: string, not_found?: true}
     */
    public function reassignAndDeleteProviderAuthorization(string $id, string $organizationId, ?string $newAuthorizationId): array
    {
        if ($newAuthorizationId === $id) {
            return [
                'success' => false,
                'error'   => 'Choose a different authorization.',
            ];
        }

        $authorization = $this->providerAuthorizationRepository->findByIdInOrganization(
            $id,
            $organizationId,
        );

        if (! $authorization instanceof ProviderAuthorization) {
            return [
                'success'   => false,
                'error'     => 'Authorization not found.',
                'not_found' => true,
            ];
        }

        $sources = $this->sourceRepository->findByProviderAuthorizationId(
            $id,
            $organizationId,
        );

        if (0 === count($sources)) {
            $this->providerAuthorizationRepository->deleteAndFlush($authorization);

            return [
                'success'    => true,
                'reassigned' => 0,
            ];
        }

        if (in_array($newAuthorizationId, [null, '', '0'], true)) {
            return [
                'success' => false,
                'error'   => 'Cannot delete authorization while sources are linked to it. Delete all linked sources or reassign them first.',
            ];
        }

        $newAuth = $this->providerAuthorizationRepository->findByIdInOrganization(
            $newAuthorizationId,
            $organizationId,
        );

        if (! $newAuth instanceof ProviderAuthorization) {
            return [
                'success' => false,
                'error'   => 'New authorization not found.',
            ];
        }

        if ($newAuth->provider() !== $authorization->provider()) {
            return [
                'success' => false,
                'error'   => 'New authorization must match the same provider.',
            ];
        }

        foreach ($sources as $source) {
            $source->assignProviderAuthorization($newAuth->getIdentifier()->toString());
            $this->sourceRepository->save($source);
        }

        $this->providerAuthorizationRepository->deleteAndFlush($authorization);

        return [
            'success'    => true,
            'reassigned' => count($sources),
        ];
    }

    /**
     * @return array{success: bool, reassigned?: int, error?: string, not_found?: true}
     */
    public function reassignAndDeleteProviderAuthorizationInContext(
        ActorContext $actorContext,
        string $id,
        ?string $newAuthorizationId
    ): array {
        $this->assertOwnerContext($actorContext);

        return $this->reassignAndDeleteProviderAuthorization(
            $id,
            $actorContext->organizationId,
            $newAuthorizationId,
        );
    }

    public function markSyncSucceeded(string $id): ?Source
    {
        return $this->sourceMaintenanceService->markSyncSucceeded($id);
    }

    public function markSyncFailed(string $id, string $error): ?Source
    {
        return $this->sourceMaintenanceService->markSyncFailed($id, $error);
    }

    public function markSourceAuthorizationInvalidForSource(string $sourceId): void
    {
        $this->sourceAuthorizationInvalidationService->markInvalidForSource($sourceId);
    }

    private function authorizeProviderWithOAuthAccountData(
        ActorContext $actorContext,
        string $provider,
        SourceOAuthAuthorizationData $sourceOAuthAuthorizationData,
    ): ProviderAuthorization {
        $this->assertOwnerContext($actorContext);

        $organizationId       = $actorContext->organizationId;
        $sourceOAuthAccountId = trim($sourceOAuthAuthorizationData->sourceOAuthAccountId);
        $accessToken          = trim($sourceOAuthAuthorizationData->accessToken);
        $accountName          = $sourceOAuthAuthorizationData->accountName();
        $providerBaseUrl      = trim((string) $sourceOAuthAuthorizationData->providerBaseUrl);
        $providerInstanceId   = trim((string) $sourceOAuthAuthorizationData->providerInstanceId);

        if ('' === $sourceOAuthAccountId || '' === $accessToken) {
            throw new SourceInputException('OAuth account data is incomplete.');
        }

        if ('' !== $providerInstanceId) {
            $sourceProviderInstance = $this->sourceProviderInstanceRepository->findActiveByIdInOrganization(
                $providerInstanceId,
                $organizationId,
            );
            if (! $sourceProviderInstance instanceof SourceProviderInstance || $sourceProviderInstance->providerType() !== $provider) {
                throw new SourceOAuthAuthorizationException(
                    SourceOAuthAccountOwnershipStatus::Unavailable,
                    'Source provider instance is not available.',
                );
            }

            $providerBaseUrl = $sourceProviderInstance->baseUrl();
        }

        $authStrategy = 'oauth2';

        $providerAuthorization = $this->providerAuthorizationFactory->createFromOAuth(
            organizationId: $organizationId,
            provider: $provider,
            sourceOAuthAccountId: $sourceOAuthAccountId,
            authStrategy: $authStrategy,
            payload: [
                'token'         => $accessToken,
                'account_id'    => '',
                'resource_name' => '',
                'resource_url'  => '',
                ...('' !== $providerBaseUrl ? [
                    'base_url' => $providerBaseUrl,
                ] : []),
                ...('' !== $providerInstanceId ? [
                    'provider_instance_id' => $providerInstanceId,
                ] : []),
            ],
            accountName: $accountName,
        );
        $providerAuthorization->assignProviderInstance('' !== $providerInstanceId ? $providerInstanceId : null);

        return $this->providerAuthorizationRepository->save($providerAuthorization);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveAuthorizationAccountName(string $provider, array $payload): string
    {
        $accountName = trim((string) ($payload['account_name'] ?? ''));
        if ('' !== $accountName) {
            return $accountName;
        }

        if ('gitlab_self_hosted' !== $provider) {
            return '';
        }

        $baseUrl = trim((string) ($payload['base_url'] ?? ''));
        if ('' === $baseUrl) {
            return '';
        }

        return (string) preg_replace('#^https://([^/]+)/?.*$#', '$1', $baseUrl);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function payloadWithProviderInstance(string $organizationId, string $provider, array $payload): array
    {
        $providerInstanceId = trim((string) ($payload['provider_instance_id'] ?? ''));
        if (
            SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED !== $provider
            || '' === $providerInstanceId
        ) {
            return $payload;
        }

        $instance = $this->sourceProviderInstanceRepository->findActiveByIdInOrganization(
            $providerInstanceId,
            $organizationId,
        );
        if (! $instance instanceof SourceProviderInstance || $instance->providerType() !== $provider) {
            throw new SourceInputException('Source provider instance is not available.');
        }

        if (! $instance->supportsToken()) {
            throw new SourceInputException('Source provider instance does not support token authorization.');
        }

        return [
            ...$payload,
            'base_url'             => $instance->baseUrl(),
            'provider_instance_id' => $providerInstanceId,
        ];
    }

    private function hasActiveGitlabSelfHostedInstance(string $organizationId): bool
    {
        return array_any($this->sourceProviderInstanceRepository->findByOrganization($organizationId, SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED), fn ($sourceProviderInstance) => $sourceProviderInstance->isActive());
    }

    private function normalizeAuthorizationStrategy(string $provider, string $authStrategy): string
    {
        return $this->sourceAuthorizationStrategyResolver->normalize(
            $provider,
            $authStrategy,
        );
    }

    private function assertOwnerContext(ActorContext $actorContext): void
    {
        if (
            '' === trim($actorContext->organizationId)
            || '' === trim($actorContext->actorId)
            || ! $actorContext->isOwner()
        ) {
            throw new SourceInputException('Owner actor context is required.');
        }
    }

    private function assertContextOrganization(ActorContext $actorContext, string $organizationId): void
    {
        if ($actorContext->organizationId !== trim($organizationId)) {
            throw new SourceInputException(
                'Actor context organization does not match the request.',
            );
        }
    }

    private function ensureWebhookSecret(Source $source, ?Source $defaults = null): void
    {
        $config = $source->providerConfig();
        // A secret that cannot be decrypted (e.g. after a key change) counts as missing.
        if ('' !== trim($this->sourceWebhookSecrets->decrypt((string) ($config['webhook_secret'] ?? '')))) {
            return;
        }

        $defaultConfig = $defaults?->providerConfig() ?? [];
        $secret        = trim((string) ($defaultConfig['webhook_secret'] ?? ''));
        if ('' === $secret) {
            $secret = $this->sourceProviderRegistry->provider($source->provider())->generateWebhookSecret();
        }

        if ('' === $secret) {
            return;
        }

        $config['webhook_secret'] = $secret;
        $source->replaceProviderConfig($this->sourceWebhookSecrets->encryptConfiguration($config));
        $this->sourceRepository->save($source);
    }
}
