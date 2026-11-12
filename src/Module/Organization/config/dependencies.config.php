<?php

declare(strict_types=1);

use Common\Branding\BrandingConfig;
use Common\Security\SecretEncryptorInterface;
use Cycle\ORM\ORMInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Mezzio\Router\RouterInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Organization\Application\Command\OrganizationCommandService;
use Packio\Organization\Application\Command\OrganizationDeletionInitiator;
use Packio\Organization\Application\Command\OrganizationDeletionService;
use Packio\Organization\Application\Config\ReservedOrganizationSlugs;
use Packio\Organization\Application\Port\IdentityServicePort;
use Packio\Organization\Application\Port\OrganizationDeletionInitiationPort;
use Packio\Organization\Application\Port\OrganizationDeletionJobSchedulerPort;
use Packio\Organization\Application\Port\OrganizationDeletionJobStatusPort;
use Packio\Organization\Application\Port\PackageDeletionSchedulerPort;
use Packio\Organization\Application\Port\SourceDeletionSchedulerPort;
use Packio\Organization\Application\Port\UserDirectoryPort;
use Packio\Organization\Application\Query\OrganizationMembersViewQuery;
use Packio\Organization\Application\Query\OrganizationQueryService;
use Packio\Organization\Application\Query\OrganizationSummariesReaderInterface;
use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use Packio\Organization\Infrastructure\Adapter\IdentityServiceAdapter;
use Packio\Organization\Infrastructure\Adapter\IdentityUserDirectoryAdapter;
use Packio\Organization\Infrastructure\Adapter\OrganizationDeletionInitiationAdapter;
use Packio\Organization\Infrastructure\Adapter\OrganizationDeletionJobSchedulerAdapter;
use Packio\Organization\Infrastructure\Adapter\OrganizationDeletionJobStatusAdapter;
use Packio\Organization\Infrastructure\Adapter\PackageDeletionSchedulerAdapter;
use Packio\Organization\Infrastructure\Adapter\SourceDeletionSchedulerAdapter;
use Packio\Organization\Infrastructure\Adapter\Worker\OrganizationDeleteJobHandler;
use Packio\Organization\Infrastructure\Cli\OrganizationInvitationsEncryptCommand;
use Packio\Organization\Infrastructure\Facade\OrganizationApiFacade;
use Packio\Organization\Infrastructure\Handler\OrganizationCreateHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInvitationCreateHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInvitationDeleteHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInvitationRevokeHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInviteAcceptHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInviteAcceptSubmitHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationMemberRemoveHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationMemberRoleUpdateHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationMembersHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsDeleteHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsUpdateHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationsHandler;
use Packio\Organization\Infrastructure\ObjectMapper\InvitationTokenTransformer;
use Packio\Organization\Infrastructure\Persistence\Cycle\OrganizationInvitationRepository;
use Packio\Organization\Infrastructure\Persistence\Cycle\OrganizationMemberRepository;
use Packio\Organization\Infrastructure\Persistence\Cycle\OrganizationRepository;
use Packio\Organization\Infrastructure\Persistence\Cycle\OrganizationSummariesReader as CycleOrganizationSummariesReader;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Log\LoggerInterface;
use Sirix\Cycle\Factory\RepositoryFactory;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

