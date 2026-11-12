<?php

declare(strict_types=1);

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\SafeGitEnvironment;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Infrastructure\Process\LimitedProcessRunner;
use Common\Security\StoredSecretResolver;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Publication\Api\PublicationApiFacadeInterface;
use Packio\Publication\Application\Command\ConnectSourceService;
use Packio\Publication\Application\Command\ConnectSourcesUseCase;
use Packio\Publication\Application\Port\SourceConnectPort;
use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Service\ComposerVersionResolver;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Application\Synchronizer\SourcePublicationSynchronizerRegistry;
use Packio\Publication\Infrastructure\Adapter\PublicationSynchronizationAdapter;
use Packio\Publication\Infrastructure\Adapter\SourceConnectAdapter;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Bitbucket\HttpBitbucketComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Codeberg\CodebergComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Codeberg\HttpCodebergComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateProvider;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateProviderInterface;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateResolver;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateResolverInterface;
use Packio\Publication\Infrastructure\DependencyInjection\SourcePublicationSynchronizerRegistryFactory;
use Packio\Publication\Infrastructure\Facade\PublicationApiFacade;
use Packio\Publication\Infrastructure\GenericGit\GenericGitComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\GenericGit\HttpGenericGitComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Git\GitCommandRunner;
use Packio\Publication\Infrastructure\Git\GitCommandRunnerInterface;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use Packio\Publication\Infrastructure\Github\GithubComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Github\HttpGithubComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Gitlab\GitlabComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Gitlab\HttpGitlabComposerMetadataFetcher;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\GitlabSelfHosted\HttpGitlabSelfHostedComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Handler\SourceConnectHandler;
use Packio\Publication\Infrastructure\Readme\Base64FileContentDecoder;
use Packio\Publication\Infrastructure\Readme\BitbucketPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\BitbucketPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Readme\CodebergPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\CodebergPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Readme\GenericGitPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\GenericGitPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Readme\GithubPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\GitlabPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\GitlabPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Readme\GitlabSelfHostedPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\GitlabSelfHostedPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Readme\MarkdownToHtmlRenderer;
use Packio\Publication\Infrastructure\Readme\PackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Readme\ReadmeCandidateProvider;
use Packio\Publication\Infrastructure\Readme\ReadmeExtractorTemplate;
use Packio\Publication\Infrastructure\Request\ConnectSourcesCommandFactory;
use Packio\Publication\Infrastructure\Synchronizer\BitbucketPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\CodebergPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\GenericGitPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\GithubPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\GitlabPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\GitlabSelfHostedPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationSynchronizer;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Log\LoggerInterface;

