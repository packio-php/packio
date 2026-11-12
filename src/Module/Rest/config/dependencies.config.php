<?php

declare(strict_types=1);

use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Application\Security\RateLimiter\ApiAuthenticationAttemptLimiter;
use Packio\Application\Validation\ApiValidationFailureResponseFactoryInterface;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Rest\Application\Security\ApiScopeEvaluator;
use Packio\Rest\Infrastructure\Authentication\ApiAccessTokenTransport;
use Packio\Rest\Infrastructure\Handler\AdminJobRestartHandler;
use Packio\Rest\Infrastructure\Handler\AdminJobShowHandler;
use Packio\Rest\Infrastructure\Handler\AdminJobsIndexHandler;
use Packio\Rest\Infrastructure\Handler\AdminOrganizationDeleteHandler;
use Packio\Rest\Infrastructure\Handler\AdminOrganizationShowHandler;
use Packio\Rest\Infrastructure\Handler\AdminOrganizationsIndexHandler;
use Packio\Rest\Infrastructure\Handler\AdminOrganizationUpdateHandler;
use Packio\Rest\Infrastructure\Handler\AdminPackagesIndexHandler;
use Packio\Rest\Infrastructure\Handler\AdminSettingsIndexHandler;
use Packio\Rest\Infrastructure\Handler\AdminSettingsUpdateHandler;
use Packio\Rest\Infrastructure\Handler\AdminSourcesIndexHandler;
use Packio\Rest\Infrastructure\Handler\AdminUserShowHandler;
use Packio\Rest\Infrastructure\Handler\AdminUsersIndexHandler;
use Packio\Rest\Infrastructure\Handler\AdminUserUpdateHandler;
use Packio\Rest\Infrastructure\Handler\AdminUserVerifyHandler;
use Packio\Rest\Infrastructure\Handler\ApiAccessTokenRegenerateHandler;
use Packio\Rest\Infrastructure\Handler\ApiAccessTokensCreateHandler;
use Packio\Rest\Infrastructure\Handler\ApiAccessTokensDeleteHandler;
use Packio\Rest\Infrastructure\Handler\ApiAccessTokensIndexHandler;
use Packio\Rest\Infrastructure\Handler\ApiHealthHandler;
use Packio\Rest\Infrastructure\Handler\CurrentUserHandler;
use Packio\Rest\Infrastructure\Handler\OpenApiAssetHandler;
use Packio\Rest\Infrastructure\Handler\OpenApiJsonHandler;
use Packio\Rest\Infrastructure\Handler\OpenApiUiHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationDeleteHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationInvitationsCreateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationInvitationsDeleteHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationInvitationsIndexHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationInvitationsRevokeHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationMemberDeleteHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationMembersIndexHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationMemberUpdateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationsCreateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationShowHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationsIndexHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationUpdateHandler;
use Packio\Rest\Infrastructure\Handler\PackageDeleteHandler;
use Packio\Rest\Infrastructure\Handler\PackageShowHandler;
use Packio\Rest\Infrastructure\Handler\PackagesIndexHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationDeleteHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationDependenciesHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationReassignDeleteHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationResourcesHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationsCreateHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationsIndexHandler;
use Packio\Rest\Infrastructure\Handler\RegistryOverviewHandler;
use Packio\Rest\Infrastructure\Handler\RegistryTokensCreateHandler;
use Packio\Rest\Infrastructure\Handler\RegistryTokensDeleteHandler;
use Packio\Rest\Infrastructure\Handler\RegistryTokensIndexHandler;
use Packio\Rest\Infrastructure\Handler\RegistryTokensRegenerateHandler;
use Packio\Rest\Infrastructure\Handler\ReleaseActivityHandler;
use Packio\Rest\Infrastructure\Handler\ReleasesDeleteHandler;
use Packio\Rest\Infrastructure\Handler\ReleaseShowByVersionHandler;
use Packio\Rest\Infrastructure\Handler\ReleasesIndexHandler;
use Packio\Rest\Infrastructure\Handler\SourceArchiveHandler;
use Packio\Rest\Infrastructure\Handler\SourceDeleteHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceActivateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceDeactivateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceDeleteHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstancesCreateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstancesIndexHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceUpdateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProvidersIndexHandler;
use Packio\Rest\Infrastructure\Handler\SourceRestoreHandler;
use Packio\Rest\Infrastructure\Handler\SourcesCreateHandler;
use Packio\Rest\Infrastructure\Handler\SourceShowHandler;
use Packio\Rest\Infrastructure\Handler\SourcesIndexHandler;
use Packio\Rest\Infrastructure\Handler\SourceSyncHandler;
use Packio\Rest\Infrastructure\Handler\SourceUpdateHandler;
use Packio\Rest\Infrastructure\Handler\SourceWebhookEventReprocessHandler;
use Packio\Rest\Infrastructure\Handler\SourceWebhookEventsIndexHandler;
use Packio\Rest\Infrastructure\Handler\SourceWebhookRegisterHandler;
use Packio\Rest\Infrastructure\Handler\SourceWebhookRotateSecretHandler;
use Packio\Rest\Infrastructure\Middleware\ApiAuditMiddleware;
use Packio\Rest\Infrastructure\Middleware\ApiAuthenticationContextMiddleware;
use Packio\Rest\Infrastructure\Middleware\ApiOrganizationContextMiddleware;
use Packio\Rest\Infrastructure\Middleware\ApiScopeMiddleware;
use Packio\Rest\Infrastructure\OpenApi\OpenApiAccessGuard;
use Packio\Rest\Infrastructure\OpenApi\OpenApiSpecGenerator;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Rest\Infrastructure\Response\PaginationResponseFactory;
use Packio\Rest\Infrastructure\Validation\ApiValidationFailureResponseFactory;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;

