<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Page;

use Common\Branding\BrandingConfig;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceProviderDto;
use Packio\Source\Api\Dto\SourceProviderInstanceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Source\Application\Provider\SourceProviderLabelResolver;
use Packio\Webhook\Api\WebhookApiFacadeInterface;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function implode;
use function in_array;
use function max;
use function min;
use function sprintf;
use function str_contains;
use function strcmp;
use function strtolower;
use function usort;

final readonly class SourcesPageDataBuilder
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceSecretResolverInterface $sourceSecretResolver,
        private WebhookApiFacadeInterface $webhookApiFacade,
        private OrganizationPolicy $organizationPolicy,
        private BrandingConfig $brandingConfig,
        private SourceProviderLabelResolver $sourceProviderLabelResolver,
        private OAuthApiFacadeInterface $oauthApiFacade,
    ) {}

    /**
     * @return array{
     *     can_manage_sources: bool,
     *     providers: list<array{key: string, name: string, description: string, capabilities: array<string, bool>, oauth_provider: null|string, oauth_enabled: bool, oauth_available: bool}>,
     *     source_provider_instances: list<array<string, mixed>>,
     *     provider_authorizations: list<array<string, mixed>>,
     *     provider_resources: array<string, list<array<string, mixed>>>,
     *     sources: list<array<string, mixed>>,
     *     source_stats: array{total: int, active: int, archived: int},
     *     source_filter_options: array{providers: list<array{value: string, label: string}>, sync_statuses: list<array{value: string, label: string}>},
     *     filters: array<string, mixed>,
     *     source_urls: list<string>,
     *     pagination: array<string, mixed>
     * }
     */
    public function build(
        OrganizationDto $organizationDto,
        ?OrganizationMemberDto $organizationMemberDto,
        ?PaginationCriteria $paginationCriteria = null,
        ?SourceIndexFilterCriteria $sourceIndexFilterCriteria = null,
    ): array {
        $paginationCriteria ??= new PaginationCriteria();
        $sourceIndexFilterCriteria ??= new SourceIndexFilterCriteria();

        $baseUrl = $this->brandingConfig->appBaseUrl();

        $sources = array_values(
            array_filter(
                $this->sourceApiFacade->sourcesForOrganization($organizationDto->id),
                static fn (SourceDto $sourceDto): bool => 'deleting' !== $sourceDto->status,
            ),
        );

        $sourceProviderInstances          = $this->sourceApiFacade->sourceProviderInstances($organizationDto->id);
        $hasGitLabSelfHostedOAuthInstance = [] !== array_filter(
            $sourceProviderInstances,
            static fn (SourceProviderInstanceDto $sourceProviderInstanceDto): bool => 'gitlab_self_hosted' === $sourceProviderInstanceDto->providerType
                && 'active' === $sourceProviderInstanceDto->status
                && ('oauth2' === $sourceProviderInstanceDto->authMode || 'oauth2_and_token' === $sourceProviderInstanceDto->authMode)
                && '' !== $sourceProviderInstanceDto->clientId
                && $sourceProviderInstanceDto->hasClientSecret,
        ) && $this->oauthApiFacade->gitLabSelfHostedSourceConnectEnabled();

        $oauthAvailabilities = [];
        foreach ($this->oauthApiFacade->providerAvailabilities() as $oAuthProviderAvailability) {
            if (null !== $oAuthProviderAvailability->sourceProvider) {
                $oauthAvailabilities[$oAuthProviderAvailability->sourceProvider] ??= $oAuthProviderAvailability;
            }
        }

        $providers = array_map(
            static function(SourceProviderDto $sourceProviderDto) use ($hasGitLabSelfHostedOAuthInstance, $oauthAvailabilities): array {
                $isGitLabSelfHosted   = 'gitlab_self_hosted' === $sourceProviderDto->key;
                $sourceOAuthAvailable = null !== $sourceProviderDto->oauthProvider
                    && ($oauthAvailabilities[$sourceProviderDto->key] ?? null)?->isSourceConnectAvailable() === true;

                return [
                    'key'             => $sourceProviderDto->key,
                    'name'            => $sourceProviderDto->name,
                    'description'     => $sourceProviderDto->description,
                    'capabilities'    => $sourceProviderDto->capabilities,
                    'oauth_provider'  => $isGitLabSelfHosted && $hasGitLabSelfHostedOAuthInstance
                        ? 'gitlab_self_hosted'
                        : $sourceProviderDto->oauthProvider,
                    'oauth_enabled'   => $isGitLabSelfHosted
                        ? $hasGitLabSelfHostedOAuthInstance
                        : $sourceOAuthAvailable,
                    'oauth_available' => $isGitLabSelfHosted
                        ? $hasGitLabSelfHostedOAuthInstance
                        : $sourceOAuthAvailable,
                ];
            },
            $this->sourceApiFacade->availableProviders(),
        );

        $providerAuthorizations = $this->sourceApiFacade->providerAuthorizations(
            $organizationDto->id,
        );

        $canManageSources = $this->organizationPolicy->canManage(
            $organizationDto,
            $organizationMemberDto,
        );

        $activeSources = array_values(
            array_filter(
                $sources,
                static fn (SourceDto $sourceDto): bool => ! $sourceDto->archived,
            ),
        );
        $archivedSources = array_values(
            array_filter(
                $sources,
                static fn (SourceDto $sourceDto): bool => $sourceDto->archived,
            ),
        );
        $filteredSources    = $this->filterSources($sources, $sourceIndexFilterCriteria);
        $paginatedResultDto = $this->paginate($filteredSources, $paginationCriteria);
        $pagination         = $paginatedResultDto->toArray();
        unset($pagination['items']);

        return [
            'can_manage_sources'        => $canManageSources,
            'providers'                 => $providers,
            'source_provider_instances' => array_map(
                static fn (SourceProviderInstanceDto $sourceProviderInstanceDto): array => [
                    'id'            => $sourceProviderInstanceDto->id,
                    'provider_type' => $sourceProviderInstanceDto->providerType,
                    'label'         => $sourceProviderInstanceDto->label,
                    'base_url'      => $sourceProviderInstanceDto->baseUrl,
                    'auth_mode'     => $sourceProviderInstanceDto->authMode,
                    'status'        => $sourceProviderInstanceDto->status,
                ],
                $sourceProviderInstances,
            ),
            'provider_authorizations'   => array_map(
                static fn (ProviderAuthorizationDto $providerAuthorizationDto): array => [
                    'id'                      => $providerAuthorizationDto->id,
                    'provider'                => $providerAuthorizationDto->provider,
                    'auth_strategy'           => $providerAuthorizationDto->authStrategy,
                    'status'                  => $providerAuthorizationDto->status,
                    'account_name'            => $providerAuthorizationDto->accountName,
                    'configured'              => $providerAuthorizationDto->configured,
                    'masked_value'            => $canManageSources ? $providerAuthorizationDto->maskedValue : '',
                    'source_oauth_account_id' => $providerAuthorizationDto->sourceOAuthAccountId,
                    'provider_instance_id'    => $providerAuthorizationDto->providerInstanceId,
                    'auth_health'             => $providerAuthorizationDto->authHealth,
                    'auth_failure_reason'     => $providerAuthorizationDto->authFailureReason,
                    'last_token_refresh_at'   => $providerAuthorizationDto->lastTokenRefreshAt,
                ],
                $providerAuthorizations,
            ),
            'provider_resources'        => [],
            'sources'                   => $this->mapSources(
                array_values($paginatedResultDto->items),
                $organizationDto->id,
                $baseUrl,
                $canManageSources,
            ),
            'source_stats'              => [
                'total'    => count($sources),
                'active'   => count($activeSources),
                'archived' => count($archivedSources),
            ],
            'source_urls'               => array_map(
                static fn (SourceDto $sourceDto): string => $sourceDto->url,
                $sources,
            ),
            'source_filter_options'     => $this->sourceFilterOptions($sources),
            'filters'                   => [
                ...$sourceIndexFilterCriteria->toQueryArray(),
                'page'     => $paginatedResultDto->currentPage,
                'per_page' => $paginatedResultDto->perPage,
            ],
            'pagination'                => $pagination,
        ];
    }

    /**
     * @param list<SourceDto> $items
     *
     * @return PaginatedResultDto<SourceDto>
     */
    private function paginate(array $items, PaginationCriteria $paginationCriteria): PaginatedResultDto
    {
        $total       = count($items);
        $perPage     = $paginationCriteria->perPage;
        $lastPage    = max(1, (int) ceil($total / $perPage));
        $currentPage = min($paginationCriteria->page, $lastPage);

        return new PaginatedResultDto(
            array_slice($items, ($currentPage - 1) * $perPage, $perPage),
            $total,
            $currentPage,
            $perPage,
        );
    }

    /**
     * @param list<SourceDto> $sources
     *
     * @return list<SourceDto>
     */
    private function filterSources(array $sources, SourceIndexFilterCriteria $sourceIndexFilterCriteria): array
    {
        return array_values(array_filter(
            $sources,
            fn (SourceDto $sourceDto): bool => $this->matchesFilters($sourceDto, $sourceIndexFilterCriteria),
        ));
    }

    private function matchesFilters(SourceDto $sourceDto, SourceIndexFilterCriteria $sourceIndexFilterCriteria): bool
    {
        if (
            null !== $sourceIndexFilterCriteria->search
            && ! str_contains($this->sourceSearchHaystack($sourceDto), strtolower($sourceIndexFilterCriteria->search))
        ) {
            return false;
        }

        if ([] !== $sourceIndexFilterCriteria->providers && ! in_array($sourceDto->provider, $sourceIndexFilterCriteria->providers, true)) {
            return false;
        }

        if ([] !== $sourceIndexFilterCriteria->visibilities && ! in_array($sourceDto->visibility, $sourceIndexFilterCriteria->visibilities, true)) {
            return false;
        }

        if ([] !== $sourceIndexFilterCriteria->statuses && ! in_array($this->logicalSourceStatus($sourceDto), $sourceIndexFilterCriteria->statuses, true)) {
            return false;
        }

        return [] === $sourceIndexFilterCriteria->syncStatuses || in_array($sourceDto->lastSyncStatus, $sourceIndexFilterCriteria->syncStatuses, true);
    }

    private function sourceSearchHaystack(SourceDto $sourceDto): string
    {
        return strtolower(implode(' ', [
            $sourceDto->displayName,
            $sourceDto->owner . '/' . $sourceDto->repository,
            $sourceDto->url,
            $sourceDto->canonicalUrl,
            $sourceDto->provider,
            $this->sourceProviderLabelResolver->resolve($sourceDto->provider),
            $sourceDto->defaultRef,
        ]));
    }

    private function logicalSourceStatus(SourceDto $sourceDto): string
    {
        if ($sourceDto->archived) {
            return 'archived';
        }

        return $sourceDto->active ? 'active' : 'inactive';
    }

    /**
     * @param list<SourceDto> $sources
     *
     * @return array{providers: list<array{value: string, label: string}>, sync_statuses: list<array{value: string, label: string}>}
     */
    private function sourceFilterOptions(array $sources): array
    {
        $providers    = [];
        $syncStatuses = [];

        foreach ($sources as $source) {
            if ('' !== $source->provider) {
                $providers[$source->provider] = [
                    'value' => $source->provider,
                    'label' => $this->sourceProviderLabelResolver->resolve($source->provider),
                ];
            }

            if ('' !== $source->lastSyncStatus) {
                $syncStatuses[$source->lastSyncStatus] = [
                    'value' => $source->lastSyncStatus,
                    'label' => $source->lastSyncStatus,
                ];
            }
        }

        $providerOptions = array_values($providers);
        usort(
            $providerOptions,
            static fn (array $left, array $right): int => strcmp($left['label'], $right['label'])
                ?: strcmp($left['value'], $right['value']),
        );

        $syncStatusOptions = array_values($syncStatuses);
        usort(
            $syncStatusOptions,
            static fn (array $left, array $right): int => strcmp($left['value'], $right['value']),
        );

        return [
            'providers'     => $providerOptions,
            'sync_statuses' => $syncStatusOptions,
        ];
    }

    /**
     * @param list<SourceDto> $sources
     *
     * @return list<array<string, mixed>>
     */
    private function mapSources(array $sources, string $organizationId, string $baseUrl, bool $includeWebhookSecrets): array
    {
        $webhookEvents = $this->webhookApiFacade->recentEventsForSources(
            array_map(static fn (SourceDto $sourceDto): string => $sourceDto->id, $sources),
            3,
        );

        return array_map(
            function(SourceDto $sourceDto) use ($organizationId, $baseUrl, $includeWebhookSecrets, $webhookEvents): array {
                $item = [
                    'id'                      => $sourceDto->id,
                    'provider'                => $sourceDto->provider,
                    'provider_label'          => $this->sourceProviderLabelResolver->resolve(
                        $sourceDto->provider,
                    ),
                    'name'                    => '' !== $sourceDto->displayName
                        ? $sourceDto->displayName
                        : $sourceDto->owner . '/' . $sourceDto->repository,
                    'url'                     => $sourceDto->url,
                    'default_ref'             => $sourceDto->defaultRef,
                    'visibility'              => $sourceDto->visibility,
                    'keep_last_releases'      => $sourceDto->keepLastReleases,
                    'active'                  => $sourceDto->active,
                    'status'                  => $sourceDto->status,
                    'archived'                => $sourceDto->archived,
                    'archived_at'             => '' !== $sourceDto->archivedAt
                        ? $sourceDto->archivedAt
                        : null,
                    'last_sync_status'        => $sourceDto->lastSyncStatus,
                    'last_successful_sync_at' => $sourceDto->lastSuccessfulSyncAt,
                    'last_failed_sync_at'     => $sourceDto->lastFailedSyncAt,
                    'last_sync_error'         => $sourceDto->lastSyncError,
                    'webhook_url'             => sprintf(
                        '%s/webhooks/%s/%s',
                        $baseUrl,
                        $sourceDto->provider,
                        $sourceDto->id,
                    ),
                    'webhook_events'          => array_map(
                        static fn ($event): array => [
                            'delivery_id'      => $event->deliveryId,
                            'provider'         => $event->provider,
                            'event'            => $event->event,
                            'status'           => $event->status,
                            'message'          => $event->message,
                            'created_at'       => $event->createdAt,
                            'processed_at'     => $event->processedAt,
                            'scheduled_job_id' => $event->scheduledJobId,
                            'reprocess_count'  => $event->reprocessCount,
                        ],
                        $webhookEvents[$sourceDto->id] ?? [],
                    ),
                ];

                if ($includeWebhookSecrets) {
                    $item['webhook_secret'] = $this->sourceSecretResolver->resolveWebhookSecret(
                        $sourceDto->id,
                        $organizationId,
                    ) ?? '';
                }

                return $item;
            },
            $sources,
        );
    }
}
