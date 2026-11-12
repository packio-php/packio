<?php

declare(strict_types=1);

namespace PackioTest\Integration\Publication\Infrastructure\Synchronizer;

use DateTimeImmutable;
use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Service\ComposerVersionResolver;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketAccessCredentials;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketPackageMetadata;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketRepositorySnapshot;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateProviderInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Readme\BitbucketPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Synchronizer\BitbucketPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationSynchronizer;
use Packio\Publication\Infrastructure\Synchronizer\ProviderSourcePublicationSynchronizer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderPublicationSynchronizer::class)]
#[CoversClass(ProviderSourcePublicationSynchronizer::class)]
#[AllowMockObjectsWithoutExpectations]
final class BitbucketProviderPublicationSynchronizerTest extends TestCase
{
    private const string SOURCE_ID      = 'source-03';
    private const string ORG_ID         = 'org-01';
    private const string WORKSPACE      = 'vendor';
    private const string REPO           = 'readme-package';
    private const string PACKAGE_NAME   = 'vendor/readme-package';
    private const string DEFAULT_BRANCH = 'main';
    private const string README_HTML    = '<p>Package README</p>';

    public function testSynchronizeUpdatesPackageReadmeWhenExtractorIsPresent(): void
    {
        $source = [
            'id'             => self::SOURCE_ID,
            'owner'          => self::WORKSPACE,
            'repository'     => self::REPO,
            'organizationId' => self::ORG_ID,
            'visibility'     => 'public',
            'url'            => 'https://bitbucket.org/' . self::WORKSPACE . '/' . self::REPO,
        ];

        $bitbucketRepositorySnapshot = new BitbucketRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [],
        );

        $bitbucketPackageMetadata = new BitbucketPackageMetadata(
            workspace: self::WORKSPACE,
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
            'sourceType'     => 'bitbucket',
            'sourceUrl'      => 'https://bitbucket.org/' . self::WORKSPACE . '/' . self::REPO,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(BitbucketComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(BitbucketPackageReadmeExtractorInterface::class);
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
            ->with(self::WORKSPACE, self::REPO, self::isInstanceOf(BitbucketAccessCredentials::class))
            ->willReturn($bitbucketRepositorySnapshot)
        ;

        $fetcher
            ->expects(self::once())
            ->method('fetchReference')
            ->with(self::WORKSPACE, self::REPO, new GitReference(self::DEFAULT_BRANCH, self::DEFAULT_BRANCH, GitReference::TYPE_BRANCH), self::DEFAULT_BRANCH, self::isInstanceOf(BitbucketAccessCredentials::class))
            ->willReturn($bitbucketPackageMetadata)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->with($source, 'test-token', self::isString())
            ->willReturn(new ComposerReleaseDateMap(
                [
                    self::DEFAULT_BRANCH => '2024-05-03T12:00:00+00:00',
                ],
                new DateTimeImmutable('2024-05-03T12:00:00+00:00'),
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
            ->willReturnCallback(static fn (array $data): array => $upsertedPackage)
        ;

        $syncPort
            ->expects(self::once())
            ->method('publishRelease')
            ->willReturnCallback(static function(array $data): array {
                self::assertSame('2024-05-03T12:00:00+00:00', $data['releasedAt'] ?? null);

                return $data;
            })
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractBitbucketReadmeHtml')
            ->with(self::WORKSPACE, self::REPO, self::DEFAULT_BRANCH, self::isInstanceOf(BitbucketAccessCredentials::class))
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
                self::stringContains('publication.bitbucket'),
                self::isArray(),
                self::identicalTo(self::ORG_ID),
                self::identicalTo(self::SOURCE_ID),
                self::identicalTo(self::PACKAGE_NAME),
            )
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new BitbucketPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $publicationSyncResult = $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertTrue($publicationSyncResult->created);
        self::assertSame(self::PACKAGE_NAME, $publicationSyncResult->package['name']);
    }
}