return [
    'dependencies'               => [
        'aliases'    => [
            OrganizationSummariesReaderInterface::class             => CycleOrganizationSummariesReader::class,
            UserDirectoryPort::class                                => IdentityUserDirectoryAdapter::class,
            OrganizationDeletionInitiationPort::class               => OrganizationDeletionInitiationAdapter::class,
            OrganizationRepositoryInterface::class                  => OrganizationRepository::class,
            OrganizationMemberRepositoryInterface::class            => OrganizationMemberRepository::class,
            OrganizationInvitationRepositoryInterface::class        => OrganizationInvitationRepository::class,
            OrganizationApiFacadeInterface::class                   => OrganizationApiFacade::class,
            IdentityServicePort::class                              => IdentityServiceAdapter::class,
            SourceDeletionSchedulerPort::class                      => SourceDeletionSchedulerAdapter::class,
            PackageDeletionSchedulerPort::class                     => PackageDeletionSchedulerAdapter::class,
            OrganizationDeletionJobSchedulerPort::class             => OrganizationDeletionJobSchedulerAdapter::class,
            OrganizationDeletionJobStatusPort::class                => OrganizationDeletionJobStatusAdapter::class,
        ],
        'invokables' => [
            OrganizationPolicy::class => OrganizationPolicy::class,
        ],
        'factories'  => [
            CycleOrganizationSummariesReader::class        => ConfigAbstractFactory::class,
            OrganizationMembersViewQuery::class            => ConfigAbstractFactory::class,
            IdentityUserDirectoryAdapter::class            => ConfigAbstractFactory::class,
            OrganizationDeletionInitiator::class           => ConfigAbstractFactory::class,
            OrganizationDeletionInitiationAdapter::class   => ConfigAbstractFactory::class,
            OrganizationRepository::class                  => RepositoryFactory::class,
            OrganizationMemberRepository::class            => RepositoryFactory::class,
            OrganizationInvitationRepository::class        => RepositoryFactory::class,
            ReservedOrganizationSlugs::class               => ConfigAbstractFactory::class,
            OrganizationCommandService::class              => ConfigAbstractFactory::class,
            OrganizationQueryService::class                => ConfigAbstractFactory::class,
            IdentityServiceAdapter::class                  => ConfigAbstractFactory::class,
            SourceDeletionSchedulerAdapter::class          => ConfigAbstractFactory::class,
            PackageDeletionSchedulerAdapter::class         => ConfigAbstractFactory::class,
            OrganizationDeletionJobSchedulerAdapter::class => ConfigAbstractFactory::class,
            OrganizationDeletionJobStatusAdapter::class    => ConfigAbstractFactory::class,
            OrganizationDeletionService::class             => ConfigAbstractFactory::class,
            OrganizationDeleteJobHandler::class            => ConfigAbstractFactory::class,
            OrganizationApiFacade::class                   => ConfigAbstractFactory::class,
            OrganizationsHandler::class                    => ConfigAbstractFactory::class,
            OrganizationSettingsHandler::class             => ConfigAbstractFactory::class,
            OrganizationCreateHandler::class               => ConfigAbstractFactory::class,
            OrganizationMembersHandler::class              => ConfigAbstractFactory::class,
            OrganizationInvitationCreateHandler::class     => ConfigAbstractFactory::class,
            OrganizationInvitationRevokeHandler::class     => ConfigAbstractFactory::class,
            OrganizationInvitationDeleteHandler::class     => ConfigAbstractFactory::class,
            OrganizationInviteAcceptHandler::class         => ConfigAbstractFactory::class,
            OrganizationInviteAcceptSubmitHandler::class   => ConfigAbstractFactory::class,
            OrganizationMemberRemoveHandler::class         => ConfigAbstractFactory::class,
            OrganizationMemberRoleUpdateHandler::class     => ConfigAbstractFactory::class,
            OrganizationSettingsUpdateHandler::class       => ConfigAbstractFactory::class,
            OrganizationSettingsDeleteHandler::class       => ConfigAbstractFactory::class,
            OrganizationInvitationTokens::class            => ConfigAbstractFactory::class,
            InvitationTokenTransformer::class              => ConfigAbstractFactory::class,
            OrganizationInvitationsEncryptCommand::class   => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        CycleOrganizationSummariesReader::class        => [ORMInterface::class],
        OrganizationMembersViewQuery::class            => [OrganizationMemberRepositoryInterface::class, OrganizationInvitationRepositoryInterface::class, UserDirectoryPort::class, OrganizationInvitationTokens::class],
        IdentityUserDirectoryAdapter::class            => [IdentityApiFacadeInterface::class],
        OrganizationDeletionInitiator::class           => [
            OrganizationDeletionInitiationPort::class,
            LoggerInterface::class,
        ],
        OrganizationDeletionInitiationAdapter::class   => [
            OrganizationCommandService::class,
            JobApiFacadeInterface::class,
            AuditApiFacadeInterface::class,
            LoggerInterface::class,
        ],
        ReservedOrganizationSlugs::class               => [
            'config.organizations.reserved_slugs',
        ],
        OrganizationCommandService::class              => [
            OrganizationRepositoryInterface::class,
            OrganizationInvitationRepositoryInterface::class,
            OrganizationMemberRepositoryInterface::class,
            ReservedOrganizationSlugs::class,
            OrganizationInvitationTokens::class,
        ],
        OrganizationInvitationTokens::class            => [SecretEncryptorInterface::class],
        InvitationTokenTransformer::class              => [OrganizationInvitationTokens::class],
        OrganizationInvitationsEncryptCommand::class   => [
            OrganizationInvitationRepositoryInterface::class,
            OrganizationInvitationTokens::class,
        ],
        OrganizationQueryService::class                => [
            OrganizationRepositoryInterface::class,
            OrganizationInvitationRepositoryInterface::class,
            OrganizationMemberRepositoryInterface::class,
        ],
        IdentityServiceAdapter::class                  => [
            IdentityApiFacadeInterface::class,
        ],
        SourceDeletionSchedulerAdapter::class          => [
            SourceApiFacadeInterface::class,
        ],
        PackageDeletionSchedulerAdapter::class         => [
            PackageApiFacadeInterface::class,
        ],
        OrganizationDeletionJobSchedulerAdapter::class => [
            JobApiFacadeInterface::class,
            LoggerInterface::class,
        ],
        OrganizationDeletionJobStatusAdapter::class    => [
            JobApiFacadeInterface::class,
        ],
        OrganizationDeletionService::class             => [
            OrganizationRepositoryInterface::class,
            OrganizationInvitationRepositoryInterface::class,
            OrganizationMemberRepositoryInterface::class,
            IdentityServicePort::class,
            SourceDeletionSchedulerPort::class,
            PackageDeletionSchedulerPort::class,
            OrganizationDeletionJobSchedulerPort::class,
            OrganizationDeletionJobStatusPort::class,
        ],
        OrganizationApiFacade::class                   => [
            OrganizationCommandService::class,
            OrganizationQueryService::class,
            ObjectMapperInterface::class,
            OrganizationDeletionInitiator::class,
            OrganizationDeletionInitiationPort::class,
            OrganizationDeletionJobSchedulerPort::class,
            OrganizationSummariesReaderInterface::class,
        ],
        OrganizationDeleteJobHandler::class            => [
            OrganizationDeletionService::class,
        ],
        OrganizationsHandler::class                    => [
            OrganizationApiFacadeInterface::class,
        ],
        OrganizationSettingsHandler::class             => [
            OrganizationApiFacadeInterface::class,
            SourceApiFacadeInterface::class,
        ],
        OrganizationCreateHandler::class               => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            ValidationRedirectState::class,
        ],
        OrganizationMembersHandler::class              => [
            OrganizationMembersViewQuery::class,
            RouterInterface::class,
            BrandingConfig::class,
            OrganizationPolicy::class,
        ],
        OrganizationInvitationCreateHandler::class     => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            ValidationRedirectState::class,
        ],
        OrganizationInvitationRevokeHandler::class     => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        OrganizationInvitationDeleteHandler::class     => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        OrganizationInviteAcceptHandler::class         => [
            OrganizationApiFacadeInterface::class,
        ],
        OrganizationInviteAcceptSubmitHandler::class   => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        OrganizationMemberRemoveHandler::class         => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        OrganizationMemberRoleUpdateHandler::class     => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        OrganizationSettingsUpdateHandler::class       => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        OrganizationSettingsDeleteHandler::class       => [
            OrganizationApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
    ],
    'worker'                     => [
        'job_handlers' => [
            'organization.delete' => OrganizationDeleteJobHandler::class,
        ],
    ],
];
