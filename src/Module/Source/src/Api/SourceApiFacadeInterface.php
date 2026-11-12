<?php

declare(strict_types=1);

namespace Packio\Source\Api;

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

interface SourceApiFacadeInterface
{
    public function countSources(): int;

    public function authorizeProvider(ProviderAuthorizationRequestDto $providerAuthorizationRequestDto): ProviderAuthorizationDto;

    public function authorizeProviderInContext(
        ActorContext $actorContext,
        ProviderAuthorizationRequestDto $providerAuthorizationRequestDto
    ): ProviderAuthorizationDto;

    /** @param array<string, string> $authorizationPayload */
    public function resolveConnectAuthorizationInContext(
        ActorContext $actorContext,
        string $provider,
        string $authorizationId,
        array $authorizationPayload,
        string $providerInstanceId,
    ): string;

    public function updateSourceSettings(
        ActorContext $actorContext,
        string $sourceId,
        SourceSettingsPatch $sourceSettingsPatch,
    ): SourceSettingsResultDto;

    public function authorizeProviderFromSourceOAuth(
        ActorContext $actorContext,
        string $currentUserId,
        string $provider,
        string $sourceOAuthAccountId,
    ): ProviderAuthorizationDto;

    public function handleSourceOAuthCallback(
        string $provider,
        string $code,
        string $userId,
        string $organizationSlug,
        ?string $codeVerifier = null,
    ): SourceOAuthAccountDto;

    public function handleSourceProviderInstanceOAuthCallback(
        string $provider,
        string $code,
        string $userId,
        string $organizationSlug,
        string $providerInstanceId,
        ?string $codeVerifier = null,
    ): SourceOAuthAccountDto;

    /**
     * @return list<SourceProviderInstanceDto>
     */
    public function sourceProviderInstances(string $organizationId, ?string $providerType = null): array;

    public function createSourceProviderInstanceInContext(
        ActorContext $actorContext,
        SourceProviderInstanceRequestDto $sourceProviderInstanceRequestDto
    ): SourceProviderInstanceDto;

    public function updateSourceProviderInstanceInContext(
        ActorContext $actorContext,
        string $id,
        SourceProviderInstanceRequestDto $sourceProviderInstanceRequestDto
    ): SourceProviderInstanceDto;

    public function activateSourceProviderInstanceInContext(ActorContext $actorContext, string $id): SourceProviderInstanceDto;

    public function deactivateSourceProviderInstanceInContext(ActorContext $actorContext, string $id): SourceProviderInstanceDto;

    public function deleteSourceProviderInstanceInContext(ActorContext $actorContext, string $id): void;

    public function sourceProviderInstanceDependenciesInContext(
        ActorContext $actorContext,
        string $id
    ): SourceProviderInstanceDependenciesDto;

    /**
     * @return array{deletedInactiveAuthorizationsCount: int, deletedSourceOAuthAccountsCount: int}
     */
    public function deleteSourceProviderInstanceWithInactiveAuthorizationsInContext(ActorContext $actorContext, string $id): array;

    public function sourceProviderInstanceAuthorizationUrl(
        string $organizationSlug,
        string $provider,
        string $instanceId,
        string $state,
        ?string $codeChallenge = null,
    ): string;

    /**
     * @return list<ProviderAuthorizationDto>
     */
    public function providerAuthorizations(string $organizationId, ?string $provider = null): array;

    public function findProviderAuthorizationInOrganization(string $id, string $organizationId): ?ProviderAuthorizationDto;

    /**
     * @return list<ProviderResourceDto>
     */
    public function providerResources(string $provider, string $authorizationId, string $organizationId): array;

    /**
     * @return list<SourceProviderDto>
     */
    public function availableProviders(): array;

    public function sourceProviderLabel(string $provider): string;

    public function registerSource(SourceRegistrationRequestDto $sourceRegistrationRequestDto): SourceDto;

