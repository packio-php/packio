<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Facade;

use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Dto\ProviderAuthorizationRequestDto;
use Packio\Source\Api\Dto\ProviderResourceDto;
use Packio\Source\Api\Dto\SourceDeletionInitiationResult;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceOAuthAccountDto;
use Packio\Source\Api\Dto\SourceProviderDto;
use Packio\Source\Api\Dto\SourceProviderInstanceDependenciesDto;
use Packio\Source\Api\Dto\SourceProviderInstanceDto;
use Packio\Source\Api\Dto\SourceProviderInstanceRequestDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\Dto\SourceSettingsPatch;
use Packio\Source\Api\Dto\SourceSettingsResultDto;
use Packio\Source\Api\Dto\WebhookRegistrationResultDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Application\Command\AuthorizeProviderCommand;
use Packio\Source\Application\Command\RegisterSourceCommand;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceProviderInstanceService;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Provider\SourceProviderLabelResolver;
use Packio\Source\Application\Query\SourceQueryService;
use Packio\Source\Application\Service\SourceConnectAuthorizationResolver;
use Packio\Source\Application\Service\SourceJobScheduler;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceSettingsService;
use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Infrastructure\Mapper\ProviderAuthorizationDtoMapper;
use Packio\Source\Infrastructure\Mapper\SourceDtoMapper;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_map;

