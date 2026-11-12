<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Page;

use Common\Branding\BrandingConfig;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Source\Application\Provider\SourceProviderLabelResolver;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Webhook\Api\WebhookApiFacadeInterface;

use function array_filter;
use function array_map;
use function array_reduce;
use function array_sum;
use function array_values;
use function count;
use function is_string;
use function sprintf;
use function strlen;
use function substr;

final readonly class SourceDetailsPageDataBuilder
{
    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceSecretResolverInterface $sourceSecretResolver,
        private WebhookApiFacadeInterface $webhookApiFacade,
        private AuditApiFacadeInterface $auditApiFacade,
        private OrganizationPolicy $organizationPolicy,
        private BrandingConfig $brandingConfig,
        private SourceProviderLabelResolver $sourceProviderLabelResolver,
    ) {}

    /**
     * @return array{
     *     can_manage_sources: bool,
     *     source: array<string, mixed>,
     *     stats: array{packages_count: int, releases_count: int, deliveries_count: int},
     *     latest_package: null|array<string, mixed>,
     *     packages: list<array<string, mixed>>,
     *     webhook_events: list<array<string, mixed>>,
     *     audit: list<array{id: string, event: string, occurred_at: string}>
     * }
     */
    public function build(OrganizationDto $organizationDto, ?OrganizationMemberDto $organizationMemberDto, SourceDto $sourceDto): array
    {
        $baseUrl = $this->brandingConfig->appBaseUrl();

        $packages = array_values(
            array_filter(
                $this->packageApiFacade->packagesForOrganization($organizationDto->id),
                static fn ($package): bool => $package->sourceId === $sourceDto->id,
            ),
        );

        $packagePayload = array_map(function($package) use ($organizationDto): array {
            $releases = $this->releaseApiFacade->releasesForPackage(
                $package->name,
                false,
                $organizationDto->id,
            );

            return [
                'name'           => $package->name,
                'description'    => $package->description,
                'visibility'     => $package->visibility,
                'active'         => $package->active,
                'updated_at'     => $package->updatedAt,
                'release_count'  => count($releases),
                'latest_version' => $releases[0]->version ?? null,
                'releases'       => array_map(
                    static fn ($release): array => [
                        'version'    => $release->version,
                        'reference'  => $release->reference,
                        'created_at' => $release->createdAt,
                    ],
                    $releases,
                ),
            ];
        }, $packages);

        $audit = $this->auditApiFacade->recentBySource(
            $organizationDto->id,
            $sourceDto->id,
            5,
        );

        $webhookEvents = array_map(
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
            $this->webhookApiFacade->recentEvents($sourceDto->id, 5),
        );

        $latestPackage = array_reduce(
            $packagePayload,
            static function(?array $carry, array $package): array {
                if (null === $carry) {
                    return $package;
                }

                return $package['updated_at'] > $carry['updated_at']
                    ? $package
                    : $carry;
            },
        );

        $credentialsStatusLabel = match (true) {
            ! $sourceDto->credentialsConfigured     => 'Not configured',
            'github_token' === $sourceDto->authType => 'GitHub authorization configured',
            'gitlab_token' === $sourceDto->authType => 'GitLab authorization configured',
            default                                 => 'Provider authorization configured',
        };

        $sourceName = '' !== $sourceDto->displayName
            ? $sourceDto->displayName
            : $sourceDto->owner . '/' . $sourceDto->repository;

        $canManageSources = $this->organizationPolicy->canManage(
            $organizationDto,
            $organizationMemberDto,
        );

        $webhookSecret = $canManageSources
            ? $this->sourceSecretResolver->resolveWebhookSecret(
                $sourceDto->id,
                $organizationDto->id,
            )
            : null;

        $webhookExternalId = $canManageSources
            ? ($sourceDto->providerConfig['webhook_external_id'] ?? null)
            : null;

        $providerAuthorization = $this->resolveProviderAuthorization(
            $organizationDto->id,
            $sourceDto->providerAuthorizationId,
        );

        $supportsAutoRegistration = $canManageSources
            && $this->sourceApiFacade->supportsWebhookAutoRegistration($sourceDto->provider);

        $webhookRegistered = null !== $webhookExternalId && '' !== $webhookExternalId;

        return [
            'can_manage_sources' => $canManageSources,
            'source'             => [
                'id'                      => $sourceDto->id,
                'provider'                => $sourceDto->provider,
                'provider_label'          => $this->sourceProviderLabelResolver->resolve(
                    $sourceDto->provider,
                ),
                'name'                    => $sourceName,
                'type'                    => $sourceDto->type,
                'url'                     => $sourceDto->url,
                'default_ref'             => $sourceDto->defaultRef,
                'visibility'              => $sourceDto->visibility,
                'keep_last_releases'      => $sourceDto->keepLastReleases,
                'active'                  => $sourceDto->active,
                'status'                  => $sourceDto->status,
                'archived'                => $sourceDto->archived,
                'archived_at'             => $sourceDto->archivedAt,
                'packages_active'         => 0 === count($packagePayload)
                    ? true
                    : count(
                        array_filter(
                            $packagePayload,
                            static fn (array $package): bool => $package['active'],
                        ),
                    ) > 0,
                'created_at'              => $sourceDto->createdAt,
                ...($canManageSources
                    ? [
                        'webhook_secret'             => $webhookSecret ?? '',
                        'webhook_external_id'        => $webhookExternalId,
                        'webhook_registered'         => $webhookRegistered,
                        'supports_auto_registration' => $supportsAutoRegistration,
                    ]
                    : []),
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
                'credentials'             => [
                    'configured'              => $sourceDto->credentialsConfigured,
                    'auth_type'               => $sourceDto->authType,
                    'status_label'            => $credentialsStatusLabel,
                    'masked_value'            => match (true) {
                        ! $canManageSources                => '',
                        $sourceDto->credentialsConfigured  => $this->resolveMaskedValue($sourceDto->authType, $sourceDto->providerAuthorizationId),
                        default                            => null,
                    },
                    'source_oauth_account_id' => $providerAuthorization?->sourceOAuthAccountId,
                    'auth_health'             => $providerAuthorization?->authHealth,
                    'auth_failure_reason'     => $providerAuthorization?->authFailureReason,
                    'last_token_refresh_at'   => $providerAuthorization?->lastTokenRefreshAt,
                ],
            ],
            'stats'              => [
                'packages_count'   => count($packagePayload),
                'releases_count'   => array_sum(
                    array_map(
                        static fn (array $package): int => $package['release_count'],
                        $packagePayload,
                    ),
                ),
                'deliveries_count' => count($webhookEvents),
            ],
            'latest_package'     => $latestPackage,
            'packages'           => $packagePayload,
            'webhook_events'     => $webhookEvents,
            'audit'              => array_map(
                static fn ($entry): array => [
                    'id'          => $entry->id,
                    'event'       => $entry->event,
                    'occurred_at' => $entry->occurredAt,
                ],
                $audit,
            ),
        ];
    }

    private function resolveMaskedValue(string $authType, string $providerAuthorizationId): string
    {
        if ('' === $providerAuthorizationId) {
            return sprintf('%s ••••••••', $authType);
        }

        try {
            $token = $this->sourceSecretResolver->resolveAuthorizationSecret($providerAuthorizationId);
        } catch (
            SourceTokenMissingException|SourceTokenRefreshFailedException|SourceTokenRefreshUnavailableException
        ) {
            $token = null;
        }

        if (is_string($token) && strlen($token) >= 4) {
            return sprintf('%s ****%s', $authType, substr($token, -4));
        }

        return sprintf('%s ••••••••', $authType);
    }

    private function resolveProviderAuthorization(string $organizationId, string $providerAuthorizationId): ?ProviderAuthorizationDto
    {
        if ('' === $providerAuthorizationId) {
            return null;
        }

        return $this->sourceApiFacade->findProviderAuthorizationInOrganization($providerAuthorizationId, $organizationId);
    }
}