    public function registerSourceInContext(
        ActorContext $actorContext,
        SourceRegistrationRequestDto $sourceRegistrationRequestDto
    ): SourceDto;

    public function findSource(string $id): ?SourceDto;

    public function rotateWebhookSecretInContext(ActorContext $actorContext, string $id): ?SourceDto;

    public function updateSettingsInContext(
        ActorContext $actorContext,
        string $id,
        string $visibility,
        bool $active,
        int $keepLastReleases
    ): ?SourceDto;

    public function archiveInContext(ActorContext $actorContext, string $id): ?SourceDto;

    public function restoreInContext(ActorContext $actorContext, string $id): ?SourceDto;

    public function cancelDeletingAsInactive(string $organizationId, string $id): ?SourceDto;

    public function deleteSource(string $id): bool;

    public function scheduleSourceDeleteInContext(ActorContext $actorContext, string $id): bool;

    public function scheduleSourceDelete(
        string $organizationId,
        string $id,
        ?string $deletionRunId = null,
        string $origin = '',
    ): bool;

    /** Schedules recovery for the current or atomically re-armed source lifecycle. */
    public function scheduleSourceDeletionRecovery(
        string $organizationId,
        string $id,
        bool $force,
        string $origin = '',
    ): ?SourceDeletionInitiationResult;

    /**
     * Atomically initiates staged source deletion. The status transition lives
     * in the owning persistence boundary (row locked for update), so
     * concurrent or repeated initiations produce exactly one `Initiated`
     * outcome. The persistence transition assigns a new deletion-run ID; its
     * cleanup job uses that ID in the idempotency key and remains held until
     * the caller publishes it after any required audit write succeeds.
     */
    public function initiateSourceDelete(
        string $organizationId,
        string $id,
        string $origin = '',
    ): SourceDeletionInitiationResult;

    /** Advances a lifecycle only after its durable audit operation exists. */
    public function publishInitiationAfterAudit(string $organizationId, string $jobId): bool;

    /**
     * Compensating revert of a successful initiation. It restores the source
     * only if the exact held lifecycle job was canceled successfully.
     */
    public function revertInitiation(
        string $organizationId,
        string $id,
        bool $previousActive,
        ?string $jobId = null,
        ?string $deletionRunId = null,
    ): bool;

    /**
     * @return list<SourceDto>
     */
    public function allSources(): array;

    /**
     * @return list<SourceDto>
     */
    public function sourcesForOrganization(string $organizationId): array;

    /**
     * Counts sources of one organization without hydrating entities or
     * resolving credential-derived data.
     */
    public function countForOrganization(string $organizationId): int;

    public function markSyncSucceeded(string $id): ?SourceDto;

    public function markSyncFailed(string $id, string $error): ?SourceDto;

    /**
     * @return array{success: bool, reassigned?: int, error?: string, not_found?: true}
     */
    public function reassignAndDeleteProviderAuthorizationInContext(
        ActorContext $actorContext,
        string $id,
        ?string $newAuthorizationId
    ): array;

    /**
     * @return array{
     *     has_sources: bool,
     *     source_count: int,
     *     sources: list<array{id: string, name: string, has_packages: bool}>,
     *     other_authorizations: list<array{id: string, provider: string, auth_strategy: string, masked_value: string}>
     * }
     */
    public function checkAuthorizationDependencies(string $id, string $organizationId): array;

    public function scheduleSourceSync(string $sourceId, string $organizationId, int $priority = 0): void;

    public function scheduleSourceSyncOnce(string $sourceId, string $organizationId, string $idempotencyKey, int $priority = 0): string;

    /**
     * Best-effort: attempt to register a webhook at the upstream provider.
     * Never throws — failures are captured in the returned DTO.
     */
    public function tryRegisterWebhook(string $id): WebhookRegistrationResultDto;

    /**
     * Returns true if the given provider supports automatic webhook registration.
     */
    public function supportsWebhookAutoRegistration(string $provider): bool;
}