return [
    'dependencies'               => [
        'aliases'    => [
            SourceConnectPort::class                                => SourceConnectAdapter::class,
            PublicationApiFacadeInterface::class                    => PublicationApiFacade::class,
            GithubComposerMetadataFetcherInterface::class           => HttpGithubComposerMetadataFetcher::class,
            GitlabComposerMetadataFetcherInterface::class           => HttpGitlabComposerMetadataFetcher::class,
            GitlabSelfHostedComposerMetadataFetcherInterface::class => HttpGitlabSelfHostedComposerMetadataFetcher::class,
            GenericGitComposerMetadataFetcherInterface::class       => HttpGenericGitComposerMetadataFetcher::class,
            BitbucketComposerMetadataFetcherInterface::class        => HttpBitbucketComposerMetadataFetcher::class,
            CodebergComposerMetadataFetcherInterface::class         => HttpCodebergComposerMetadataFetcher::class,
            ComposerReleaseDateProviderInterface::class             => ComposerReleaseDateProvider::class,
            ComposerReleaseDateResolverInterface::class             => ComposerReleaseDateResolver::class,
            GitCommandRunnerInterface::class                        => GitCommandRunner::class,
            PackageReadmeExtractorInterface::class                  => GithubPackageReadmeExtractor::class,
            GitlabPackageReadmeExtractorInterface::class            => GitlabPackageReadmeExtractor::class,
            GitlabSelfHostedPackageReadmeExtractorInterface::class  => GitlabSelfHostedPackageReadmeExtractor::class,
            GenericGitPackageReadmeExtractorInterface::class        => GenericGitPackageReadmeExtractor::class,
            BitbucketPackageReadmeExtractorInterface::class         => BitbucketPackageReadmeExtractor::class,
            CodebergPackageReadmeExtractorInterface::class          => CodebergPackageReadmeExtractor::class,

            // Port aliases
            SynchronizerDependenciesPort::class                     => PublicationSynchronizationAdapter::class,
        ],
        'invokables' => [
            ConnectSourcesCommandFactory::class => ConnectSourcesCommandFactory::class,
            ComposerVersionResolver::class      => ComposerVersionResolver::class,
            ComposerReleaseDateResolver::class  => ComposerReleaseDateResolver::class,
            Base64FileContentDecoder::class     => Base64FileContentDecoder::class,
            MarkdownToHtmlRenderer::class       => MarkdownToHtmlRenderer::class,
            ReadmeCandidateProvider::class      => ReadmeCandidateProvider::class,
        ],
        'factories'  => [
            GitCommandRunner::class                              => ConfigAbstractFactory::class,
            ConnectSourcesUseCase::class                         => ConfigAbstractFactory::class,
            SourceConnectAdapter::class                          => ConfigAbstractFactory::class,
            // --- Facade ---
            PublicationApiFacade::class                          => ConfigAbstractFactory::class,
            ConnectSourceService::class                          => ConfigAbstractFactory::class,
            PackageReadmeSyncService::class                      => ConfigAbstractFactory::class,
            ComposerReleaseDateProvider::class                   => ConfigAbstractFactory::class,

            // --- Adapters ---
            PublicationSynchronizationAdapter::class             => ConfigAbstractFactory::class,

            // --- Synchronizers ---
            SourcePublicationSynchronizerRegistry::class         => SourcePublicationSynchronizerRegistryFactory::class,
            ProviderPublicationSynchronizer::class               => ConfigAbstractFactory::class,
            GithubPublicationAdapter::class                      => ConfigAbstractFactory::class,
            GitlabPublicationAdapter::class                      => ConfigAbstractFactory::class,
            GitlabSelfHostedPublicationAdapter::class            => ConfigAbstractFactory::class,
            GenericGitPublicationAdapter::class                  => ConfigAbstractFactory::class,
            BitbucketPublicationAdapter::class                   => ConfigAbstractFactory::class,
            CodebergPublicationAdapter::class                    => ConfigAbstractFactory::class,

            // --- Fetchers ---
            HttpGithubComposerMetadataFetcher::class             => ConfigAbstractFactory::class,
            HttpGitlabComposerMetadataFetcher::class             => ConfigAbstractFactory::class,
            HttpGitlabSelfHostedComposerMetadataFetcher::class   => ConfigAbstractFactory::class,
            HttpGenericGitComposerMetadataFetcher::class         => ConfigAbstractFactory::class,
            HttpBitbucketComposerMetadataFetcher::class          => ConfigAbstractFactory::class,
            HttpCodebergComposerMetadataFetcher::class           => ConfigAbstractFactory::class,
            GitRemoteReferenceReader::class                      => ConfigAbstractFactory::class,

            // --- Readme extractors ---
            GithubPackageReadmeExtractor::class                  => ConfigAbstractFactory::class,
            ReadmeExtractorTemplate::class                       => ConfigAbstractFactory::class,
            GitlabPackageReadmeExtractor::class                  => ConfigAbstractFactory::class,
            GitlabSelfHostedPackageReadmeExtractor::class        => ConfigAbstractFactory::class,
            GenericGitPackageReadmeExtractor::class              => ConfigAbstractFactory::class,
            BitbucketPackageReadmeExtractor::class               => ConfigAbstractFactory::class,
            CodebergPackageReadmeExtractor::class                => ConfigAbstractFactory::class,

            // --- Handlers ---
            SourceConnectHandler::class                          => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        GitCommandRunner::class                              => [LimitedProcessRunner::class],
        ConnectSourcesUseCase::class                         => [SourceConnectPort::class, LoggerInterface::class],
        SourceConnectAdapter::class                          => [SourceApiFacadeInterface::class, JobApiFacadeInterface::class],
        // --- Facade ---
        PublicationApiFacade::class                          => [
            ConnectSourceService::class,
        ],
        ConnectSourceService::class                          => [
            SourcePublicationSynchronizerRegistry::class,
            SynchronizerDependenciesPort::class,
        ],
        PackageReadmeSyncService::class                      => [
            SynchronizerDependenciesPort::class,
            LoggerInterface::class,
        ],
        ComposerReleaseDateProvider::class                   => [
            ComposerReleaseDateResolverInterface::class,
        ],

        // --- Adapters ---
        PublicationSynchronizationAdapter::class             => [
            SourceApiFacadeInterface::class,
            SourceSecretResolverInterface::class,
            PackageApiFacadeInterface::class,
            ReleaseApiFacadeInterface::class,
            AuditApiFacadeInterface::class,
        ],

        // --- Synchronizers ---
        ProviderPublicationSynchronizer::class               => [
            SynchronizerDependenciesPort::class,
            ComposerVersionResolver::class,
            ComposerReleaseDateProviderInterface::class,
        ],
        GithubPublicationAdapter::class                      => [
            GithubComposerMetadataFetcherInterface::class,
            PackageReadmeExtractorInterface::class,
            PackageReadmeSyncService::class,
        ],
        GitlabPublicationAdapter::class                      => [
            GitlabComposerMetadataFetcherInterface::class,
            GitlabPackageReadmeExtractorInterface::class,
            PackageReadmeSyncService::class,
        ],
        GitlabSelfHostedPublicationAdapter::class            => [
            GitlabSelfHostedComposerMetadataFetcherInterface::class,
            GitlabSelfHostedPackageReadmeExtractorInterface::class,
            PackageReadmeSyncService::class,
        ],
        GenericGitPublicationAdapter::class                  => [
            GenericGitComposerMetadataFetcherInterface::class,
            GenericGitPackageReadmeExtractorInterface::class,
            PackageReadmeSyncService::class,
        ],
        BitbucketPublicationAdapter::class                   => [
            BitbucketComposerMetadataFetcherInterface::class,
            BitbucketPackageReadmeExtractorInterface::class,
            PackageReadmeSyncService::class,
        ],
        CodebergPublicationAdapter::class                    => [
            CodebergComposerMetadataFetcherInterface::class,
            CodebergPackageReadmeExtractorInterface::class,
            PackageReadmeSyncService::class,
        ],

        // --- Fetchers ---
        HttpGithubComposerMetadataFetcher::class             => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            StoredSecretResolver::class,
            GitRemoteReferenceReader::class,
        ],
        HttpGitlabComposerMetadataFetcher::class             => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            StoredSecretResolver::class,
            GitRemoteReferenceReader::class,
        ],
        HttpGitlabSelfHostedComposerMetadataFetcher::class   => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            StoredSecretResolver::class,
            GitRemoteReferenceReader::class,
        ],
        HttpGenericGitComposerMetadataFetcher::class         => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            StoredSecretResolver::class,
            GitRemoteReferenceReader::class,
        ],
        HttpBitbucketComposerMetadataFetcher::class          => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            StoredSecretResolver::class,
            GitRemoteReferenceReader::class,
        ],
        HttpCodebergComposerMetadataFetcher::class           => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            StoredSecretResolver::class,
            GitRemoteReferenceReader::class,
        ],
        GitRemoteReferenceReader::class                      => [
            GitCommandRunnerInterface::class,
            SafeGitEnvironment::class,
        ],

        // --- Readme extractors ---
        ReadmeExtractorTemplate::class                       => [
            ReadmeCandidateProvider::class,
            MarkdownToHtmlRenderer::class,
        ],
        GithubPackageReadmeExtractor::class                  => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            ReadmeExtractorTemplate::class,
            Base64FileContentDecoder::class,
            StoredSecretResolver::class,
        ],
        GitlabPackageReadmeExtractor::class                  => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            ReadmeExtractorTemplate::class,
            Base64FileContentDecoder::class,
            StoredSecretResolver::class,
            // LoggerInterface::class,  // optional
        ],
        GitlabSelfHostedPackageReadmeExtractor::class        => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            ReadmeExtractorTemplate::class,
            StoredSecretResolver::class,
        ],
        GenericGitPackageReadmeExtractor::class              => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            ReadmeExtractorTemplate::class,
            StoredSecretResolver::class,
        ],
        BitbucketPackageReadmeExtractor::class               => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            ReadmeExtractorTemplate::class,
            StoredSecretResolver::class,
        ],
        CodebergPackageReadmeExtractor::class                => [
            UpstreamHttpClient::class,
            BrandingConfig::class,
            ReadmeExtractorTemplate::class,
            Base64FileContentDecoder::class,
            StoredSecretResolver::class,
        ],

        // --- Handlers ---
        SourceConnectHandler::class                          => [
            ConnectSourcesUseCase::class,
            ConnectSourcesCommandFactory::class,
            InertiaNotificationMessenger::class,
        ],
    ],
];