final readonly class SourceApiFacade implements SourceApiFacadeInterface
{
    public function __construct(
        private SourceCommandService $sourceCommandService,
        private SourceQueryService $sourceQueryService,
        private SourceJobScheduler $sourceJobScheduler,
        private SourceOAuthService $sourceOAuthService,
        private SourceDtoMapper $sourceDtoMapper,
        private ProviderAuthorizationDtoMapper $providerAuthorizationDtoMapper,
        private ObjectMapperInterface $objectMapper,
        private OAuthProviderPort $oAuthProviderPort,
        private SourceProviderInstanceService $sourceProviderInstanceService,
        private SourceProviderLabelResolver $sourceProviderLabelResolver,
        private SourceConnectAuthorizationResolver $sourceConnectAuthorizationResolver,
        private SourceSettingsService $sourceSettingsService,
    ) {}

    public function authorizeProvider(ProviderAuthorizationRequestDto $providerAuthorizationRequestDto): ProviderAuthorizationDto
    {
        return $this->providerAuthorizationDtoMapper->map(
            $this->sourceCommandService->authorizeProvider(
                new AuthorizeProviderCommand(
                    organizationId: $providerAuthorizationRequestDto->organizationId,
                    provider: $providerAuthorizationRequestDto->provider,
                    authorizationStrategy: $providerAuthorizationRequestDto->authStrategy,
                    payload: $providerAuthorizationRequestDto->payload,
                ),
            ),
        );
    }

    public function authorizeProviderInContext(
        ActorContext $actorContext,
        ProviderAuthorizationRequestDto $providerAuthorizationRequestDto
    ): ProviderAuthorizationDto {
        return $this->providerAuthorizationDtoMapper->map(
            $this->sourceCommandService->authorizeProviderInContext(
                $actorContext,
                new AuthorizeProviderCommand(
                    organizationId: $providerAuthorizationRequestDto->organizationId,
                    provider: $providerAuthorizationRequestDto->provider,
                    authorizationStrategy: $providerAuthorizationRequestDto->authStrategy,
                    payload: $providerAuthorizationRequestDto->payload,
                ),
            ),
        );
    }

    public function resolveConnectAuthorizationInContext(
        ActorContext $actorContext,
        string $provider,
        string $authorizationId,
        array $authorizationPayload,
        string $providerInstanceId,
    ): string {
        return $this->sourceConnectAuthorizationResolver->resolve($actorContext, $provider, $authorizationId, $authorizationPayload, $providerInstanceId);
    }

    public function updateSourceSettings(
        ActorContext $actorContext,
        string $sourceId,
        SourceSettingsPatch $sourceSettingsPatch,
    ): SourceSettingsResultDto {
        return $this->sourceSettingsService->update($actorContext, $sourceId, $sourceSettingsPatch);
    }

    public function authorizeProviderFromSourceOAuth(
        ActorContext $actorContext,
        string $currentUserId,
        string $provider,
        string $sourceOAuthAccountId,
    ): ProviderAuthorizationDto {
        return $this->providerAuthorizationDtoMapper->map(
            $this->sourceCommandService->authorizeProviderFromSourceOAuth(
                $actorContext,
                $currentUserId,
                $provider,
                $sourceOAuthAccountId,
            ),
        );
    }

    public function handleSourceOAuthCallback(
        string $provider,
        string $code,
        string $userId,
        string $organizationSlug,
        ?string $codeVerifier = null,
    ): SourceOAuthAccountDto {
        $sourceOAuthAccount = $this->sourceOAuthService->handleSourceCallback(
            $provider,
            $code,
            $userId,
            $organizationSlug,
            $codeVerifier,
        );

        return $this->objectMapper->map($sourceOAuthAccount, SourceOAuthAccountDto::class);
    }

    public function handleSourceProviderInstanceOAuthCallback(
        string $provider,
        string $code,
        string $userId,
        string $organizationSlug,
        string $providerInstanceId,
        ?string $codeVerifier = null,
    ): SourceOAuthAccountDto {
        $sourceOAuthAccount = $this->sourceOAuthService->handleSourceProviderInstanceCallback(
            $provider,
            $code,
            $userId,
            $organizationSlug,
            $providerInstanceId,
            $codeVerifier,
        );

        return $this->objectMapper->map($sourceOAuthAccount, SourceOAuthAccountDto::class);
    }

    public function sourceProviderInstances(string $organizationId, ?string $providerType = null): array
    {
        return array_map(
            $this->mapSourceProviderInstance(...),
            $this->sourceProviderInstanceService->listForOrganization($organizationId, $providerType),
        );
    }

    public function createSourceProviderInstanceInContext(
        ActorContext $actorContext,
        SourceProviderInstanceRequestDto $sourceProviderInstanceRequestDto
    ): SourceProviderInstanceDto {
        return $this->mapSourceProviderInstance(
            $this->sourceProviderInstanceService->createInContext(
                actorContext: $actorContext,
                providerType: $sourceProviderInstanceRequestDto->providerType,
                label: $sourceProviderInstanceRequestDto->label,
                baseUrl: $sourceProviderInstanceRequestDto->baseUrl,
                clientId: $sourceProviderInstanceRequestDto->clientId,
                clientSecret: $sourceProviderInstanceRequestDto->clientSecret,
                authMode: $sourceProviderInstanceRequestDto->authMode,
                metadata: $sourceProviderInstanceRequestDto->metadata,
            ),
        );
    }

    public function updateSourceProviderInstanceInContext(
        ActorContext $actorContext,
        string $id,
        SourceProviderInstanceRequestDto $sourceProviderInstanceRequestDto
    ): SourceProviderInstanceDto {
        return $this->mapSourceProviderInstance(
            $this->sourceProviderInstanceService->updateInContext(
                actorContext: $actorContext,
                id: $id,
                label: $sourceProviderInstanceRequestDto->label,
                baseUrl: $sourceProviderInstanceRequestDto->baseUrl,
                clientId: $sourceProviderInstanceRequestDto->clientId,
                authMode: $sourceProviderInstanceRequestDto->authMode,
                replacementClientSecret: $sourceProviderInstanceRequestDto->clientSecret,
                metadata: $sourceProviderInstanceRequestDto->metadata,
            ),
        );
    }

    public function activateSourceProviderInstanceInContext(ActorContext $actorContext, string $id): SourceProviderInstanceDto
    {
        return $this->mapSourceProviderInstance(
            $this->sourceProviderInstanceService->activateInContext($actorContext, $id),
        );
    }

    public function deactivateSourceProviderInstanceInContext(ActorContext $actorContext, string $id): SourceProviderInstanceDto
    {
        return $this->mapSourceProviderInstance(
            $this->sourceProviderInstanceService->deactivateInContext($actorContext, $id),
        );
    }

    public function deleteSourceProviderInstanceInContext(ActorContext $actorContext, string $id): void
    {
        $this->sourceProviderInstanceService->deleteInContext($actorContext, $id);
    }

    public function sourceProviderInstanceDependenciesInContext(
        ActorContext $actorContext,
        string $id
    ): SourceProviderInstanceDependenciesDto {
        return $this->objectMapper->map(
            $this->sourceProviderInstanceService->dependenciesInContext($actorContext, $id),
            SourceProviderInstanceDependenciesDto::class,
        );
    }

    public function deleteSourceProviderInstanceWithInactiveAuthorizationsInContext(ActorContext $actorContext, string $id): array
    {
        $sourceProviderInstanceCleanupResult = $this->sourceProviderInstanceService->deleteWithInactiveAuthorizationsInContext($actorContext, $id);

        return [
            'deletedInactiveAuthorizationsCount' => $sourceProviderInstanceCleanupResult->deletedInactiveAuthorizationsCount,
            'deletedSourceOAuthAccountsCount'    => $sourceProviderInstanceCleanupResult->deletedSourceOAuthAccountsCount,
        ];
    }

    public function sourceProviderInstanceAuthorizationUrl(
        string $organizationSlug,
        string $provider,
        string $instanceId,
        string $state,
        ?string $codeChallenge = null,
    ): string {
        return $this->sourceProviderInstanceService->authorizationUrlForInstance(
            $organizationSlug,
            $provider,
            $instanceId,
            $state,
            $codeChallenge,
        );
    }

    /**
     * @return list<ProviderAuthorizationDto>
     */
    public function providerAuthorizations(string $organizationId, ?string $provider = null): array
    {
        return array_map(
            $this->providerAuthorizationDtoMapper->map(...),
            $this->sourceQueryService->providerAuthorizations(
                $organizationId,
                $provider,
            ),
        );
    }

    public function findProviderAuthorizationInOrganization(string $id, string $organizationId): ?ProviderAuthorizationDto
    {
        $entity = $this->sourceQueryService->findProviderAuthorizationInOrganization($id, $organizationId);

        return $entity instanceof ProviderAuthorization
            ? $this->providerAuthorizationDtoMapper->map($entity)
            : null;
    }

    /**
     * @return list<ProviderResourceDto>
     */
    public function providerResources(string $provider, string $authorizationId, string $organizationId): array
    {
        return array_map(
            fn (ProviderResourceData $providerResourceData): ProviderResourceDto => $this->objectMapper->map(
                $providerResourceData,
                ProviderResourceDto::class,
            ),
            $this->sourceQueryService->providerResources(
                $provider,
                $authorizationId,
                $organizationId,
            ),
        );
    }

    /**
     * @return list<SourceProviderDto>
     */
    public function availableProviders(): array
    {
        $availabilities = $this->oAuthProviderPort->sourceConnectAvailability();

        return array_map(
            fn (ProviderDescriptor $providerDescriptor): SourceProviderDto => $this->buildProviderDto(
                $providerDescriptor,
                $availabilities[$providerDescriptor->name] ?? null,
            ),
            $this->sourceQueryService->availableProviders(),
        );
    }

    public function sourceProviderLabel(string $provider): string
    {
        return $this->sourceProviderLabelResolver->resolve($provider);
    }

    public function registerSource(SourceRegistrationRequestDto $sourceRegistrationRequestDto): SourceDto
    {
        return $this->sourceDtoMapper->map(
            $this->sourceCommandService->registerSource(
                new RegisterSourceCommand(
                    organizationId: $sourceRegistrationRequestDto->organizationId,
                    provider: $sourceRegistrationRequestDto->provider,
                    resourceId: $sourceRegistrationRequestDto->resource,
                    name: $sourceRegistrationRequestDto->resource,
                    defaultRef: $sourceRegistrationRequestDto->defaultRef,
                    visibility: $sourceRegistrationRequestDto->visibility,
                    providerAuthorizationId: $sourceRegistrationRequestDto->providerAuthorizationId,
                    settings: $sourceRegistrationRequestDto->settings,
                    keepLastReleases: $sourceRegistrationRequestDto->keepLastReleases,
                ),
            ),
        );
    }

    public function registerSourceInContext(
        ActorContext $actorContext,
        SourceRegistrationRequestDto $sourceRegistrationRequestDto
    ): SourceDto {
        return $this->sourceDtoMapper->map(
            $this->sourceCommandService->registerSourceInContext(
                $actorContext,
                new RegisterSourceCommand(
                    organizationId: $sourceRegistrationRequestDto->organizationId,
                    provider: $sourceRegistrationRequestDto->provider,
                    resourceId: $sourceRegistrationRequestDto->resource,
                    name: $sourceRegistrationRequestDto->resource,
                    defaultRef: $sourceRegistrationRequestDto->defaultRef,
                    visibility: $sourceRegistrationRequestDto->visibility,
                    providerAuthorizationId: $sourceRegistrationRequestDto->providerAuthorizationId,
                    settings: $sourceRegistrationRequestDto->settings,
                    keepLastReleases: $sourceRegistrationRequestDto->keepLastReleases,
                ),
            ),
        );
    }

    /**
     * @return list<SourceDto>
     */
    public function allSources(): array
    {
        return array_map(
            $this->sourceDtoMapper->map(...),
            $this->sourceQueryService->allSources(),
        );
    }

    /**
     * @return list<SourceDto>
     */
    public function sourcesForOrganization(string $organizationId): array
    {
        return $this->sourceDtoMapper->mapForOrganization(
            $this->sourceQueryService->sourcesForOrganization($organizationId),
            $organizationId,
        );
    }

    public function findSource(string $id): ?SourceDto
    {
        $entity = $this->sourceQueryService->findSource($id);

        return $entity instanceof Source
            ? $this->sourceDtoMapper->map($entity)
            : null;
    }

    public function rotateWebhookSecretInContext(ActorContext $actorContext, string $id): ?SourceDto
    {
        $entity = $this->sourceCommandService->rotateWebhookSecretInContext(
            $actorContext,
            $id,
        );

        return $entity instanceof Source
            ? $this->sourceDtoMapper->map($entity)
            : null;
    }

    public function updateSettingsInContext(
        ActorContext $actorContext,
        string $id,
        string $visibility,
        bool $active,
        int $keepLastReleases
    ): ?SourceDto {
        $entity = $this->sourceCommandService->updateSettingsInContext(
            $actorContext,
            $id,
            $visibility,
            $active,
            $keepLastReleases,
        );

        return $entity instanceof Source
            ? $this->sourceDtoMapper->map($entity)
            : null;
    }

    public function archiveInContext(ActorContext $actorContext, string $id): ?SourceDto
    {
        $entity = $this->sourceCommandService->archiveInContext($actorContext, $id);

        return $entity instanceof Source
            ? $this->sourceDtoMapper->map($entity)
            : null;
    }

    public function restoreInContext(ActorContext $actorContext, string $id): ?SourceDto
    {
        $entity = $this->sourceCommandService->restoreInContext($actorContext, $id);

        return $entity instanceof Source
            ? $this->sourceDtoMapper->map($entity)
            : null;
    }

    public function cancelDeletingAsInactive(string $organizationId, string $id): ?SourceDto
    {
        $entity = $this->sourceCommandService->cancelDeletingAsInactive($id, $organizationId);

        return $entity instanceof Source
            ? $this->sourceDtoMapper->map($entity)
            : null;
    }

    public function deleteSource(string $id): bool
    {
        return $this->sourceCommandService->deleteSource($id);
    }

    public function scheduleSourceDeleteInContext(ActorContext $actorContext, string $id): bool
    {
        return $this->sourceJobScheduler->scheduleSourceDeleteInContext($actorContext, $id);
    }

    public function scheduleSourceDelete(
        string $organizationId,
        string $id,
        ?string $deletionRunId = null,
        string $origin = '',
    ): bool {
        return $this->sourceJobScheduler->scheduleSourceDelete($organizationId, $id, $deletionRunId, $origin);
    }

    public function scheduleSourceDeletionRecovery(
        string $organizationId,
        string $id,
        bool $force,
        string $origin = '',
    ): ?SourceDeletionInitiationResult {
        return $this->sourceJobScheduler->scheduleSourceDeletionRecovery($organizationId, $id, $force, $origin);
    }

    public function initiateSourceDelete(
        string $organizationId,
        string $id,
        string $origin = '',
    ): SourceDeletionInitiationResult {
        return $this->sourceJobScheduler->initiateSourceDelete($organizationId, $id, $origin);
    }

    public function publishInitiationAfterAudit(string $organizationId, string $jobId): bool
    {
        return $this->sourceJobScheduler->publishInitiationAfterAudit($organizationId, $jobId);
    }

    public function revertInitiation(
        string $organizationId,
        string $id,
        bool $previousActive,
        ?string $jobId = null,
        ?string $deletionRunId = null,
    ): bool {
        return $this->sourceJobScheduler->revertInitiation($organizationId, $id, $previousActive, $jobId, $deletionRunId);
    }

    public function countForOrganization(string $organizationId): int
    {
        return $this->sourceQueryService->countForOrganization($organizationId);
    }

    public function countSources(): int
    {
        return $this->sourceQueryService->countSources();
    }

    /**
     * @return array{success: bool, reassigned?: int, error?: string, not_found?: true}
     */
    public function reassignAndDeleteProviderAuthorizationInContext(
        ActorContext $actorContext,
        string $id,
        ?string $newAuthorizationId
    ): array {
        return $this->sourceCommandService->reassignAndDeleteProviderAuthorizationInContext(
            $actorContext,
            $id,
            $newAuthorizationId,
        );
    }

    public function checkAuthorizationDependencies(string $id, string $organizationId): array
    {
        return $this->sourceQueryService->checkAuthorizationDependencies($id, $organizationId);
    }

    public function markSyncSucceeded(string $id): ?SourceDto
    {
        $entity = $this->sourceCommandService->markSyncSucceeded($id);

        return $entity instanceof Source
            ? $this->sourceDtoMapper->map($entity)
            : null;
    }

    public function markSyncFailed(string $id, string $error): ?SourceDto
    {
        $entity = $this->sourceCommandService->markSyncFailed($id, $error);

        return $entity instanceof Source
            ? $this->sourceDtoMapper->map($entity)
            : null;
    }

    public function scheduleSourceSync(string $sourceId, string $organizationId, int $priority = 0): void
    {
        $this->sourceJobScheduler->scheduleSourceSync($sourceId, $organizationId, $priority);
    }

    public function scheduleSourceSyncOnce(string $sourceId, string $organizationId, string $idempotencyKey, int $priority = 0): string
    {
        return $this->sourceJobScheduler->scheduleSourceSyncOnce($sourceId, $organizationId, $idempotencyKey, $priority);
    }

    public function tryRegisterWebhook(string $id): WebhookRegistrationResultDto
    {
        return $this->objectMapper->map(
            $this->sourceCommandService->tryRegisterWebhook($id),
            WebhookRegistrationResultDto::class,
        );
    }

    public function supportsWebhookAutoRegistration(string $provider): bool
    {
        return $this->sourceCommandService->supportsWebhookAutoRegistration($provider);
    }

    /**
     * @param null|array{provider: string, available: bool} $oAuthAvailability
     */
    private function buildProviderDto(
        ProviderDescriptor $providerDescriptor,
        ?array $oAuthAvailability
    ): SourceProviderDto {
        return new SourceProviderDto(
            key: $providerDescriptor->name,
            name: $providerDescriptor->label,
            description: '',
            capabilities: [
                'supports_authorization'   => $providerDescriptor->supportsAuthorization,
                'supports_synchronization' => $providerDescriptor->supportsSynchronization,
            ],
            oauthProvider: $oAuthAvailability['provider'] ?? null,
            oauthEnabled: $oAuthAvailability['available'] ?? false,
            oauthAvailable: $oAuthAvailability['available'] ?? false,
        );
    }

    private function mapSourceProviderInstance(SourceProviderInstance $sourceProviderInstance): SourceProviderInstanceDto
    {
        return new SourceProviderInstanceDto(
            id: $sourceProviderInstance->getIdentifier()->toString(),
            organizationId: $sourceProviderInstance->organizationId(),
            providerType: $sourceProviderInstance->providerType(),
            label: $sourceProviderInstance->label(),
            baseUrl: $sourceProviderInstance->baseUrl(),
            clientId: $sourceProviderInstance->clientId(),
            hasClientSecret: null !== $sourceProviderInstance->storedClientSecret(),
            authMode: $sourceProviderInstance->authMode(),
            status: $sourceProviderInstance->status(),
            metadata: $sourceProviderInstance->metadata(),
        );
    }
}
