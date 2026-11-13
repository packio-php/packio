<?php

declare(strict_types=1);

use Common\Branding\BrandingConfig;
use Common\Provider\SourceProviderMetadataCatalog;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Api\RuntimeSettingKeysApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingCatalog;
use Packio\Admin\Application\Settings\RuntimeSettingsService;
use Packio\Admin\Domain\Repository\ProjectSettingRepositoryInterface;
use Packio\Admin\Infrastructure\Facade\ProjectSettingsApiFacade;
use Packio\Admin\Infrastructure\Facade\RuntimeSettingKeysApiFacade;
use Packio\Admin\Infrastructure\Handler\AdminDashboardHandler;
use Packio\Admin\Infrastructure\Handler\AdminInvitationCreateHandler;
use Packio\Admin\Infrastructure\Handler\AdminInvitationDeleteHandler;
use Packio\Admin\Infrastructure\Handler\AdminInvitationRevokeHandler;
use Packio\Admin\Infrastructure\Handler\AdminJobRestartHandler;
use Packio\Admin\Infrastructure\Handler\AdminJobsHandler;
use Packio\Admin\Infrastructure\Handler\AdminJobShowHandler;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationDeleteHandler;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationsHandler;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationShowHandler;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationUpdateHandler;
use Packio\Admin\Infrastructure\Handler\AdminPackagesHandler;
use Packio\Admin\Infrastructure\Handler\AdminSettingsHandler;
use Packio\Admin\Infrastructure\Handler\AdminSettingsUpdateHandler;
use Packio\Admin\Infrastructure\Handler\AdminSourceCancelDeleteHandler;
use Packio\Admin\Infrastructure\Handler\AdminSourceDeleteHandler;
use Packio\Admin\Infrastructure\Handler\AdminSourceDeletionPreviewHandler;
use Packio\Admin\Infrastructure\Handler\AdminSourceForceFinalizeDeleteHandler;
use Packio\Admin\Infrastructure\Handler\AdminSourceForceRequeueDeleteHandler;
use Packio\Admin\Infrastructure\Handler\AdminSourceRequeueDeleteHandler;
use Packio\Admin\Infrastructure\Handler\AdminSourcesHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserBanHandler;
use Packio\Admin\Infrastructure\Handler\AdminUsersHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserShowHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserUnbanHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserUpdateHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserVerifyHandler;
use Packio\Admin\Infrastructure\Page\AdminJobPresenter;
use Packio\Admin\Infrastructure\Page\AdminOrganizationPresenter;
use Packio\Admin\Infrastructure\Page\AdminPackagesPageDataBuilder;
use Packio\Admin\Infrastructure\Page\AdminSourcesPageDataBuilder;
use Packio\Admin\Infrastructure\Persistence\Cycle\ProjectSettingRepository;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryService;
use Packio\Admin\Infrastructure\UserModeration\UserModerationService;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestPresentationPolicy;
use Packio\Application\Service\InitialListViewModeResolver;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Log\LoggerInterface;
use Sirix\Cycle\Factory\RepositoryFactory;

