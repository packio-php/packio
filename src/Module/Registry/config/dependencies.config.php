<?php

declare(strict_types=1);

use Aws\S3\S3ClientInterface;
use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\SafeGitEnvironment;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Infrastructure\Process\LimitedProcessRunner;
use Common\Infrastructure\Resource\ResourceLimits;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Registry\Application\Port\JobSchedulerPort;
use Packio\Registry\Application\Port\PackageQueryPort;
use Packio\Registry\Application\Port\ReleaseQueryPort;
use Packio\Registry\Application\Port\ReleaseStorageMetadataPort;
use Packio\Registry\Application\Port\SourceQueryPort;
use Packio\Registry\Application\Query\RegistryQueryService;
use Packio\Registry\Application\Service\DistArchiveFetcherInterface;
use Packio\Registry\Application\Service\PackageArchiveCleanupScheduler;
use Packio\Registry\Application\Service\RegistryOrganizationResolver;
use Packio\Registry\Application\Service\RegistryTokenPermissionEvaluator;
use Packio\Registry\Application\Service\ReleaseArchiveDescriptorInterface;
use Packio\Registry\Application\Service\ReleaseArchiveStorageService;
use Packio\Registry\Application\Service\TokenPermissionEvaluatorInterface;
use Packio\Registry\Application\Storage\PackageArchiveKeyGenerator;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Registry\Infrastructure\Adapter\PackageApiFacadeAdapter;
use Packio\Registry\Infrastructure\Adapter\ReleaseApiFacadeAdapter;
use Packio\Registry\Infrastructure\Adapter\SourceApiFacadeAdapter;
use Packio\Registry\Infrastructure\Adapter\Worker\CleanupPackageArchivesJobHandler;
use Packio\Registry\Infrastructure\Adapter\Worker\StoreReleaseArchiveJobHandler;
use Packio\Registry\Infrastructure\Adapter\WorkerJobSchedulerAdapter;
use Packio\Registry\Infrastructure\Authorization\RegistryProtocolAuthorizer;
use Packio\Registry\Infrastructure\BitbucketDistArchiveFetcher;
use Packio\Registry\Infrastructure\Cli\ArchiveBackfillCommand;
use Packio\Registry\Infrastructure\Cli\ArchiveDevCleanupCommand;
use Packio\Registry\Infrastructure\CodebergDistArchiveFetcher;
use Packio\Registry\Infrastructure\DistArchiveFetcher;
use Packio\Registry\Infrastructure\Facade\ArchiveStorageApiFacade;
use Packio\Registry\Infrastructure\Facade\ArchiveStorageCleanupApiFacade;
use Packio\Registry\Infrastructure\Facade\RegistryApiFacade;
use Packio\Registry\Infrastructure\GenericGitDistArchiveFetcher;
use Packio\Registry\Infrastructure\GitlabDistArchiveFetcher;
use Packio\Registry\Infrastructure\GitlabSelfHostedDistArchiveFetcher;
use Packio\Registry\Infrastructure\Handler\RegistryDistArchiveHandler;
use Packio\Registry\Infrastructure\Handler\RegistryPackageMetadataHandler;
use Packio\Registry\Infrastructure\Handler\RegistryPackagesHandler;
use Packio\Registry\Infrastructure\Handler\RegistryPrivatePackagesHandler;
use Packio\Registry\Infrastructure\Handler\RegistrySetupHandler;
use Packio\Registry\Infrastructure\HttpDistArchiveDownloader;
use Packio\Registry\Infrastructure\Middleware\RequireRegistryAccessMiddleware;
use Packio\Registry\Infrastructure\Middleware\ResolveRegistryOrganizationAttributeMiddleware;
use Packio\Registry\Infrastructure\Middleware\RestrictRegistrySubdomainMiddleware;
use Packio\Registry\Infrastructure\Page\RegistrySetupPageDataBuilder;
use Packio\Registry\Infrastructure\RegistryMetadataUrlNormalizer;
use Packio\Registry\Infrastructure\Storage\PackageArchiveKeyGeneratorFactory;
use Packio\Registry\Infrastructure\Storage\PackageArchiveStorageFactory;
use Packio\Registry\Infrastructure\Storage\S3ClientFactory;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Rbac\PermissionMatcher;

