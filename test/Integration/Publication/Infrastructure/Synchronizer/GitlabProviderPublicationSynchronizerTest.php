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
use Packio\Publication\Infrastructure\Gitlab\GitlabAccessCredentials;
use Packio\Publication\Infrastructure\Gitlab\GitlabComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Gitlab\GitlabPackageMetadata;
use Packio\Publication\Infrastructure\Gitlab\GitlabRepositorySnapshot;
use Packio\Publication\Infrastructure\Readme\GitlabPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Synchronizer\GitlabPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationSynchronizer;
use Packio\Publication\Infrastructure\Synchronizer\ProviderSourcePublicationSynchronizer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ProviderPublicationSynchronizer::class)]
#[CoversClass(ProviderSourcePublicationSynchronizer::class)]
#[AllowMockObjectsWithoutExpectations]
final class GitlabProviderPublicationSynchronizerTest extends TestCase
{
    private const string SOURCE_ID      = 'source-02';
    private const string ORG_ID         = 'org-01';
    private const string NAMESPACE      = 'vendor';
    private const string REPO           = 'readme-package';
    private const string PACKAGE_NAME   = 'vendor/readme-package';
    private const string DEFAULT_BRANCH = 'main';
    private const string README_HTML    = '<p>Package README</p>';

    #[DataProvider('readmeExtractionCases')]
    public function testSynchronizeUpdatesPackageReadmeWhenExtractorIsPresent(?string $readmeHtml, string $expectedReadmeStatus): void
    {
        $source = [
            'id'             => self::SOURCE_ID,
            'owner'          => self::NAMESPACE,
            'repository'     => self::REPO,
            'organizationId' => self::ORG_ID,
            'visibility'     => 'public',
            'url'            => 'https://gitlab.com/' . self::NAMESPACE . '/' . self::REPO,
        ];

        $gitlabRepositorySnapshot = new GitlabRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [],
        );

        $gitlabPackageMetadata = new GitlabPackageMetadata(
            namespace: self::NAMESPACE,
            repository: self::REPO,
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
            'sourceType'     => 'gitlab',
            'sourceUrl'      => 'https://gitlab.com/' . self::NAMESPACE . '/' . self::REPO,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(GitlabComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(GitlabPackageReadmeExtractorInterface::class);
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
            ->with(self::NAMESPACE, self::REPO, self::isInstanceOf(GitlabAccessCredentials::class))
            ->willReturn($gitlabRepositorySnapshot)
        ;

        $fetcher
            ->expects(self::once())
            ->method('fetchReference')
            ->with(self::NAMESPACE, self::REPO, new GitReference(self::DEFAULT_BRANCH, self::DEFAULT_BRANCH, GitReference::TYPE_BRANCH), self::DEFAULT_BRANCH, self::isInstanceOf(GitlabAccessCredentials::class))
            ->willReturn($gitlabPackageMetadata)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new ComposerReleaseDateMap([
                self::DEFAULT_BRANCH => '2024-05-02T12:00:00+00:00',
            ], new DateTimeImmutable('2024-05-02T12:00:00+00:00')))
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
            ->willReturnCallback(static fn (array $data): array => $upsertedPackage)
        ;

        $syncPort
            ->expects(self::once())
            ->method('publishRelease')
            ->willReturnCallback(static function(array $data): array {
                self::assertSame('2024-05-02T12:00:00+00:00', $data['releasedAt'] ?? null);

                return $data;
            })
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractGitlabReadmeHtml')
            ->with(self::NAMESPACE, self::REPO, self::DEFAULT_BRANCH, self::isInstanceOf(GitlabAccessCredentials::class))
            ->willReturn($readmeHtml)
        ;

        $syncPort
            ->expects(self::once())
            ->method('updatePackageReadmeHtml')
            ->with(self::ORG_ID, self::PACKAGE_NAME, $readmeHtml)
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
                self::stringContains('publication.gitlab'),
                self::isArray(),
                self::identicalTo(self::ORG_ID),
                self::identicalTo(self::SOURCE_ID),
                self::identicalTo(self::PACKAGE_NAME),
            )
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new GitlabPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $publicationSyncResult = $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertTrue($publicationSyncResult->created);
        self::assertSame(self::PACKAGE_NAME, $publicationSyncResult->package['name']);
        self::assertSame($expectedReadmeStatus, $publicationSyncResult->readmeSync['status'] ?? null);
    }

    /**
     * @return iterable<string, array{null|string, string}>
     */
    public static function readmeExtractionCases(): iterable
    {
        yield 'readme found' => [self::README_HTML, 'updated'];

        yield 'readme removed upstream' => [null, 'cleared'];
    }

    public function testSynchronizeDoesNotClearPackageReadmeWhenExtractorFails(): void
    {
        $source = [
            'id'             => self::SOURCE_ID,
            'owner'          => self::NAMESPACE,
            'repository'     => self::REPO,
            'organizationId' => self::ORG_ID,
            'visibility'     => 'public',
            'url'            => 'https://gitlab.com/' . self::NAMESPACE . '/' . self::REPO,
        ];

        $gitlabRepositorySnapshot = new GitlabRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [],
        );

        $gitlabPackageMetadata = new GitlabPackageMetadata(
            namespace: self::NAMESPACE,
            repository: self::REPO,
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
            'sourceType'     => 'gitlab',
            'sourceUrl'      => 'https://gitlab.com/' . self::NAMESPACE . '/' . self::REPO,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(GitlabComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(GitlabPackageReadmeExtractorInterface::class);
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
            ->with(self::NAMESPACE, self::REPO, self::isInstanceOf(GitlabAccessCredentials::class))
            ->willReturn($gitlabRepositorySnapshot)
        ;

        $fetcher
            ->expects(self::once())
            ->method('fetchReference')
            ->with(self::NAMESPACE, self::REPO, new GitReference(self::DEFAULT_BRANCH, self::DEFAULT_BRANCH, GitReference::TYPE_BRANCH), self::DEFAULT_BRANCH, self::isInstanceOf(GitlabAccessCredentials::class))
            ->willReturn($gitlabPackageMetadata)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new ComposerReleaseDateMap([
                self::DEFAULT_BRANCH => '2024-05-02T12:00:00+00:00',
            ], new DateTimeImmutable('2024-05-02T12:00:00+00:00')))
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
            ->willReturnCallback(static fn (array $data): array => $upsertedPackage)
        ;

        $syncPort
            ->expects(self::once())
            ->method('publishRelease')
            ->willReturnCallback(static fn (array $data): array => $data)
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractGitlabReadmeHtml')
            ->with(self::NAMESPACE, self::REPO, self::DEFAULT_BRANCH, self::isInstanceOf(GitlabAccessCredentials::class))
            ->willThrowException(new RuntimeException('GitLab README API error (500).'))
        ;

        $syncPort
            ->expects(self::never())
            ->method('updatePackageReadmeHtml')
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
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new GitlabPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $publicationSyncResult = $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertTrue($publicationSyncResult->created);
        self::assertSame(self::PACKAGE_NAME, $publicationSyncResult->package['name']);
        self::assertSame('failed', $publicationSyncResult->readmeSync['status'] ?? null);
        self::assertSame('GitLab README API error (500).', $publicationSyncResult->readmeSync['message'] ?? null);
    }
}