return [
    'dependencies'               => [
        'aliases'    => [
            ProjectSettingRepositoryInterface::class    => ProjectSettingRepository::class,
            ProjectSettingsApiFacadeInterface::class    => ProjectSettingsApiFacade::class,
            RuntimeSettingKeysApiFacadeInterface::class => RuntimeSettingKeysApiFacade::class,
        ],
        'invokables' => [
            AdminJobPresenter::class          => AdminJobPresenter::class,
            AdminOrganizationPresenter::class => AdminOrganizationPresenter::class,
        ],
        'factories'  => [
            ProjectSettingRepository::class               => RepositoryFactory::class,
            RuntimeSettingCatalog::class                  => ConfigAbstractFactory::class,
            RuntimeSettingsService::class                 => ConfigAbstractFactory::class,
            ProjectSettingsApiFacade::class               => ConfigAbstractFactory::class,
            RuntimeSettingKeysApiFacade::class            => ConfigAbstractFactory::class,
            UserModerationService::class                  => ConfigAbstractFactory::class,
            SourceDeletionRecoveryService::class          => ConfigAbstractFactory::class,
            AdminDashboardHandler::class                  => ConfigAbstractFactory::class,
            AdminOrganizationsHandler::class              => ConfigAbstractFactory::class,
            AdminOrganizationShowHandler::class           => ConfigAbstractFactory::class,
            AdminOrganizationUpdateHandler::class         => ConfigAbstractFactory::class,
            AdminOrganizationDeleteHandler::class         => ConfigAbstractFactory::class,
            AdminPackagesPageDataBuilder::class           => ConfigAbstractFactory::class,
            AdminSourcesPageDataBuilder::class            => ConfigAbstractFactory::class,
            AdminPackagesHandler::class                   => ConfigAbstractFactory::class,
            AdminSourcesHandler::class                    => ConfigAbstractFactory::class,
            AdminSourceRequeueDeleteHandler::class        => ConfigAbstractFactory::class,
            AdminSourceForceRequeueDeleteHandler::class   => ConfigAbstractFactory::class,
            AdminSourceCancelDeleteHandler::class         => ConfigAbstractFactory::class,
            AdminSourceDeleteHandler::class               => ConfigAbstractFactory::class,
            AdminSourceDeletionPreviewHandler::class      => ConfigAbstractFactory::class,
            AdminSourceForceFinalizeDeleteHandler::class  => ConfigAbstractFactory::class,
            AdminJobsHandler::class                       => ConfigAbstractFactory::class,
            AdminJobShowHandler::class                    => ConfigAbstractFactory::class,
            AdminJobRestartHandler::class                 => ConfigAbstractFactory::class,
            AdminInvitationCreateHandler::class           => ConfigAbstractFactory::class,
            AdminInvitationRevokeHandler::class           => ConfigAbstractFactory::class,
            AdminInvitationDeleteHandler::class           => ConfigAbstractFactory::class,
            AdminSettingsHandler::class                   => ConfigAbstractFactory::class,
            AdminSettingsUpdateHandler::class             => ConfigAbstractFactory::class,
            AdminUsersHandler::class                      => ConfigAbstractFactory::class,
            AdminUserBanHandler::class                    => ConfigAbstractFactory::class,
            AdminUserShowHandler::class                   => ConfigAbstractFactory::class,
            AdminUserUnbanHandler::class                  => ConfigAbstractFactory::class,
            AdminUserUpdateHandler::class                 => ConfigAbstractFactory::class,
            AdminUserVerifyHandler::class                 => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        RuntimeSettingCatalog::class                  => [
            SourceProviderMetadataCatalog::class,
        ],
        RuntimeSettingsService::class                 => [
            ProjectSettingRepositoryInterface::class,
            RuntimeSettingCatalog::class,
        ],
        RuntimeSettingKeysApiFacade::class            => [
            RuntimeSettingCatalog::class,
        ],
        ProjectSettingsApiFacade::class               => [
            RuntimeSettingsService::class,
            'config',
        ],
        UserModerationService::class                  => [
            IdentityApiFacadeInterface::class,
            AuditApiFacadeInterface::class,
            LoggerInterface::class,
        ],
        SourceDeletionRecoveryService::class          => [
            SourceApiFacadeInterface::class,
            JobApiFacadeInterface::class,
            PackageApiFacadeInterface::class,
            ReleaseApiFacadeInterface::class,
            ArchiveStorageCleanupApiFacadeInterface::class,
            WebhookApiFacadeInterface::class,
            AuditApiFacadeInterface::class,
            LoggerInterface::class,
        ],
        AdminDashboardHandler::class                  => [
            IdentityApiFacadeInterface::class,
            OrganizationApiFacadeInterface::class,
            PackageApiFacadeInterface::class,
            SourceApiFacadeInterface::class,
        ],
        AdminOrganizationsHandler::class              => [
            OrganizationApiFacadeInterface::class,
        ],
        AdminOrganizationShowHandler::class           => [
            OrganizationApiFacadeInterface::class,
            IdentityApiFacadeInterface::class,
            SourceApiFacadeInterface::class,
            PackageApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        AdminOrganizationUpdateHandler::class         => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            AuditApiFacadeInterface::class,
        ],
        AdminOrganizationDeleteHandler::class         => [
            OrganizationApiFacadeInterface::class,
            LoggerInterface::class,
            InertiaNotificationMessenger::class,
        ],
        AdminPackagesPageDataBuilder::class           => [
            PackageApiFacadeInterface::class,
            SourceApiFacadeInterface::class,
            OrganizationApiFacadeInterface::class,
            InitialListViewModeResolver::class,
            AdminOrganizationPresenter::class,
        ],
        AdminSourcesPageDataBuilder::class            => [
            SourceApiFacadeInterface::class,
            OrganizationApiFacadeInterface::class,
            SourceDeletionRecoveryService::class,
            InitialListViewModeResolver::class,
            AdminOrganizationPresenter::class,
        ],
        AdminPackagesHandler::class                   => [
            AdminPackagesPageDataBuilder::class,
        ],
        AdminSourcesHandler::class                    => [
            AdminSourcesPageDataBuilder::class,
        ],
        AdminSourceRequeueDeleteHandler::class        => [
            SourceDeletionRecoveryService::class,
            InertiaNotificationMessenger::class,
        ],
        AdminSourceForceRequeueDeleteHandler::class   => [
            SourceDeletionRecoveryService::class,
            InertiaNotificationMessenger::class,
        ],
        AdminSourceCancelDeleteHandler::class         => [
            SourceDeletionRecoveryService::class,
            InertiaNotificationMessenger::class,
        ],
        AdminSourceDeleteHandler::class               => [
            SourceApiFacadeInterface::class,
            SourceDeletionRecoveryService::class,
            LoggerInterface::class,
            InertiaNotificationMessenger::class,
        ],
        AdminSourceDeletionPreviewHandler::class      => [
            SourceApiFacadeInterface::class,
            SourceDeletionRecoveryService::class,
        ],
        AdminSourceForceFinalizeDeleteHandler::class  => [
            SourceDeletionRecoveryService::class,
            InertiaNotificationMessenger::class,
        ],
        AdminJobsHandler::class                       => [
            JobApiFacadeInterface::class,
            OrganizationApiFacadeInterface::class,
            AdminJobPresenter::class,
            AdminOrganizationPresenter::class,
        ],
        AdminJobShowHandler::class                    => [
            JobApiFacadeInterface::class,
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            AdminJobPresenter::class,
            AdminOrganizationPresenter::class,
        ],
        AdminJobRestartHandler::class                 => [
            JobApiFacadeInterface::class,
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        AdminInvitationCreateHandler::class           => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            AuditApiFacadeInterface::class,
        ],
        AdminInvitationRevokeHandler::class           => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            AuditApiFacadeInterface::class,
        ],
        AdminInvitationDeleteHandler::class           => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            AuditApiFacadeInterface::class,
        ],
        AdminSettingsHandler::class                   => [
            ProjectSettingsApiFacadeInterface::class,
            BrandingConfig::class,
            'config.packio',
        ],
        AdminSettingsUpdateHandler::class             => [
            ProjectSettingsApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            AuditApiFacadeInterface::class,
        ],
        AdminUsersHandler::class                      => [
            IdentityApiFacadeInterface::class,
        ],
        AdminUserBanHandler::class                    => [
            UserModerationService::class,
            InertiaNotificationMessenger::class,
            RequestPresentationPolicy::class,
        ],
        AdminUserShowHandler::class                   => [
            IdentityApiFacadeInterface::class,
            UserModerationService::class,
            InertiaNotificationMessenger::class,
        ],
        AdminUserUnbanHandler::class                  => [
            UserModerationService::class,
            InertiaNotificationMessenger::class,
            RequestPresentationPolicy::class,
        ],
        AdminUserUpdateHandler::class                 => [
            IdentityApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            AuditApiFacadeInterface::class,
        ],
        AdminUserVerifyHandler::class                 => [
            IdentityApiFacadeInterface::class,
            AuditApiFacadeInterface::class,
        ],
    ],
];
