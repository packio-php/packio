<?php

declare(strict_types=1);

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\OutboundUrlPolicy;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\SecretEncryptorInterface;
use Common\Security\StoredSecretResolver;
use Cycle\ORM\ORMInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Publication\Api\PublicationApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceMaintenanceService;
use Packio\Source\Application\Command\SourceProviderInstanceService;
use Packio\Source\Application\Port\AuditRecorderPort;
use Packio\Source\Application\Port\JobSchedulerPort;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationMembershipPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Port\PackageVisibilityPort;
use Packio\Source\Application\Port\PublicationSyncPort;
use Packio\Source\Application\Port\SourceDeleteArtifactsPort;
use Packio\Source\Application\Port\SourceProviderAvailabilityOverridePort;
use Packio\Source\Application\Provider\SourceProviderAvailabilityResolver;
use Packio\Source\Application\Provider\SourceProviderLabelResolver;
use Packio\Source\Application\Provider\SourceProviderRegistry;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Query\SourceQueryService;
use Packio\Source\Application\Security\ProviderAuthorizationFactory;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Security\SourceProviderInstanceSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SecretResolverInterface;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceConnectAuthorizationResolver;
use Packio\Source\Application\Service\SourceCreationExecutionInterface;
use Packio\Source\Application\Service\SourceCreationExecutionService;
use Packio\Source\Application\Service\SourceDeletionExecutionInterface;
use Packio\Source\Application\Service\SourceDeletionExecutionLeaseInterface;
use Packio\Source\Application\Service\SourceDeletionExecutionService;
use Packio\Source\Application\Service\SourceJobScheduler;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifier;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifierInterface;
use Packio\Source\Application\Service\SourceOAuthRefreshMaintenanceService;
use Packio\Source\Application\Service\SourceOAuthRefreshOptions;
use Packio\Source\Application\Service\SourceOAuthRefreshPolicy;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceOAuthTokenResolver;
use Packio\Source\Application\Service\SourceProviderInstanceOAuthConfig;
use Packio\Source\Application\Service\SourceSecretResolver;
use Packio\Source\Application\Service\SourceSettingsService;
use Packio\Source\Application\Service\SourceSettingsUpdaterInterface;
use Packio\Source\Application\Service\SourceSyncExecutionInterface;
use Packio\Source\Application\Service\SourceSyncExecutionService;
use Packio\Source\Application\Service\WebhookAutoRegistrationService;
use Packio\Source\Application\Service\WebhookRegistrarRegistryInterface;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceCleanupRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use Packio\Source\Infrastructure\Adapter\AuditRecorderAdapter;
use Packio\Source\Infrastructure\Adapter\IdentityOAuthAdapter;
use Packio\Source\Infrastructure\Adapter\OrganizationMembershipAdapter;
use Packio\Source\Infrastructure\Adapter\OrganizationQueryAdapter;
use Packio\Source\Infrastructure\Adapter\PackageVisibilityAdapter;
use Packio\Source\Infrastructure\Adapter\ProjectSettingsSourceProviderAvailabilityOverrideAdapter;
use Packio\Source\Infrastructure\Adapter\PublicationSyncAdapter;
use Packio\Source\Infrastructure\Adapter\SourceDeleteArtifactsAdapter;
use Packio\Source\Infrastructure\Adapter\Worker\SourceCreateJobHandler;
use Packio\Source\Infrastructure\Adapter\Worker\SourceDeleteJobHandler;
use Packio\Source\Infrastructure\Adapter\Worker\SourceOAuthRefreshJobHandler;
use Packio\Source\Infrastructure\Adapter\Worker\SourceSyncJobHandler;
use Packio\Source\Infrastructure\Adapter\WorkerJobSchedulerAdapter;
use Packio\Source\Infrastructure\BitbucketRepositoryUrlParser;
use Packio\Source\Infrastructure\Cli\SourceOAuthRefreshMaintenanceCommand;
use Packio\Source\Infrastructure\Cli\SourceWebhookSecretsEncryptCommand;
use Packio\Source\Infrastructure\CodebergRepositoryUrlParser;
use Packio\Source\Infrastructure\Facade\SourceApiFacade;
use Packio\Source\Infrastructure\GenericGitRepositoryUrlParser;
use Packio\Source\Infrastructure\GithubRepositoryUrlParser;
use Packio\Source\Infrastructure\GitlabRepositoryUrlParser;
use Packio\Source\Infrastructure\GitlabSelfHostedRepositoryUrlParser;
use Packio\Source\Infrastructure\Handler\SourceArchiveHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationCheckHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationOAuthStoreHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationResourcesHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationStoreHandler;
use Packio\Source\Infrastructure\Handler\SourceDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceDetailsHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceDependenciesHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceInactiveAuthorizationDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstancesHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceStatusHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceStoreHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceUpdateHandler;
use Packio\Source\Infrastructure\Handler\SourceResyncHandler;
use Packio\Source\Infrastructure\Handler\SourceSettingsUpdateHandler;
use Packio\Source\Infrastructure\Handler\SourcesHandler;
use Packio\Source\Infrastructure\Handler\SourceWebhookRegisterHandler;
use Packio\Source\Infrastructure\Handler\SourceWebhookRotateHandler;
use Packio\Source\Infrastructure\Mapper\ProviderAuthorizationDtoMapper;
use Packio\Source\Infrastructure\Mapper\SourceDtoMapper;
use Packio\Source\Infrastructure\Page\SourceDetailsPageDataBuilder;
use Packio\Source\Infrastructure\Page\SourcesPageDataBuilder;
use Packio\Source\Infrastructure\Persistence\Cycle\PostgresSourceDeletionExecutionLease;
use Packio\Source\Infrastructure\Persistence\Cycle\ProviderAuthorizationRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceOAuthAccountRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceProviderInstanceCleanupRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceProviderInstanceRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\TransactionalSourceSettingsUpdater;
use Packio\Source\Infrastructure\Provider\Bitbucket\BitbucketProviderResourceFetcherInterface;
use Packio\Source\Infrastructure\Provider\Bitbucket\BitbucketSourceProvider;
use Packio\Source\Infrastructure\Provider\Bitbucket\HttpBitbucketProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\Codeberg\CodebergProviderResourceFetcherInterface;
use Packio\Source\Infrastructure\Provider\Codeberg\CodebergSourceProvider;
use Packio\Source\Infrastructure\Provider\Codeberg\HttpCodebergProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\GenericGit\GenericGitSourceProvider;
use Packio\Source\Infrastructure\Provider\Github\GithubProviderResourceFetcherInterface;
use Packio\Source\Infrastructure\Provider\Github\GithubSourceProvider;
use Packio\Source\Infrastructure\Provider\Github\HttpGithubProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\Gitlab\GitlabProviderResourceFetcherInterface;
use Packio\Source\Infrastructure\Provider\Gitlab\GitlabSourceProvider;
use Packio\Source\Infrastructure\Provider\Gitlab\HttpGitlabProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\GitlabSelfHosted\GitlabSelfHostedProviderResourceFetcherInterface;
use Packio\Source\Infrastructure\Provider\GitlabSelfHosted\GitlabSelfHostedSourceProvider;
use Packio\Source\Infrastructure\Provider\GitlabSelfHosted\HttpGitlabSelfHostedProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\ProviderApiRequestor;
use Packio\Source\Infrastructure\Provider\ProviderResourceFallbackFactory;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use Packio\Source\Infrastructure\Provider\WebhookSecretGenerator;
use Packio\Source\Infrastructure\Response\SourceProviderInstancePresenter;
use Packio\Source\Infrastructure\Webhook\BitbucketWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\CodebergWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\GithubWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\GitlabSelfHostedWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\GitlabWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\SourceWebhookRegistrarRegistry;
use Packio\Source\Infrastructure\Webhook\WebhookHttpRequestor;
use Packio\Source\Infrastructure\Webhook\WebhookRegistrationTemplateFactory;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Config\Factory\ValinorConfigFactory;
use Sirix\Cycle\Factory\RepositoryFactory;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Reporter\SentryReporter;

