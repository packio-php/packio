<?php

declare(strict_types=1);

namespace Packio\Source\Application\Command;

use Common\Infrastructure\HttpClient\OutboundUrlPolicy;
use InvalidArgumentException;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Exception\SourceProviderInstanceException;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Security\SourceProviderInstanceSecrets;
use Packio\Source\Application\Service\SourceProviderInstanceOAuthConfig;
use Packio\Source\Domain\Dto\SourceProviderInstanceCleanupResult;
use Packio\Source\Domain\Dto\SourceProviderInstanceDependencies;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceCleanupRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function array_map;
use function array_values;
use function in_array;
use function parse_url;
use function rtrim;
use function trim;

final readonly class SourceProviderInstanceService
{
    public function __construct(
        private SourceProviderInstanceRepositoryInterface $sourceProviderInstanceRepository,
        private SourceProviderInstanceSecrets $sourceProviderInstanceSecrets,
        private SourceRepositoryInterface $sourceRepository,
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private OrganizationQueryPort $organizationQueryPort,
        private OAuthProviderPort $oAuthProviderPort,
        private SourceProviderInstanceOAuthConfig $sourceProviderInstanceOAuthConfig,
        private SourceProviderInstanceCleanupRepositoryInterface $sourceProviderInstanceCleanupRepository,
        private ?LoggerInterface $logger = null,
        private OutboundUrlPolicy $outboundUrlPolicy = new OutboundUrlPolicy(),
    ) {}

    /**
     * @return list<SourceProviderInstance>
     */
    public function listForOrganization(string $organizationId, ?string $providerType = null): array
    {
        if (null !== $providerType) {
            $this->assertProviderType($providerType);
        }

        return $this->sourceProviderInstanceRepository->findByOrganization($organizationId, $providerType);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function create(
        string $organizationId,
        string $providerType,
        string $label,
        string $baseUrl,
        string $clientId,
        ?string $clientSecret,
        string $authMode = SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        ?string $createdByUserId = null,
        array $metadata = [],
    ): SourceProviderInstance {
        $this->assertProviderType($providerType);
        $this->assertAuthMode($authMode);
        $this->assertOAuthCredentials(
            $authMode,
            $clientId,
            $clientSecret,
        );

        $normalizedBaseUrl = $this->normalizeBaseUrl($baseUrl);
        $this->assertUnique($organizationId, $providerType, $normalizedBaseUrl);

        return $this->sourceProviderInstanceRepository->save(
            SourceProviderInstance::create(
                organizationId: $organizationId,
                providerType: $providerType,
                label: trim($label),
                baseUrl: $normalizedBaseUrl,
                clientId: trim($clientId),
                clientSecret: $this->sourceProviderInstanceSecrets->encryptClientSecret($clientSecret),
                authMode: $authMode,
                createdByUserId: $createdByUserId,
                metadata: $metadata,
            ),
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createInContext(
        ActorContext $actorContext,
        string $providerType,
        string $label,
        string $baseUrl,
        string $clientId,
        ?string $clientSecret,
        string $authMode = SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        array $metadata = [],
    ): SourceProviderInstance {
        $this->assertOwnerContext($actorContext);

        return $this->create(
            organizationId: $actorContext->organizationId,
            providerType: $providerType,
            label: $label,
            baseUrl: $baseUrl,
            clientId: $clientId,
            clientSecret: $clientSecret,
            authMode: $authMode,
            createdByUserId: $actorContext->actorId,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function update(
        string $organizationId,
        string $id,
        string $label,
        string $baseUrl,
        string $clientId,
        string $authMode,
        ?string $replacementClientSecret = null,
        array $metadata = [],
    ): SourceProviderInstance {
        $sourceProviderInstance = $this->requireInOrganization($id, $organizationId);
        $this->assertAuthMode($authMode);
        $this->assertOAuthCredentials(
            $authMode,
            $clientId,
            $replacementClientSecret,
            $sourceProviderInstance->storedClientSecret(),
        );

        $normalizedBaseUrl = $this->normalizeBaseUrl($baseUrl);
        $duplicate         = $this->sourceProviderInstanceRepository->findByOrganizationProviderAndBaseUrl(
            $organizationId,
            $sourceProviderInstance->providerType(),
            $normalizedBaseUrl,
        );
        if ($duplicate instanceof SourceProviderInstance && $duplicate->getIdentifier()->toString() !== $sourceProviderInstance->getIdentifier()->toString()) {
            throw SourceProviderInstanceException::duplicate();
        }

        $sourceProviderInstance->updateDetails(trim($label), $normalizedBaseUrl, trim($clientId), $authMode, $metadata);
        if (null !== $replacementClientSecret) {
            $sourceProviderInstance->replaceClientSecret(
                $this->sourceProviderInstanceSecrets->encryptClientSecret($replacementClientSecret),
            );
        }

        return $this->sourceProviderInstanceRepository->save($sourceProviderInstance);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function updateInContext(
        ActorContext $actorContext,
        string $id,
        string $label,
        string $baseUrl,
        string $clientId,
        string $authMode,
        ?string $replacementClientSecret = null,
        array $metadata = [],
    ): SourceProviderInstance {
        $this->assertOwnerContext($actorContext);

        return $this->update(
            organizationId: $actorContext->organizationId,
            id: $id,
            label: $label,
            baseUrl: $baseUrl,
            clientId: $clientId,
            authMode: $authMode,
            replacementClientSecret: $replacementClientSecret,
            metadata: $metadata,
        );
    }

    public function activateInContext(ActorContext $actorContext, string $id): SourceProviderInstance
    {
        $this->assertOwnerContext($actorContext);

        return $this->activate($actorContext->organizationId, $id);
    }

    public function deactivateInContext(ActorContext $actorContext, string $id): SourceProviderInstance
    {
        $this->assertOwnerContext($actorContext);

        return $this->deactivate($actorContext->organizationId, $id);
    }

    public function deleteInContext(ActorContext $actorContext, string $id): void
    {
        $this->assertOwnerContext($actorContext);
        $this->delete($actorContext->organizationId, $id);
    }

    public function dependenciesInContext(ActorContext $actorContext, string $id): SourceProviderInstanceDependencies
    {
        $this->assertOwnerContext($actorContext);

        return $this->dependencies($actorContext->organizationId, $id);
    }

    public function dependencies(string $organizationId, string $id): SourceProviderInstanceDependencies
    {
        $sourceProviderInstance = $this->requireInOrganization($id, $organizationId);

        $authorizations   = $this->providerAuthorizationRepository->findByProviderInstance($id, $organizationId);
        $sources          = [];
        foreach ($this->sourceRepository->findByProviderInstance($id, $organizationId) as $source) {
            $sources[$source->getIdentifier()->toString()] = $source;
        }
        $authorizationIds = array_map(
            static fn ($authorization): string => $authorization->getIdentifier()->toString(),
            $authorizations,
        );
        foreach ($this->sourceRepository->findByProviderAuthorizationIds($authorizationIds, $organizationId) as $source) {
            $sources[$source->getIdentifier()->toString()] = $source;
        }

        return new SourceProviderInstanceDependencies(
            instanceId: $id,
            label: $sourceProviderInstance->label(),
            baseUrl: $sourceProviderInstance->baseUrl(),
            providerType: $sourceProviderInstance->providerType(),
            sources: array_map(
                static fn ($source): array => [
                    'id'       => $source->getIdentifier()->toString(),
                    'name'     => $source->displayName(),
                    'url'      => $source->canonicalUrl(),
                    'provider' => $source->provider(),
                    'status'   => $source->status(),
                ],
                array_values($sources),
            ),
            authorizations: array_map(
                static fn ($authorization): array => [
                    'id'           => $authorization->getIdentifier()->toString(),
                    'accountName'  => $authorization->accountName(),
                    'provider'     => $authorization->provider(),
                    'authStrategy' => $authorization->authStrategy(),
                    'status'       => $authorization->status(),
                ],
                $authorizations,
            ),
        );
    }

    public function deleteWithInactiveAuthorizationsInContext(ActorContext $actorContext, string $id): SourceProviderInstanceCleanupResult
    {
        $this->assertOwnerContext($actorContext);

        return $this->deleteWithInactiveAuthorizations($actorContext->organizationId, $id);
    }

    public function authorizationUrlForInstance(
        string $organizationSlug,
        string $provider,
        string $instanceId,
        string $state,
        ?string $codeChallenge = null,
    ): string {
        $organizationId = $this->resolveOrganizationId($organizationSlug);
        $instance       = $this->sourceProviderInstanceRepository->findActiveByIdInOrganization($instanceId, $organizationId);

        if (! $instance instanceof SourceProviderInstance) {
            throw SourceProviderInstanceException::notFound();
        }

        if ($instance->providerType() !== $provider || ! $instance->supportsOAuth()) {
            throw SourceProviderInstanceException::unsupportedProviderType($provider);
        }

        $config = $this->sourceProviderInstanceOAuthConfig->forInstance($instance);
        if (null === $config) {
            throw new RuntimeException('OAuth client secret is required for source provider instance OAuth.');
        }

        return $this->oAuthProviderPort->getAuthorizationUrlForProviderInstance(
            $provider,
            $config,
            $state,
            $this->sourceProviderInstanceOAuthConfig->scopes($instance),
            $codeChallenge,
        );
    }

    public function activate(string $organizationId, string $id): SourceProviderInstance
    {
        $sourceProviderInstance = $this->requireInOrganization($id, $organizationId);
        $sourceProviderInstance->activate();

        return $this->sourceProviderInstanceRepository->save($sourceProviderInstance);
    }

    public function deactivate(string $organizationId, string $id): SourceProviderInstance
    {
        $sourceProviderInstance = $this->requireInOrganization($id, $organizationId);
        $sourceProviderInstance->deactivate();

        return $this->sourceProviderInstanceRepository->save($sourceProviderInstance);
    }

    public function delete(string $organizationId, string $id): void
    {
        $this->sourceProviderInstanceCleanupRepository->deleteStrict($organizationId, $id);
    }

    public function deleteWithInactiveAuthorizations(string $organizationId, string $id): SourceProviderInstanceCleanupResult
    {
        $dependencies = $this->dependencies($organizationId, $id);
        if (0 < $dependencies->sourceCount() || 0 < $dependencies->activeAuthorizationCount() || $dependencies->authorizationCount() !== $dependencies->inactiveAuthorizationCount()) {
            $this->logCleanupConflict($organizationId, $id, $dependencies);

            throw SourceProviderInstanceException::inUse();
        }

        try {
            $result = $this->sourceProviderInstanceCleanupRepository->deleteWithInactiveAuthorizations($organizationId, $id);
        } catch (SourceProviderInstanceException $exception) {
            if ($exception->isInUse()) {
                $this->logCleanupConflict($organizationId, $id, $this->dependencies($organizationId, $id));
            }

            throw $exception;
        }

        $this->logger?->info('Source provider instance and inactive authorizations deleted.', [
            'organization_id'                       => $organizationId,
            'instance_id'                           => $id,
            'deleted_inactive_authorizations_count' => $result->deletedInactiveAuthorizationsCount,
            'deleted_source_oauth_accounts_count'   => $result->deletedSourceOAuthAccountsCount,
        ]);

        return $result;
    }

    public function requireInOrganization(string $id, string $organizationId): SourceProviderInstance
    {
        $instance = $this->sourceProviderInstanceRepository->findByIdInOrganization($id, $organizationId);
        if (! $instance instanceof SourceProviderInstance) {
            throw SourceProviderInstanceException::notFound();
        }

        return $instance;
    }

    public function normalizeBaseUrl(string $baseUrl): string
    {
        $url      = rtrim(trim($baseUrl), '/');

        try {
            $target = $this->outboundUrlPolicy->parse($url);
        } catch (InvalidArgumentException) {
            throw SourceProviderInstanceException::invalidBaseUrl();
        }
        $parts = parse_url($url);
        if (isset($parts['query']) || (isset($parts['path']) && '' !== $parts['path'])) {
            throw SourceProviderInstanceException::invalidBaseUrl();
        }

        return $target['origin'];
    }

    private function logCleanupConflict(
        string $organizationId,
        string $id,
        SourceProviderInstanceDependencies $sourceProviderInstanceDependencies,
    ): void {
        $this->logger?->warning('Source provider instance cleanup rejected due to dependencies.', [
            'organization_id'              => $organizationId,
            'instance_id'                  => $id,
            'source_count'                 => $sourceProviderInstanceDependencies->sourceCount(),
            'active_authorization_count'   => $sourceProviderInstanceDependencies->activeAuthorizationCount(),
            'inactive_authorization_count' => $sourceProviderInstanceDependencies->inactiveAuthorizationCount(),
        ]);
    }

    private function assertProviderType(string $providerType): void
    {
        if (! in_array($providerType, SourceProviderInstance::SUPPORTED_PROVIDER_TYPES, true)) {
            throw SourceProviderInstanceException::unsupportedProviderType($providerType);
        }
    }

    private function assertAuthMode(string $authMode): void
    {
        if (! in_array($authMode, SourceProviderInstance::SUPPORTED_AUTH_MODES, true)) {
            throw SourceProviderInstanceException::unsupportedAuthMode($authMode);
        }
    }

    private function assertOAuthCredentials(
        string $authMode,
        string $clientId,
        ?string $clientSecret,
        ?string $storedClientSecret = null
    ): void {
        if (SourceProviderInstance::AUTH_MODE_TOKEN === $authMode) {
            return;
        }

        if ('' === trim($clientId)) {
            throw SourceProviderInstanceException::oauthClientCredentialsRequired();
        }

        if (null !== $clientSecret && '' !== trim($clientSecret)) {
            return;
        }

        if (null !== $storedClientSecret && '' !== trim($storedClientSecret)) {
            return;
        }

        throw SourceProviderInstanceException::oauthClientCredentialsRequired();
    }

    private function assertUnique(string $organizationId, string $providerType, string $baseUrl): void
    {
        if ($this->sourceProviderInstanceRepository->findByOrganizationProviderAndBaseUrl($organizationId, $providerType, $baseUrl) instanceof SourceProviderInstance) {
            throw SourceProviderInstanceException::duplicate();
        }
    }

    private function assertOwnerContext(ActorContext $actorContext): void
    {
        if ('' === trim($actorContext->organizationId) || '' === trim($actorContext->actorId) || ! $actorContext->isOwner()) {
            throw SourceProviderInstanceException::forbidden();
        }
    }

    private function resolveOrganizationId(string $slug): string
    {
        if ('' === trim($slug)) {
            throw new RuntimeException('Organization is required for source provider instance OAuth.');
        }

        $organizationId = $this->organizationQueryPort->resolveOrganizationId($slug);
        if (null === $organizationId || '' === $organizationId) {
            throw new RuntimeException('Organization not found for source provider instance OAuth.');
        }

        return $organizationId;
    }
}
