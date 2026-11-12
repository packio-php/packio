<?php

declare(strict_types=1);

namespace PackioTest\Integration\Publication\Infrastructure\Synchronizer;

use DateTimeImmutable;
use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Service\ComposerVersionResolver;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateProviderInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedAccessCredentials;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedPackageMetadata;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedRepositorySnapshot;
use Packio\Publication\Infrastructure\Readme\GitlabSelfHostedPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Synchronizer\GitlabSelfHostedPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationSynchronizer;
use Packio\Publication\Infrastructure\Synchronizer\ProviderSourcePublicationSynchronizer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderPublicationSynchronizer::class)]
#[CoversClass(ProviderSourcePublicationSynchronizer::class)]
#[AllowMockObjectsWithoutExpectations]
final class GitlabSelfHostedProviderPublicationSynchronizerTest extends TestCase
{
    private const string SOURCE_ID      = 'source-05';
    private const string ORG_ID         = 'org-01';
    private const string NAMESPACE      = 'vendor';
    private const string REPO           = 'readme-package';
    private const string PACKAGE_NAME   = 'vendor/readme-package';
    private const string DEFAULT_BRANCH = 'main';
    private const string BASE_URL       = 'https://gitlab.company.test';
    private const string README_HTML    = '<p>Package README</p>';

    public function testSynchronizeUpdatesPackageReadmeWhenExtractorIsPresent(): void
    {
        $source = [
            'id'             => self::SOURCE_ID,
            'owner'          => self::NAMESPACE,
            'repository'     => self::REPO,
            'organizationId' => self::ORG_ID,
            'visibility'     => 'public',
            'url'            => self::BASE_URL . '/' . self::NAMESPACE . '/' . self::REPO,
            'providerConfig' => [
                'base_url' => self::BASE_URL,
            ],
        ];

        $gitlabSelfHostedRepositorySnapshot = new GitlabSelfHostedRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [],
        );

        $gitlabSelfHostedPackageMetadata = new GitlabSelfHostedPackageMetadata(
            namespace: self::NAMESPACE,
            repository: self::REPO,
            baseUrl: self::BASE_URL,
            reference: self::DEFAULT_BRANCH,
            composer: [
                'name'        => self::PACKAGE_NAME,
                'description' => 'Test package with README',
            ],
            defaultBranch: self::DEFAULT_BRANCH,
        );

        $upsertedPackage = [
            'name'           => self::PACKAGE_NAME,
            'type'           => 'library',
            'description'    => 'Test package with README',
            'visibility'     => 'public',
            'sourceType'     => 'gitlab_self_hosted',
            'sourceUrl'      => self::BASE_URL . '/' . self::NAMESPACE . '/' . self::REPO,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(GitlabSelfHostedComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(GitlabSelfHostedPackageReadmeExtractorInterface::class);
        $releaseDates    = $this->createMock(ComposerReleaseDateProviderInterface::class);

        $syncPort
            ->expects(self::once())
            ->method('resolveSourceAuthorizationSecret')
            ->with(self::SOURCE_ID)
            ->willReturn('test-token')
        ;

        $fetcher
            ->expects(self::once())
            ->method('snapshot')
            ->with(self::NAMESPACE, self::REPO, self::isInstanceOf(GitlabSelfHostedAccessCredentials::class))
            ->willReturn($gitlabSelfHostedRepositorySnapshot)
        ;

        $fetcher
            ->expects(self::once())
            ->method('fetchReference')
            ->with(self::NAMESPACE, self::REPO, new GitReference(self::DEFAULT_BRANCH, self::DEFAULT_BRANCH, GitReference::TYPE_BRANCH), self::DEFAULT_BRANCH, self::isInstanceOf(GitlabSelfHostedAccessCredentials::class))
            ->willReturn($gitlabSelfHostedPackageMetadata)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->with($source, 'test-token', self::isString())
            ->willReturn(new ComposerReleaseDateMap(
                [
                    self::DEFAULT_BRANCH => '2024-05-05T12:00:00+00:00',
                ],
                new DateTimeImmutable('2024-05-05T12:00:00+00:00'),
            ))
        ;

        $syncPort
            ->expects(self::once())
            ->method('findPackageInOrganization')
            ->with(self::ORG_ID, self::PACKAGE_NAME)
            ->willReturn(null)
        ;

        $syncPort
            ->expects(self::once())
            ->method('upsertPackage')
            ->willReturnCallback(static function(array $data) use ($upsertedPackage): array {
                self::assertSame('gitlab_self_hosted', $data['sourceType']);
                self::assertSame(self::BASE_URL . '/' . self::NAMESPACE . '/' . self::REPO, $data['sourceUrl']);

                return $upsertedPackage;
            })
        ;

        $syncPort
            ->expects(self::once())
            ->method('publishRelease')
            ->willReturnCallback(static function(array $data): array {
                self::assertSame(self::BASE_URL . '/' . self::NAMESPACE . '/' . self::REPO . '.git', $data['source']['url']);
                self::assertSame(
                    self::BASE_URL . '/' . self::NAMESPACE . '/' . self::REPO . '/-/archive/' . self::DEFAULT_BRANCH . '/' . self::REPO . '-' . self::DEFAULT_BRANCH . '.zip',
                    $data['dist']['url'],
                );
                self::assertSame('2024-05-05T12:00:00+00:00', $data['releasedAt'] ?? null);

                return $data;
            })
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractGitlabSelfHostedReadmeHtml')
            ->with(self::NAMESPACE, self::REPO, self::DEFAULT_BRANCH, self::isInstanceOf(GitlabSelfHostedAccessCredentials::class))
            ->willReturn(self::README_HTML)
        ;

        $syncPort
            ->expects(self::once())
            ->method('updatePackageReadmeHtml')
            ->with(self::ORG_ID, self::PACKAGE_NAME, self::README_HTML)
            ->willReturn($upsertedPackage)
        ;

        $syncPort
            ->expects(self::once())
            ->method('markSyncSucceeded')
            ->with(self::SOURCE_ID)
            ->willReturn($source)
        ;

        $syncPort
            ->expects(self::once())
            ->method('recordAudit')
            ->with(
                self::stringContains('publication.gitlab_self_hosted'),
                self::isArray(),
                self::identicalTo(self::ORG_ID),
                self::identicalTo(self::SOURCE_ID),
                self::identicalTo(self::PACKAGE_NAME),
            )
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new GitlabSelfHostedPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $publicationSyncResult = $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertTrue($publicationSyncResult->created);
        self::assertSame(self::PACKAGE_NAME, $publicationSyncResult->package['name']);
    }
}