return [
    'dependencies'               => [
        'aliases'    => [
            SourceSyncExecutionInterface::class                               => SourceSyncExecutionService::class,
            SourceCreationExecutionInterface::class                           => SourceCreationExecutionService::class,
            SourceDeletionExecutionInterface::class                           => SourceDeletionExecutionService::class,

            SourceRepositoryInterface::class                                  => SourceRepository::class,
            ProviderAuthorizationRepositoryInterface::class                   => ProviderAuthorizationRepository::class,
            SourceOAuthAccountRepositoryInterface::class                      => SourceOAuthAccountRepository::class,
            SourceProviderInstanceRepositoryInterface::class                  => SourceProviderInstanceRepository::class,
            SourceProviderInstanceCleanupRepositoryInterface::class           => SourceProviderInstanceCleanupRepository::class,
            SourceApiFacadeInterface::class                                   => SourceApiFacade::class,
            SourceProviderRegistryInterface::class                            => SourceProviderRegistry::class,
            SourceSecretResolverInterface::class                              => SourceSecretResolver::class,
            GithubProviderResourceFetcherInterface::class                     => HttpGithubProviderResourceFetcher::class,
            GitlabProviderResourceFetcherInterface::class                     => HttpGitlabProviderResourceFetcher::class,
            GitlabSelfHostedProviderResourceFetcherInterface::class           => HttpGitlabSelfHostedProviderResourceFetcher::class,
            BitbucketProviderResourceFetcherInterface::class                  => HttpBitbucketProviderResourceFetcher::class,
            CodebergProviderResourceFetcherInterface::class                   => HttpCodebergProviderResourceFetcher::class,
            OAuthProviderPort::class                                          => IdentityOAuthAdapter::class,
            JobSchedulerPort::class                                           => WorkerJobSchedulerAdapter::class,
            AuditRecorderPort::class                                          => AuditRecorderAdapter::class,
            OrganizationQueryPort::class                                      => OrganizationQueryAdapter::class,
            OrganizationMembershipPort::class                                 => OrganizationMembershipAdapter::class,
            SecretResolverInterface::class                                    => SourceSecretResolver::class,
            PublicationSyncPort::class                                        => PublicationSyncAdapter::class,
            PackageVisibilityPort::class                                      => PackageVisibilityAdapter::class,
            SourceSettingsUpdaterInterface::class                             => TransactionalSourceSettingsUpdater::class,
            SourceDeleteArtifactsPort::class                                  => SourceDeleteArtifactsAdapter::class,
            SourceDeletionExecutionLeaseInterface::class                      => PostgresSourceDeletionExecutionLease::class,
            SourceProviderAvailabilityOverridePort::class                     => ProjectSettingsSourceProviderAvailabilityOverrideAdapter::class,
            WebhookRegistrarRegistryInterface::class                          => SourceWebhookRegistrarRegistry::class,
            SourceOAuthAccountOwnershipVerifierInterface::class               => SourceOAuthAccountOwnershipVerifier::class,
        ],
        'invokables' => [
            SourceProviderInstancePresenter::class     => SourceProviderInstancePresenter::class,
            SourceAuthorizationStrategyResolver::class => SourceAuthorizationStrategyResolver::class,
            SourcePolicy::class                        => SourcePolicy::class,
        ],
        'factories'  => [
            SourcesHandler::class                                           => ConfigAbstractFactory::class,
            SourcesPageDataBuilder::class                                   => ConfigAbstractFactory::class,
            SourceDetailsPageDataBuilder::class                             => ConfigAbstractFactory::class,
            SourceAuthorizationCheckHandler::class                          => ConfigAbstractFactory::class,
            SourceAuthorizationResourcesHandler::class                      => ConfigAbstractFactory::class,
            SourceProviderAvailabilityResolver::class                       => ConfigAbstractFactory::class,
            SourceProviderLabelResolver::class                              => ConfigAbstractFactory::class,

            // --- Repositories ---
            SourceRepository::class                                         => RepositoryFactory::class,
            ProviderAuthorizationRepository::class                          => RepositoryFactory::class,
            SourceOAuthAccountRepository::class                             => RepositoryFactory::class,
            SourceProviderInstanceRepository::class                         => RepositoryFactory::class,
            PostgresSourceDeletionExecutionLease::class                     => ConfigAbstractFactory::class,

            // --- Mappers ---
            SourceDtoMapper::class                                          => ConfigAbstractFactory::class,
            ProviderAuthorizationDtoMapper::class                           => ConfigAbstractFactory::class,

            // --- Security ---
            ProviderAuthorizationSecrets::class                             => ConfigAbstractFactory::class,
            SourceWebhookSecrets::class                                     => ConfigAbstractFactory::class,
            SourceOAuthAccountSecrets::class                                => ConfigAbstractFactory::class,
            SourceProviderInstanceSecrets::class                            => ConfigAbstractFactory::class,
            ProviderAuthorizationFactory::class                             => ConfigAbstractFactory::class,

            // --- Application Services ---
            SourceCommandService::class                                     => ConfigAbstractFactory::class,
            SourceQueryService::class                                       => ConfigAbstractFactory::class,
            SourceMaintenanceService::class                                 => ConfigAbstractFactory::class,
            SourceProviderInstanceService::class                            => ConfigAbstractFactory::class,
            SourceOAuthAccountOwnershipVerifier::class                      => ConfigAbstractFactory::class,
            SourceOAuthRefreshOptions::class                                => [ValinorConfigFactory::class, 'config.source_oauth_refresh'],
            SourceOAuthRefreshPolicy::class                                 => ConfigAbstractFactory::class,
            SourceOAuthRefreshMaintenanceService::class                     => ConfigAbstractFactory::class,
            SourceJobScheduler::class                                       => ConfigAbstractFactory::class,
            SourceConnectAuthorizationResolver::class                       => ConfigAbstractFactory::class,
            SourceSettingsService::class                                    => ConfigAbstractFactory::class,
            TransactionalSourceSettingsUpdater::class                       => ConfigAbstractFactory::class,
            PackageVisibilityAdapter::class                                 => ConfigAbstractFactory::class,
            SourceOAuthService::class                                       => ConfigAbstractFactory::class,
            SourceOAuthTokenResolver::class                                 => ConfigAbstractFactory::class,

            // --- Port Adapters ---
            IdentityOAuthAdapter::class                                     => ConfigAbstractFactory::class,
            WorkerJobSchedulerAdapter::class                                => ConfigAbstractFactory::class,
            AuditRecorderAdapter::class                                     => ConfigAbstractFactory::class,
            OrganizationQueryAdapter::class                                 => ConfigAbstractFactory::class,
            SourceSecretResolver::class                                     => ConfigAbstractFactory::class,
            WebhookAutoRegistrationService::class                           => ConfigAbstractFactory::class,

            // --- API Facade ---
            SourceApiFacade::class                                          => ConfigAbstractFactory::class,

            // --- Ports/Adapters ---
            OrganizationMembershipAdapter::class                            => ConfigAbstractFactory::class,
            PublicationSyncAdapter::class                                   => ConfigAbstractFactory::class,
            SourceDeleteArtifactsAdapter::class                             => ConfigAbstractFactory::class,
            ProjectSettingsSourceProviderAvailabilityOverrideAdapter::class => ConfigAbstractFactory::class,

            // --- Job Handlers ---
            SourceCreateJobHandler::class                                   => ConfigAbstractFactory::class,
            SourceCreationExecutionService::class                           => ConfigAbstractFactory::class,
            SourceDeleteJobHandler::class                                   => ConfigAbstractFactory::class,
            SourceDeletionExecutionService::class                           => ConfigAbstractFactory::class,
            SourceOAuthRefreshJobHandler::class                             => ConfigAbstractFactory::class,
            SourceSyncJobHandler::class                                     => ConfigAbstractFactory::class,
            SourceSyncExecutionService::class                               => ConfigAbstractFactory::class,
            SourceOAuthRefreshMaintenanceCommand::class                     => ConfigAbstractFactory::class,
            SourceWebhookSecretsEncryptCommand::class                       => ConfigAbstractFactory::class,

            // --- Registries ---
            SourceProviderRegistry::class                                   => static fn (ContainerInterface $container): SourceProviderRegistry => new SourceProviderRegistry([
                $container->get(GithubSourceProvider::class),
                $container->get(GitlabSourceProvider::class),
                $container->get(GitlabSelfHostedSourceProvider::class),
                $container->get(GenericGitSourceProvider::class),
                $container->get(BitbucketSourceProvider::class),
                $container->get(CodebergSourceProvider::class),
            ], $container->get(SourceProviderAvailabilityResolver::class)),

            // --- Source Providers ---
            ProviderResourceFallbackFactory::class                          => ConfigAbstractFactory::class,
            SourceRegistrationFactory::class                                => ConfigAbstractFactory::class,
            WebhookSecretGenerator::class                                   => ConfigAbstractFactory::class,
            GithubSourceProvider::class                                     => ConfigAbstractFactory::class,
            GitlabSourceProvider::class                                     => ConfigAbstractFactory::class,
            GitlabSelfHostedSourceProvider::class                           => ConfigAbstractFactory::class,
            GenericGitSourceProvider::class                                 => ConfigAbstractFactory::class,
            BitbucketSourceProvider::class                                  => ConfigAbstractFactory::class,
            CodebergSourceProvider::class                                   => ConfigAbstractFactory::class,
            GithubRepositoryUrlParser::class                                => ConfigAbstractFactory::class,
            GitlabRepositoryUrlParser::class                                => ConfigAbstractFactory::class,
            GitlabSelfHostedRepositoryUrlParser::class                      => ConfigAbstractFactory::class,
            GenericGitRepositoryUrlParser::class                            => ConfigAbstractFactory::class,
            BitbucketRepositoryUrlParser::class                             => ConfigAbstractFactory::class,
            CodebergRepositoryUrlParser::class                              => ConfigAbstractFactory::class,
            ProviderApiRequestor::class                                     => ConfigAbstractFactory::class,
            HttpGithubProviderResourceFetcher::class                        => ConfigAbstractFactory::class,
            HttpGitlabProviderResourceFetcher::class                        => ConfigAbstractFactory::class,
            HttpGitlabSelfHostedProviderResourceFetcher::class              => ConfigAbstractFactory::class,
            HttpBitbucketProviderResourceFetcher::class                     => ConfigAbstractFactory::class,
            HttpCodebergProviderResourceFetcher::class                      => ConfigAbstractFactory::class,
            WebhookHttpRequestor::class                                     => ConfigAbstractFactory::class,
            GithubWebhookProviderAdapter::class                             => ConfigAbstractFactory::class,
            GitlabWebhookProviderAdapter::class                             => ConfigAbstractFactory::class,
            GitlabSelfHostedWebhookProviderAdapter::class                   => ConfigAbstractFactory::class,
            BitbucketWebhookProviderAdapter::class                          => ConfigAbstractFactory::class,
            CodebergWebhookProviderAdapter::class                           => ConfigAbstractFactory::class,
            WebhookRegistrationTemplateFactory::class                       => ConfigAbstractFactory::class,
            SourceWebhookRegistrarRegistry::class                           => static function(ContainerInterface $container): SourceWebhookRegistrarRegistry {
                $factory = $container->get(WebhookRegistrationTemplateFactory::class);

                return new SourceWebhookRegistrarRegistry([
                    $factory->create($container->get(GithubWebhookProviderAdapter::class)),
                    $factory->create($container->get(GitlabWebhookProviderAdapter::class)),
                    $factory->create($container->get(GitlabSelfHostedWebhookProviderAdapter::class)),
                    $factory->create($container->get(BitbucketWebhookProviderAdapter::class)),
                    $factory->create($container->get(CodebergWebhookProviderAdapter::class)),
                ]);
            },

            // --- Handlers ---
            SourceArchiveHandler::class                                     => ConfigAbstractFactory::class,
            SourceAuthorizationStoreHandler::class                          => ConfigAbstractFactory::class,
            SourceAuthorizationOAuthStoreHandler::class                     => ConfigAbstractFactory::class,
            SourceAuthorizationDeleteHandler::class                         => ConfigAbstractFactory::class,
            SourceProviderInstancesHandler::class                           => ConfigAbstractFactory::class,
            SourceProviderInstanceStoreHandler::class                       => ConfigAbstractFactory::class,
            SourceProviderInstanceUpdateHandler::class                      => ConfigAbstractFactory::class,
            SourceProviderInstanceStatusHandler::class                      => ConfigAbstractFactory::class,
            SourceProviderInstanceDeleteHandler::class                      => ConfigAbstractFactory::class,
            SourceDeleteHandler::class                                      => ConfigAbstractFactory::class,
            SourceResyncHandler::class                                      => ConfigAbstractFactory::class,
            SourceDetailsHandler::class                                     => ConfigAbstractFactory::class,
            SourceSettingsUpdateHandler::class                              => ConfigAbstractFactory::class,
            SourceWebhookRotateHandler::class                               => ConfigAbstractFactory::class,
            SourceWebhookRegisterHandler::class                             => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        // --- Mappers ---
        SourceDtoMapper::class                                           => [
            ProviderAuthorizationRepositoryInterface::class,
            SourceWebhookSecrets::class,
        ],
        ProviderAuthorizationDtoMapper::class                            => [
            SecretResolverInterface::class,
            SourceOAuthAccountRepositoryInterface::class,
        ],

        // --- Security ---
        ProviderAuthorizationSecrets::class                              => [
            SecretEncryptorInterface::class,
        ],
        SourceWebhookSecrets::class                                      => [
            SecretEncryptorInterface::class,
        ],
        SourceOAuthAccountSecrets::class                                 => [
            SecretEncryptorInterface::class,
        ],
        SourceProviderInstanceSecrets::class                             => [
            SecretEncryptorInterface::class,
        ],
        ProviderAuthorizationFactory::class                              => [
            ProviderAuthorizationSecrets::class,
        ],

        // --- Application Services ---
        SourceCommandService::class                                      => [
            SourceRepositoryInterface::class,
            ProviderAuthorizationRepositoryInterface::class,
            SourceProviderRegistryInterface::class,
            ProviderAuthorizationFactory::class,
            ProviderAuthorizationSecrets::class,
            SourceAuthorizationStrategyResolver::class,
            SourceMaintenanceService::class,
            SourceOAuthAccountOwnershipVerifierInterface::class,
            SourceProviderInstanceRepositoryInterface::class,
            SourceAuthorizationInvalidationService::class,
            WebhookAutoRegistrationService::class,
            SourceWebhookSecrets::class,
        ],
        SourceQueryService::class                                        => [
            SourceRepositoryInterface::class,
            ProviderAuthorizationRepositoryInterface::class,
            SourceProviderRegistryInterface::class,
            ProviderAuthorizationSecrets::class,
            SecretResolverInterface::class,
            SourceAuthorizationInvalidationService::class,
            SourceOAuthTokenResolver::class,
        ],
        SourceMaintenanceService::class                                  => [
            SourceRepositoryInterface::class,
        ],
        SourceProviderInstanceService::class                             => [
            SourceProviderInstanceRepositoryInterface::class,
            SourceProviderInstanceSecrets::class,
            SourceRepositoryInterface::class,
            ProviderAuthorizationRepositoryInterface::class,
            OrganizationQueryPort::class,
            OAuthProviderPort::class,
            SourceProviderInstanceOAuthConfig::class,
            SourceProviderInstanceCleanupRepositoryInterface::class,
            LoggerInterface::class,
            OutboundUrlPolicy::class,
        ],
        SourceProviderInstanceCleanupRepository::class                   => [
            SourceProviderInstanceRepository::class,
            SourceRepositoryInterface::class,
            ProviderAuthorizationRepositoryInterface::class,
            SourceOAuthAccountRepositoryInterface::class,
        ],
        SourceAuthorizationInvalidationService::class                    => [
            SourceRepositoryInterface::class,
            ProviderAuthorizationRepositoryInterface::class,
            SourceOAuthService::class,
        ],
        SourceOAuthAccountOwnershipVerifier::class                       => [
            SourceOAuthAccountRepositoryInterface::class,
            SourceOAuthAccountSecrets::class,
        ],
        SourceOAuthRefreshPolicy::class                                  => [
            SourceOAuthRefreshOptions::class,
        ],
        SourceOAuthRefreshMaintenanceService::class                      => [
            SourceOAuthAccountRepositoryInterface::class,
            ProviderAuthorizationRepositoryInterface::class,
            SourceOAuthTokenResolver::class,
            SourceOAuthRefreshPolicy::class,
            JobSchedulerPort::class,
            SourceAuthorizationInvalidationService::class,
        ],
        SourceProviderInstanceOAuthConfig::class                         => [
            SourceProviderInstanceSecrets::class,
            BrandingConfig::class,
        ],
        SourceOAuthService::class                                        => [
            OAuthProviderPort::class,
            SourceOAuthAccountRepositoryInterface::class,
            SourceOAuthAccountSecrets::class,
            OrganizationQueryPort::class,
            SourceProviderInstanceRepositoryInterface::class,
            SourceProviderInstanceOAuthConfig::class,
        ],
        SourceOAuthTokenResolver::class                                  => [
            SourceOAuthAccountRepositoryInterface::class,
            ProviderAuthorizationRepositoryInterface::class,
            SourceRepositoryInterface::class,
            SourceOAuthAccountSecrets::class,
            OAuthProviderPort::class,
            SourceProviderInstanceRepositoryInterface::class,
            SourceProviderInstanceOAuthConfig::class,
            LoggerInterface::class,
        ],
        // --- Port Adapters ---
        IdentityOAuthAdapter::class                                      => [
            OAuthApiFacadeInterface::class,
        ],
        WorkerJobSchedulerAdapter::class                                 => [
            JobApiFacadeInterface::class,
        ],
        AuditRecorderAdapter::class                                      => [
            AuditApiFacadeInterface::class,
        ],
        OrganizationQueryAdapter::class                                  => [
            OrganizationApiFacadeInterface::class,
        ],
        OrganizationMembershipAdapter::class                             => [
            OrganizationApiFacadeInterface::class,
        ],
        PublicationSyncAdapter::class                                    => [
            PublicationApiFacadeInterface::class,
        ],
        SourceDeleteArtifactsAdapter::class                              => [
            PackageApiFacadeInterface::class,
            ArchiveStorageCleanupApiFacadeInterface::class,
            ReleaseApiFacadeInterface::class,
            WebhookApiFacadeInterface::class,
            SourceDeletionExecutionLeaseInterface::class,
        ],
        PostgresSourceDeletionExecutionLease::class                      => [
            'dbal',
            LoggerInterface::class,
        ],
        ProjectSettingsSourceProviderAvailabilityOverrideAdapter::class  => [
            ProjectSettingsApiFacadeInterface::class,
        ],

        // --- Job Handlers ---
        SourceCreateJobHandler::class                                    => [SourceCreationExecutionInterface::class],
        SourceCreationExecutionService::class                            => [
            SourceCommandService::class,
            OrganizationMembershipPort::class,
            JobSchedulerPort::class,
        ],
        SourceDeleteJobHandler::class                                    => [SourceDeletionExecutionInterface::class],
        SourceDeletionExecutionService::class                            => [
            SourceCommandService::class,
            SourceDeleteArtifactsPort::class,
            SourceDeletionExecutionLeaseInterface::class,
            SourceRepositoryInterface::class,
        ],
        SourceSyncJobHandler::class                                      => [SourceSyncExecutionInterface::class],
        SourceSyncExecutionService::class                                => [
            PublicationSyncPort::class,
            SourceCommandService::class,
            JobSchedulerPort::class,
            SourceRepositoryInterface::class,
        ],
        SourceOAuthRefreshJobHandler::class                              => [
            SourceOAuthRefreshMaintenanceService::class,
        ],
        SourceOAuthRefreshMaintenanceCommand::class                      => [
            SourceOAuthRefreshPolicy::class,
            SourceOAuthRefreshMaintenanceService::class,
            OrganizationQueryPort::class,
            SentryLifecycle::class,
            SentryReporter::class,
        ],
        SourceWebhookSecretsEncryptCommand::class                        => [
            SourceRepositoryInterface::class,
            SourceWebhookSecrets::class,
        ],

        SourceSecretResolver::class                                      => [
            SourceRepositoryInterface::class,
            ProviderAuthorizationRepositoryInterface::class,
            ProviderAuthorizationSecrets::class,
            SourceOAuthTokenResolver::class,
            SourceWebhookSecrets::class,
        ],
        WebhookAutoRegistrationService::class                            => [
            WebhookRegistrarRegistryInterface::class,
            SourceRepositoryInterface::class,
            SourceAuthorizationInvalidationService::class,
            LoggerInterface::class,
        ],

        // --- API Facade ---
        SourceApiFacade::class                                           => [
            SourceCommandService::class,
            SourceQueryService::class,
            SourceJobScheduler::class,
            SourceOAuthService::class,
            SourceDtoMapper::class,
            ProviderAuthorizationDtoMapper::class,
            ObjectMapperInterface::class,
            OAuthProviderPort::class,
            SourceProviderInstanceService::class,
            SourceProviderLabelResolver::class,
            SourceConnectAuthorizationResolver::class,
            SourceSettingsService::class,
        ],
        SourceSettingsService::class                                     => [
            SourceSettingsUpdaterInterface::class,
        ],
        TransactionalSourceSettingsUpdater::class                        => [
            ORMInterface::class,
            SourceQueryService::class,
            SourceCommandService::class,
            SourceDtoMapper::class,
            PackageVisibilityPort::class,
        ],
        PackageVisibilityAdapter::class                                  => [
            PackageApiFacadeInterface::class,
        ],
        SourceConnectAuthorizationResolver::class                        => [
            SourceCommandService::class,
            SourceAuthorizationStrategyResolver::class,
            ProviderAuthorizationRepositoryInterface::class,
            StoredSecretResolver::class,
        ],
        SourceJobScheduler::class                                        => [
            SourceCommandService::class,
            JobSchedulerPort::class,
            AuditRecorderPort::class,
            LoggerInterface::class,
        ],

        // --- Page Data Builders ---
        SourceProviderAvailabilityResolver::class                        => [
            'config.sources',
            SourceProviderAvailabilityOverridePort::class,
        ],
        SourceProviderLabelResolver::class                               => [
            SourceProviderRegistryInterface::class,
        ],
        SourcesPageDataBuilder::class                                    => [
            SourceApiFacadeInterface::class,
            SourceSecretResolverInterface::class,
            WebhookApiFacadeInterface::class,
            OrganizationPolicy::class,
            BrandingConfig::class,
            SourceProviderLabelResolver::class,
            OAuthApiFacadeInterface::class,
        ],
        SourceDetailsPageDataBuilder::class                              => [
            PackageApiFacadeInterface::class,
            ReleaseApiFacadeInterface::class,
            SourceApiFacadeInterface::class,
            SourceSecretResolverInterface::class,
            WebhookApiFacadeInterface::class,
            AuditApiFacadeInterface::class,
            OrganizationPolicy::class,
            BrandingConfig::class,
            SourceProviderLabelResolver::class,
        ],

        // --- Handlers ---
        SourcesHandler::class                                            => [
            SourcesPageDataBuilder::class,
        ],
        SourceAuthorizationCheckHandler::class                           => [
            SourceApiFacadeInterface::class,
        ],
        SourceAuthorizationResourcesHandler::class                       => [
            SourceApiFacadeInterface::class,
            LoggerInterface::class,
        ],
        SourceArchiveHandler::class                                      => [
            SourceApiFacadeInterface::class,
            PackageApiFacadeInterface::class,
            SourcePolicy::class,
            InertiaNotificationMessenger::class,
        ],
        SourceAuthorizationStoreHandler::class                           => [
            SourceApiFacadeInterface::class,
            SourceAuthorizationStrategyResolver::class,
            InertiaNotificationMessenger::class,
            LoggerInterface::class,
        ],
        SourceAuthorizationOAuthStoreHandler::class                      => [
            SourceApiFacadeInterface::class,
            LoggerInterface::class,
        ],
        SourceAuthorizationDeleteHandler::class                          => [
            SourceApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        SourceProviderInstancesHandler::class                            => [
            SourceApiFacadeInterface::class,
            SourceProviderInstancePresenter::class,
        ],
        SourceProviderInstanceStoreHandler::class                        => [
            SourceApiFacadeInterface::class,
            SourceProviderInstancePresenter::class,
            LoggerInterface::class,
        ],
        SourceProviderInstanceUpdateHandler::class                       => [
            SourceApiFacadeInterface::class,
            SourceProviderInstancePresenter::class,
            LoggerInterface::class,
        ],
        SourceProviderInstanceStatusHandler::class                       => [
            SourceApiFacadeInterface::class,
            SourceProviderInstancePresenter::class,
            LoggerInterface::class,
        ],
        SourceProviderInstanceDeleteHandler::class                       => [
            SourceApiFacadeInterface::class,
            LoggerInterface::class,
        ],
        SourceProviderInstanceDependenciesHandler::class                 => [
            SourceApiFacadeInterface::class,
            SourceProviderInstancePresenter::class,
            LoggerInterface::class,
        ],
        SourceProviderInstanceInactiveAuthorizationDeleteHandler::class  => [
            SourceApiFacadeInterface::class,
            SourceProviderInstancePresenter::class,
            LoggerInterface::class,
        ],
        SourceDeleteHandler::class                                       => [
            SourceApiFacadeInterface::class,
            SourcePolicy::class,
            InertiaNotificationMessenger::class,
        ],
        SourceResyncHandler::class                                       => [
            SourceApiFacadeInterface::class,
            SourcePolicy::class,
            InertiaNotificationMessenger::class,
        ],
        SourceSettingsUpdateHandler::class                               => [
            SourceApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        SourceWebhookRotateHandler::class                                => [
            SourceApiFacadeInterface::class,
            SourcePolicy::class,
            InertiaNotificationMessenger::class,
        ],
        SourceWebhookRegisterHandler::class                              => [
            SourceApiFacadeInterface::class,
            SourcePolicy::class,
            InertiaNotificationMessenger::class,
        ],
        SourceDetailsHandler::class                                      => [
            SourceApiFacadeInterface::class,
            SourcePolicy::class,
            SourceDetailsPageDataBuilder::class,
        ],

        // --- Source Providers ---
        ProviderResourceFallbackFactory::class                           => [],
        SourceRegistrationFactory::class                                 => [],
        WebhookSecretGenerator::class                                    => [],
        GithubSourceProvider::class                                      => [
            GithubRepositoryUrlParser::class,
            GithubProviderResourceFetcherInterface::class,
            ProviderResourceFallbackFactory::class,
            SourceRegistrationFactory::class,
            WebhookSecretGenerator::class,
        ],
        GitlabSourceProvider::class                                      => [
            GitlabRepositoryUrlParser::class,
            GitlabProviderResourceFetcherInterface::class,
            StoredSecretResolver::class,
            BrandingConfig::class,
            UpstreamHttpClient::class,
            ProviderResourceFallbackFactory::class,
            SourceRegistrationFactory::class,
            WebhookSecretGenerator::class,
        ],
        GitlabSelfHostedSourceProvider::class                            => [
            GitlabSelfHostedRepositoryUrlParser::class,
            GitlabSelfHostedProviderResourceFetcherInterface::class,
            ProviderResourceFallbackFactory::class,
            SourceRegistrationFactory::class,
            WebhookSecretGenerator::class,
        ],
        GenericGitSourceProvider::class                                  => [
            GenericGitRepositoryUrlParser::class,
            SourceRegistrationFactory::class,
        ],
        BitbucketSourceProvider::class                                   => [
            BitbucketRepositoryUrlParser::class,
            BitbucketProviderResourceFetcherInterface::class,
            ProviderResourceFallbackFactory::class,
            SourceRegistrationFactory::class,
            WebhookSecretGenerator::class,
        ],
        CodebergSourceProvider::class                                    => [
            CodebergRepositoryUrlParser::class,
            CodebergProviderResourceFetcherInterface::class,
            SourceRegistrationFactory::class,
            WebhookSecretGenerator::class,
        ],

        // --- URL Parsers ---
        GithubRepositoryUrlParser::class                                 => [],
        GitlabRepositoryUrlParser::class                                 => [],
        GitlabSelfHostedRepositoryUrlParser::class                       => [],
        GenericGitRepositoryUrlParser::class                             => [OutboundUrlPolicy::class],
        BitbucketRepositoryUrlParser::class                              => [],
        CodebergRepositoryUrlParser::class                               => [],
        ProviderApiRequestor::class                                      => [
            UpstreamHttpClient::class,
        ],
        HttpGithubProviderResourceFetcher::class                         => [
            BrandingConfig::class,
            ProviderApiRequestor::class,
            LoggerInterface::class,
        ],
        HttpGitlabProviderResourceFetcher::class                         => [
            BrandingConfig::class,
            ProviderApiRequestor::class,
            LoggerInterface::class,
        ],
        HttpGitlabSelfHostedProviderResourceFetcher::class               => [
            BrandingConfig::class,
            ProviderApiRequestor::class,
        ],
        HttpBitbucketProviderResourceFetcher::class                      => [
            BrandingConfig::class,
            ProviderApiRequestor::class,
        ],
        HttpCodebergProviderResourceFetcher::class                       => [
            BrandingConfig::class,
            ProviderApiRequestor::class,
        ],
        WebhookHttpRequestor::class                                      => [
            UpstreamHttpClient::class,
        ],
        WebhookRegistrationTemplateFactory::class                        => [
            BrandingConfig::class,
            SecretResolverInterface::class,
            WebhookHttpRequestor::class,
            SourceWebhookSecrets::class,
        ],
        GithubWebhookProviderAdapter::class                              => [
            BrandingConfig::class,
        ],
        GitlabWebhookProviderAdapter::class                              => [
            BrandingConfig::class,
        ],
        GitlabSelfHostedWebhookProviderAdapter::class                    => [
            BrandingConfig::class,
        ],
        BitbucketWebhookProviderAdapter::class                           => [
            BrandingConfig::class,
        ],
        CodebergWebhookProviderAdapter::class                            => [
            BrandingConfig::class,
        ],
    ],

    'worker'                     => [
        'job_handlers' => [
            'source.create'        => SourceCreateJobHandler::class,
            'source.delete'        => SourceDeleteJobHandler::class,
            'source.oauth.refresh' => SourceOAuthRefreshJobHandler::class,
            'source.sync'          => SourceSyncJobHandler::class,
        ],
    ],
    'source_oauth_refresh'       => [
        'refresh_before'           => '24 hours',
        'idle_before'              => '30 days',
        'minimum_refresh_interval' => '1 day',
        'limit'                    => 100,
        'providers'                => [
            'github'             => [
                'idle_before' => '60 days',
            ],
            'gitlab'             => [
                'idle_before' => '30 days',
            ],
            'gitlab_self_hosted' => [
                'idle_before' => '30 days',
            ],
            'bitbucket'          => [
                'idle_before' => '30 days',
            ],
            'codeberg'           => [
                'idle_before' => '30 days',
            ],
        ],
    ],
];