return [
    'dependencies'               => [
        'aliases'    => [
            ApiValidationFailureResponseFactoryInterface::class => ApiValidationFailureResponseFactory::class,
        ],
        'invokables' => [
            ApiAccessTokenTransport::class  => ApiAccessTokenTransport::class,
            ApiErrorResponseFactory::class  => ApiErrorResponseFactory::class,
            ApiResponseFactory::class       => ApiResponseFactory::class,
            ApiScopeEvaluator::class        => ApiScopeEvaluator::class,
        ],
        'factories'  => [
            PaginationResponseFactory::class                   => ConfigAbstractFactory::class,
            ApiAccessTokensIndexHandler::class                 => ConfigAbstractFactory::class,
            ApiAccessTokensCreateHandler::class                => ConfigAbstractFactory::class,
            ApiAccessTokensDeleteHandler::class                => ConfigAbstractFactory::class,
            ApiAccessTokenRegenerateHandler::class             => ConfigAbstractFactory::class,
            OrganizationsIndexHandler::class                   => ConfigAbstractFactory::class,
            OrganizationsCreateHandler::class                  => ConfigAbstractFactory::class,
            OrganizationShowHandler::class                     => ConfigAbstractFactory::class,
            OrganizationUpdateHandler::class                   => ConfigAbstractFactory::class,
            OrganizationDeleteHandler::class                   => ConfigAbstractFactory::class,
            OrganizationMembersIndexHandler::class             => ConfigAbstractFactory::class,
            OrganizationMemberUpdateHandler::class             => ConfigAbstractFactory::class,
            OrganizationMemberDeleteHandler::class             => ConfigAbstractFactory::class,
            OrganizationInvitationsIndexHandler::class         => ConfigAbstractFactory::class,
            OrganizationInvitationsCreateHandler::class        => ConfigAbstractFactory::class,
            OrganizationInvitationsRevokeHandler::class        => ConfigAbstractFactory::class,
            OrganizationInvitationsDeleteHandler::class        => ConfigAbstractFactory::class,
            AdminJobsIndexHandler::class                       => ConfigAbstractFactory::class,
            AdminJobShowHandler::class                         => ConfigAbstractFactory::class,
            AdminJobRestartHandler::class                      => ConfigAbstractFactory::class,
            AdminUsersIndexHandler::class                      => ConfigAbstractFactory::class,
            AdminUserShowHandler::class                        => ConfigAbstractFactory::class,
            AdminUserUpdateHandler::class                      => ConfigAbstractFactory::class,
            AdminUserVerifyHandler::class                      => ConfigAbstractFactory::class,
            AdminOrganizationsIndexHandler::class              => ConfigAbstractFactory::class,
            AdminOrganizationShowHandler::class                => ConfigAbstractFactory::class,
            AdminOrganizationUpdateHandler::class              => ConfigAbstractFactory::class,
            AdminOrganizationDeleteHandler::class              => ConfigAbstractFactory::class,
            AdminPackagesIndexHandler::class                   => ConfigAbstractFactory::class,
            AdminSourcesIndexHandler::class                    => ConfigAbstractFactory::class,
            AdminSettingsIndexHandler::class                   => ConfigAbstractFactory::class,
            AdminSettingsUpdateHandler::class                  => ConfigAbstractFactory::class,
            RegistryTokensIndexHandler::class                  => ConfigAbstractFactory::class,
            RegistryTokensCreateHandler::class                 => ConfigAbstractFactory::class,
            RegistryTokensDeleteHandler::class                 => ConfigAbstractFactory::class,
            RegistryTokensRegenerateHandler::class             => ConfigAbstractFactory::class,
            RegistryOverviewHandler::class                     => ConfigAbstractFactory::class,
            PackagesIndexHandler::class                        => ConfigAbstractFactory::class,
            PackageShowHandler::class                          => ConfigAbstractFactory::class,
            PackageDeleteHandler::class                        => ConfigAbstractFactory::class,
            ReleasesIndexHandler::class                        => ConfigAbstractFactory::class,
            ReleaseShowByVersionHandler::class                 => ConfigAbstractFactory::class,
            ReleaseActivityHandler::class                      => ConfigAbstractFactory::class,
            ReleasesDeleteHandler::class                       => ConfigAbstractFactory::class,
            SourceProvidersIndexHandler::class                 => ConfigAbstractFactory::class,
            SourceProviderInstancesIndexHandler::class         => ConfigAbstractFactory::class,
            SourceProviderInstancesCreateHandler::class        => ConfigAbstractFactory::class,
            SourceProviderInstanceUpdateHandler::class         => ConfigAbstractFactory::class,
            SourceProviderInstanceActivateHandler::class       => ConfigAbstractFactory::class,
            SourceProviderInstanceDeactivateHandler::class     => ConfigAbstractFactory::class,
            SourceProviderInstanceDeleteHandler::class         => ConfigAbstractFactory::class,
            ProviderAuthorizationsIndexHandler::class          => ConfigAbstractFactory::class,
            ProviderAuthorizationsCreateHandler::class         => ConfigAbstractFactory::class,
            ProviderAuthorizationResourcesHandler::class       => ConfigAbstractFactory::class,
            ProviderAuthorizationDependenciesHandler::class    => ConfigAbstractFactory::class,
            ProviderAuthorizationDeleteHandler::class          => ConfigAbstractFactory::class,
            ProviderAuthorizationReassignDeleteHandler::class  => ConfigAbstractFactory::class,
            SourcesIndexHandler::class                         => ConfigAbstractFactory::class,
            SourcesCreateHandler::class                        => ConfigAbstractFactory::class,
            SourceShowHandler::class                           => ConfigAbstractFactory::class,
            SourceUpdateHandler::class                         => ConfigAbstractFactory::class,
            SourceSyncHandler::class                           => ConfigAbstractFactory::class,
            SourceArchiveHandler::class                        => ConfigAbstractFactory::class,
            SourceRestoreHandler::class                        => ConfigAbstractFactory::class,
            SourceDeleteHandler::class                         => ConfigAbstractFactory::class,
            SourceWebhookRegisterHandler::class                => ConfigAbstractFactory::class,
            SourceWebhookRotateSecretHandler::class            => ConfigAbstractFactory::class,
            SourceWebhookEventsIndexHandler::class             => ConfigAbstractFactory::class,
            SourceWebhookEventReprocessHandler::class          => ConfigAbstractFactory::class,
            ApiHealthHandler::class                            => ConfigAbstractFactory::class,
            CurrentUserHandler::class                          => ConfigAbstractFactory::class,
            OpenApiAccessGuard::class                          => ConfigAbstractFactory::class,
            OpenApiSpecGenerator::class                        => ConfigAbstractFactory::class,
            OpenApiJsonHandler::class                          => ConfigAbstractFactory::class,
            OpenApiUiHandler::class                            => ConfigAbstractFactory::class,
            OpenApiAssetHandler::class                         => ConfigAbstractFactory::class,
            ApiAuthenticationContextMiddleware::class          => ConfigAbstractFactory::class,
            ApiScopeMiddleware::class                          => ConfigAbstractFactory::class,
            ApiOrganizationContextMiddleware::class            => ConfigAbstractFactory::class,
            ApiAuditMiddleware::class                          => ConfigAbstractFactory::class,
            ApiValidationFailureResponseFactory::class         => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        ApiValidationFailureResponseFactory::class         => [
            ApiErrorResponseFactory::class,
        ],
        PaginationResponseFactory::class                   => [
            ApiResponseFactory::class,
        ],
        ApiAccessTokensIndexHandler::class                 => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        ApiAccessTokensCreateHandler::class                => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        ApiAccessTokensDeleteHandler::class                => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        ApiAccessTokenRegenerateHandler::class             => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        OrganizationsIndexHandler::class                   => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        OrganizationsCreateHandler::class                  => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        OrganizationShowHandler::class                     => [
            ApiResponseFactory::class,
        ],
        OrganizationUpdateHandler::class                   => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        OrganizationDeleteHandler::class                   => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        OrganizationMembersIndexHandler::class             => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        OrganizationMemberUpdateHandler::class             => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        OrganizationMemberDeleteHandler::class             => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        OrganizationInvitationsIndexHandler::class         => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        OrganizationInvitationsCreateHandler::class        => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        OrganizationInvitationsRevokeHandler::class        => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        OrganizationInvitationsDeleteHandler::class        => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        AdminJobsIndexHandler::class                       => [
            JobApiFacadeInterface::class,
            PaginationResponseFactory::class,
        ],
        AdminJobShowHandler::class                         => [
            JobApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        AdminJobRestartHandler::class                      => [
            JobApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        AdminUsersIndexHandler::class                      => [
            IdentityApiFacadeInterface::class,
            PaginationResponseFactory::class,
        ],
        AdminUserShowHandler::class                        => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        AdminUserUpdateHandler::class                      => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        AdminUserVerifyHandler::class                      => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        AdminOrganizationsIndexHandler::class              => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        AdminOrganizationShowHandler::class                => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        AdminOrganizationUpdateHandler::class              => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        AdminOrganizationDeleteHandler::class              => [
            OrganizationApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        AdminPackagesIndexHandler::class                   => [
            PackageApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        AdminSourcesIndexHandler::class                    => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        AdminSettingsIndexHandler::class                   => [
            ProjectSettingsApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        AdminSettingsUpdateHandler::class                  => [
            ProjectSettingsApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        RegistryTokensIndexHandler::class                  => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        RegistryTokensCreateHandler::class                 => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        RegistryTokensDeleteHandler::class                 => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        RegistryTokensRegenerateHandler::class             => [
            IdentityApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        RegistryOverviewHandler::class                     => [
            RegistryApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        PackagesIndexHandler::class                        => [
            PackageApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        PackageShowHandler::class                          => [
            PackageApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        PackageDeleteHandler::class                        => [
            PackageApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        ReleasesIndexHandler::class                        => [
            PackageApiFacadeInterface::class,
            ReleaseApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        ReleaseShowByVersionHandler::class                 => [
            ReleaseApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        ReleaseActivityHandler::class                      => [
            ReleaseApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        ReleasesDeleteHandler::class                       => [
            ReleaseApiFacadeInterface::class,
            ArchiveStorageCleanupApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        SourceProvidersIndexHandler::class                 => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        SourceProviderInstancesIndexHandler::class         => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        SourceProviderInstancesCreateHandler::class        => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        SourceProviderInstanceUpdateHandler::class         => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        SourceProviderInstanceActivateHandler::class       => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        SourceProviderInstanceDeactivateHandler::class     => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        SourceProviderInstanceDeleteHandler::class         => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        ProviderAuthorizationsIndexHandler::class          => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
        ],
        ProviderAuthorizationsCreateHandler::class         => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        ProviderAuthorizationResourcesHandler::class       => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        ProviderAuthorizationDependenciesHandler::class    => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        ProviderAuthorizationDeleteHandler::class          => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        ProviderAuthorizationReassignDeleteHandler::class  => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        SourcesIndexHandler::class                         => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            OrganizationPolicy::class,
            ApiScopeEvaluator::class,
        ],
        SourcesCreateHandler::class                        => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        SourceShowHandler::class                           => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            OrganizationPolicy::class,
            ApiScopeEvaluator::class,
        ],
        SourceUpdateHandler::class                         => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        SourceSyncHandler::class                           => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
            LoggerInterface::class,
        ],
        SourceArchiveHandler::class                        => [
            SourceApiFacadeInterface::class,
            PackageApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        SourceRestoreHandler::class                        => [
            SourceApiFacadeInterface::class,
            PackageApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        SourceDeleteHandler::class                         => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        SourceWebhookRegisterHandler::class                => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        SourceWebhookRotateSecretHandler::class            => [
            SourceApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        SourceWebhookEventsIndexHandler::class             => [
            SourceApiFacadeInterface::class,
            WebhookApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        SourceWebhookEventReprocessHandler::class          => [
            SourceApiFacadeInterface::class,
            WebhookApiFacadeInterface::class,
            ApiResponseFactory::class,
            ApiErrorResponseFactory::class,
        ],
        ApiHealthHandler::class                            => [
            ApiResponseFactory::class,
        ],
        CurrentUserHandler::class                          => [
            ApiResponseFactory::class,
        ],
        OpenApiAccessGuard::class                          => [
            IdentityApiFacadeInterface::class,
            ProjectSettingsApiFacadeInterface::class,
            ApiErrorResponseFactory::class,
            'config.api.docs',
        ],
        OpenApiSpecGenerator::class                        => [
            'config.api.docs',
        ],
        OpenApiJsonHandler::class                          => [
            ApiResponseFactory::class,
            OpenApiSpecGenerator::class,
            OpenApiAccessGuard::class,
        ],
        OpenApiUiHandler::class                            => [
            OpenApiAccessGuard::class,
        ],
        OpenApiAssetHandler::class                         => [
            ApiErrorResponseFactory::class,
            OpenApiAccessGuard::class,
            'config.api.docs',
        ],
        ApiAuthenticationContextMiddleware::class          => [
            AuthenticationProfileProviderInterface::class,
            ApiErrorResponseFactory::class,
            ApiAuthenticationAttemptLimiter::class,
        ],
        ApiScopeMiddleware::class                          => [
            ApiScopeEvaluator::class,
            ApiErrorResponseFactory::class,
        ],
        ApiOrganizationContextMiddleware::class            => [
            OrganizationApiFacadeInterface::class,
            ApiErrorResponseFactory::class,
        ],
        ApiAuditMiddleware::class                          => [
            AuditApiFacadeInterface::class,
            LoggerInterface::class,
        ],
    ],
];
