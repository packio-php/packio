<?php

declare(strict_types=1);

namespace PackioTest\Integration\Publication\Infrastructure\Synchronizer;

use DateTimeImmutable;
use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Service\ComposerVersionResolver;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Codeberg\CodebergAccessCredentials;
use Packio\Publication\Infrastructure\Codeberg\CodebergComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Codeberg\CodebergPackageMetadata;
use Packio\Publication\Infrastructure\Codeberg\CodebergRepositorySnapshot;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateProviderInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Readme\CodebergPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Synchronizer\CodebergPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationSynchronizer;
use Packio\Publication\Infrastructure\Synchronizer\ProviderSourcePublicationSynchronizer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderPublicationSynchronizer::class)]
#[CoversClass(ProviderSourcePublicationSynchronizer::class)]
#[AllowMockObjectsWithoutExpectations]
final class CodebergProviderPublicationSynchronizerTest extends TestCase
{
    private const string SOURCE_ID      = 'source-04';
    private const string ORG_ID         = 'org-01';
    private const string OWNER          = 'vendor';
    private const string REPO           = 'readme-package';
    private const string PACKAGE_NAME   = 'vendor/readme-package';
    private const string DEFAULT_BRANCH = 'main';
    private const string README_HTML    = '<p>Package README</p>';

    public function testSynchronizeUpdatesPackageReadmeWhenExtractorIsPresent(): void
    {
        $source = [
            'id'             => self::SOURCE_ID,
            'owner'          => self::OWNER,
            'repository'     => self::REPO,
            'organizationId' => self::ORG_ID,
            'visibility'     => 'public',
            'url'            => 'https://codeberg.org/' . self::OWNER . '/' . self::REPO,
        ];

        $codebergRepositorySnapshot = new CodebergRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [],
        );

        $codebergPackageMetadata = new CodebergPackageMetadata(
            owner: self::OWNER,
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
            'sourceType'     => 'codeberg',
            'sourceUrl'      => 'https://codeberg.org/' . self::OWNER . '/' . self::REPO,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(CodebergComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(CodebergPackageReadmeExtractorInterface::class);
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
            ->with(self::OWNER, self::REPO, self::isInstanceOf(CodebergAccessCredentials::class))
            ->willReturn($codebergRepositorySnapshot)
        ;

        $fetcher
            ->expects(self::once())
            ->method('fetchReference')
            ->with(self::OWNER, self::REPO, new GitReference(self::DEFAULT_BRANCH, self::DEFAULT_BRANCH, GitReference::TYPE_BRANCH), self::DEFAULT_BRANCH, self::isInstanceOf(CodebergAccessCredentials::class))
            ->willReturn($codebergPackageMetadata)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->with($source, 'test-token', self::isString())
            ->willReturn(new ComposerReleaseDateMap(
                [
                    self::DEFAULT_BRANCH => '2024-05-04T12:00:00+00:00',
                ],
                new DateTimeImmutable('2024-05-04T12:00:00+00:00'),
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
                self::assertSame('codeberg', $data['sourceType']);
                self::assertSame('https://codeberg.org/' . self::OWNER . '/' . self::REPO, $data['sourceUrl']);

                return $upsertedPackage;
            })
        ;

        $syncPort
            ->expects(self::once())
            ->method('publishRelease')
            ->willReturnCallback(static function(array $data): array {
                self::assertSame('https://codeberg.org/' . self::OWNER . '/' . self::REPO . '.git', $data['source']['url']);
                self::assertSame('https://codeberg.org/' . self::OWNER . '/' . self::REPO . '/archive/' . self::DEFAULT_BRANCH . '.zip', $data['dist']['url']);
                self::assertSame('2024-05-04T12:00:00+00:00', $data['releasedAt'] ?? null);

                return $data;
            })
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractCodebergReadmeHtml')
            ->with(self::OWNER, self::REPO, self::DEFAULT_BRANCH, self::isInstanceOf(CodebergAccessCredentials::class))
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
                self::stringContains('publication.codeberg'),
                self::isArray(),
                self::identicalTo(self::ORG_ID),
                self::identicalTo(self::SOURCE_ID),
                self::identicalTo(self::PACKAGE_NAME),
            )
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new CodebergPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $publicationSyncResult = $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertTrue($publicationSyncResult->created);
        self::assertSame(self::PACKAGE_NAME, $publicationSyncResult->package['name']);
    }
}