return [
    'dependencies'               => [
        'aliases'   => [
            ArchiveStorageCleanupApiFacadeInterface::class      => ArchiveStorageCleanupApiFacade::class,
            RegistryApiFacadeInterface::class                   => RegistryApiFacade::class,
            ArchiveStorageApiFacadeInterface::class             => ArchiveStorageApiFacade::class,
            DistArchiveFetcherInterface::class                  => DistArchiveFetcher::class,
            JobSchedulerPort::class                             => WorkerJobSchedulerAdapter::class,
            PackageQueryPort::class                             => PackageApiFacadeAdapter::class,
            ReleaseArchiveDescriptorInterface::class            => RegistryQueryService::class,
            ReleaseQueryPort::class                             => ReleaseApiFacadeAdapter::class,
            ReleaseStorageMetadataPort::class                   => ReleaseApiFacadeAdapter::class,
            SourceQueryPort::class                              => SourceApiFacadeAdapter::class,
            TokenPermissionEvaluatorInterface::class            => RegistryTokenPermissionEvaluator::class,
        ],
        'factories' => [
            RegistryMetadataUrlNormalizer::class                        => ConfigAbstractFactory::class,
            PackageArchiveStorageInterface::class                       => PackageArchiveStorageFactory::class,
            PackageArchiveKeyGenerator::class                           => PackageArchiveKeyGeneratorFactory::class,
            ReleaseArchiveStorageService::class                         => ConfigAbstractFactory::class,
            S3ClientInterface::class                                    => S3ClientFactory::class,
            RegistryTokenPermissionEvaluator::class                     => ConfigAbstractFactory::class,
            RegistryProtocolAuthorizer::class                           => ConfigAbstractFactory::class,
            RegistrySetupPageDataBuilder::class                         => ConfigAbstractFactory::class,
            RegistrySetupHandler::class                                 => ConfigAbstractFactory::class,
            RegistryQueryService::class                                 => ConfigAbstractFactory::class,
            RegistryOrganizationResolver::class                         => ConfigAbstractFactory::class,
            WorkerJobSchedulerAdapter::class                            => ConfigAbstractFactory::class,
            PackageApiFacadeAdapter::class                              => ConfigAbstractFactory::class,
            ReleaseApiFacadeAdapter::class                              => ConfigAbstractFactory::class,
            SourceApiFacadeAdapter::class                               => ConfigAbstractFactory::class,
            PackageArchiveCleanupScheduler::class                       => ConfigAbstractFactory::class,
            ArchiveStorageCleanupApiFacade::class                       => ConfigAbstractFactory::class,
            RegistryApiFacade::class                                    => ConfigAbstractFactory::class,
            ArchiveStorageApiFacade::class                              => ConfigAbstractFactory::class,
            GitlabDistArchiveFetcher::class                             => ConfigAbstractFactory::class,
            GitlabSelfHostedDistArchiveFetcher::class                   => ConfigAbstractFactory::class,
            BitbucketDistArchiveFetcher::class                          => ConfigAbstractFactory::class,
            CodebergDistArchiveFetcher::class                           => ConfigAbstractFactory::class,
            HttpDistArchiveDownloader::class                            => ConfigAbstractFactory::class,
            GenericGitDistArchiveFetcher::class                         => ConfigAbstractFactory::class,
            DistArchiveFetcher::class                                   => ConfigAbstractFactory::class,
            ArchiveBackfillCommand::class                               => ConfigAbstractFactory::class,
            ArchiveDevCleanupCommand::class                             => ConfigAbstractFactory::class,
            CleanupPackageArchivesJobHandler::class                     => ConfigAbstractFactory::class,
            StoreReleaseArchiveJobHandler::class                        => ConfigAbstractFactory::class,
            RegistryPackagesHandler::class                              => ConfigAbstractFactory::class,
            RegistryPrivatePackagesHandler::class                       => ConfigAbstractFactory::class,
            RegistryPackageMetadataHandler::class                       => ConfigAbstractFactory::class,
            RegistryDistArchiveHandler::class                           => ConfigAbstractFactory::class,
            ResolveRegistryOrganizationAttributeMiddleware::class       => ConfigAbstractFactory::class,
            RestrictRegistrySubdomainMiddleware::class                  => ConfigAbstractFactory::class,
            RequireRegistryAccessMiddleware::class                      => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        RegistryMetadataUrlNormalizer::class                        => [BrandingConfig::class],
        RegistrySetupPageDataBuilder::class                         => [
            RegistryApiFacadeInterface::class,
            BrandingConfig::class,
        ],
        RegistrySetupHandler::class                                 => [
            RegistrySetupPageDataBuilder::class,
        ],
        RegistryQueryService::class                                 => [
            PackageQueryPort::class,
            ReleaseQueryPort::class,
            SourceQueryPort::class,
        ],
        PackageApiFacadeAdapter::class                              => [
            PackageApiFacadeInterface::class,
        ],
        ReleaseApiFacadeAdapter::class                              => [
            ReleaseApiFacadeInterface::class,
        ],
        SourceApiFacadeAdapter::class                               => [
            SourceApiFacadeInterface::class,
            SourceSecretResolverInterface::class,
        ],
        RegistryOrganizationResolver::class                         => [
            BrandingConfig::class,
        ],
        ArchiveStorageApiFacade::class                              => [
            'config',
        ],
        RegistryApiFacade::class                                    => [
            RegistryQueryService::class,
        ],
        WorkerJobSchedulerAdapter::class                            => [
            JobApiFacadeInterface::class,
        ],
        PackageArchiveCleanupScheduler::class                       => [
            ReleaseStorageMetadataPort::class,
            JobSchedulerPort::class,
            LoggerInterface::class,
        ],
        ArchiveStorageCleanupApiFacade::class                       => [
            PackageArchiveCleanupScheduler::class,
        ],
        GitlabDistArchiveFetcher::class                             => [
            GenericGitDistArchiveFetcher::class,
        ],
        GitlabSelfHostedDistArchiveFetcher::class                   => [
            BrandingConfig::class,
            HttpDistArchiveDownloader::class,
        ],
        BitbucketDistArchiveFetcher::class                          => [
            GenericGitDistArchiveFetcher::class,
        ],
        CodebergDistArchiveFetcher::class                           => [
            BrandingConfig::class,
            HttpDistArchiveDownloader::class,
        ],
        HttpDistArchiveDownloader::class                            => [
            UpstreamHttpClient::class,
        ],
        GenericGitDistArchiveFetcher::class                         => [SafeGitEnvironment::class, LimitedProcessRunner::class, ResourceLimits::class],
        DistArchiveFetcher::class                                   => [
            BrandingConfig::class,
            GitlabDistArchiveFetcher::class,
            GitlabSelfHostedDistArchiveFetcher::class,
            BitbucketDistArchiveFetcher::class,
            CodebergDistArchiveFetcher::class,
            GenericGitDistArchiveFetcher::class,
            HttpDistArchiveDownloader::class,
        ],
        StoreReleaseArchiveJobHandler::class                        => [
            ReleaseArchiveStorageService::class,
        ],
        CleanupPackageArchivesJobHandler::class                     => [
            PackageArchiveStorageInterface::class,
        ],
        ReleaseArchiveStorageService::class                         => [
            ReleaseArchiveDescriptorInterface::class,
            PackageArchiveStorageInterface::class,
            PackageArchiveKeyGenerator::class,
            DistArchiveFetcherInterface::class,
            ReleaseStorageMetadataPort::class,
            SourceQueryPort::class,
            'config.storage.archive.driver',
            'config.storage.archive.max_archive_bytes',
            'config.storage.archive.fallback_to_source_on_unavailable',
            LoggerInterface::class,
        ],
        ArchiveBackfillCommand::class                               => [
            OrganizationApiFacadeInterface::class,
            PackageQueryPort::class,
            ReleaseQueryPort::class,
            ReleaseStorageMetadataPort::class,
            JobApiFacadeInterface::class,
        ],
        ArchiveDevCleanupCommand::class                             => [
            OrganizationApiFacadeInterface::class,
            PackageQueryPort::class,
            ReleaseQueryPort::class,
            PackageArchiveStorageInterface::class,
            PackageArchiveKeyGenerator::class,
        ],
        RegistryPackagesHandler::class                              => [
            RegistryApiFacadeInterface::class,
            RegistryMetadataUrlNormalizer::class,
        ],
        RegistryPrivatePackagesHandler::class                       => [
            RegistryApiFacadeInterface::class,
            RegistryMetadataUrlNormalizer::class,
        ],
        RegistryPackageMetadataHandler::class                       => [
            RegistryApiFacadeInterface::class,
            RegistryMetadataUrlNormalizer::class,
        ],
        RegistryDistArchiveHandler::class                           => [
            ReleaseArchiveStorageService::class,
            EventDispatcherInterface::class,
            ResponseFactoryInterface::class,
        ],
        ResolveRegistryOrganizationAttributeMiddleware::class       => [
            RegistryOrganizationResolver::class,
        ],
        RequireRegistryAccessMiddleware::class                      => [
            BrandingConfig::class,
            RegistryProtocolAuthorizer::class,
        ],
        RegistryProtocolAuthorizer::class                           => [
            IdentityApiFacadeInterface::class,
            TokenPermissionEvaluatorInterface::class,
            PackageQueryPort::class,
            OrganizationApiFacadeInterface::class,
            RateLimiterInterface::class,
            ClientIpResolverInterface::class,
            'config.security.rate_limits',
        ],
        RestrictRegistrySubdomainMiddleware::class                  => [
            RegistryOrganizationResolver::class,
            BrandingConfig::class,
        ],
        RegistryTokenPermissionEvaluator::class                     => [
            PermissionMatcher::class,
        ],
    ],
    'worker'                     => [
        'job_handlers' => [
            CleanupPackageArchivesJobHandler::TYPE => CleanupPackageArchivesJobHandler::class,
            StoreReleaseArchiveJobHandler::TYPE    => StoreReleaseArchiveJobHandler::class,
        ],
    ],
];
