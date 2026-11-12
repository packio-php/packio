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
use Packio\Publication\Infrastructure\Github\GithubAccessCredentials;
use Packio\Publication\Infrastructure\Github\GithubComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Github\GithubPackageMetadata;
use Packio\Publication\Infrastructure\Github\GithubRepositorySnapshot;
use Packio\Publication\Infrastructure\Readme\PackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Synchronizer\GithubPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationSynchronizer;
use Packio\Publication\Infrastructure\Synchronizer\ProviderSourcePublicationSynchronizer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ProviderPublicationSynchronizer::class)]
#[CoversClass(ProviderSourcePublicationSynchronizer::class)]
#[AllowMockObjectsWithoutExpectations]
final class GithubProviderPublicationSynchronizerTest extends TestCase
{
    private const string SOURCE_ID      = 'source-01';
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
            'url'            => 'https://github.com/' . self::OWNER . '/' . self::REPO,
        ];

        $githubRepositorySnapshot = new GithubRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [],
        );

        $githubPackageMetadata = new GithubPackageMetadata(
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
            'sourceType'     => 'github',
            'sourceUrl'      => 'https://github.com/' . self::OWNER . '/' . self::REPO,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(GithubComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(PackageReadmeExtractorInterface::class);
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
            ->with(self::OWNER, self::REPO, self::isInstanceOf(GithubAccessCredentials::class))
            ->willReturn($githubRepositorySnapshot)
        ;

        $fetcher
            ->expects(self::once())
            ->method('fetchReference')
            ->with(self::OWNER, self::REPO, new GitReference(self::DEFAULT_BRANCH, self::DEFAULT_BRANCH, GitReference::TYPE_BRANCH), self::DEFAULT_BRANCH, self::isInstanceOf(GithubAccessCredentials::class))
            ->willReturn($githubPackageMetadata)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new ComposerReleaseDateMap([
                self::DEFAULT_BRANCH => '2024-05-01T12:00:00+00:00',
            ], new DateTimeImmutable('2024-05-01T12:00:00+00:00')))
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
                self::assertSame('2024-05-01T12:00:00+00:00', $data['releasedAt'] ?? null);

                return $data;
            })
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractGithubReadmeHtml')
            ->with(self::OWNER, self::REPO, self::DEFAULT_BRANCH, self::isInstanceOf(GithubAccessCredentials::class))
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
                self::stringContains('publication.github'),
                self::isArray(),
                self::identicalTo(self::ORG_ID),
                self::identicalTo(self::SOURCE_ID),
                self::identicalTo(self::PACKAGE_NAME),
            )
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new GithubPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $publicationSyncResult = $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertTrue($publicationSyncResult->created);
        self::assertSame(self::PACKAGE_NAME, $publicationSyncResult->package['name']);
    }

    public function testSynchronizeFallsBackToImportDateWhenReleaseDateResolutionFails(): void
    {
        $source = [
            'id'             => self::SOURCE_ID,
            'owner'          => self::OWNER,
            'repository'     => self::REPO,
            'organizationId' => self::ORG_ID,
            'visibility'     => 'public',
            'url'            => 'https://github.com/' . self::OWNER . '/' . self::REPO,
        ];

        $githubRepositorySnapshot = new GithubRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [],
        );

        $githubPackageMetadata = new GithubPackageMetadata(
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
            'sourceType'     => 'github',
            'sourceUrl'      => 'https://github.com/' . self::OWNER . '/' . self::REPO,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(GithubComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(PackageReadmeExtractorInterface::class);
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
            ->willReturn($githubRepositorySnapshot)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->willReturnCallback(
                static fn (array $source, ?string $accessToken, string $fallbackReleasedAt): ComposerReleaseDateMap => ComposerReleaseDateMap::fallback($fallbackReleasedAt),
            )
        ;

        $fetcher
            ->expects(self::once())
            ->method('fetchReference')
            ->willReturn($githubPackageMetadata)
        ;

        $syncPort
            ->expects(self::once())
            ->method('findPackageInOrganization')
            ->willReturn(null)
        ;

        $syncPort
            ->expects(self::once())
            ->method('upsertPackage')
            ->willReturn($upsertedPackage)
        ;

        $syncPort
            ->expects(self::once())
            ->method('publishRelease')
            ->willReturnCallback(static function(array $data): array {
                self::assertNotEmpty($data['createdAt'] ?? null);
                self::assertSame($data['createdAt'], $data['releasedAt'] ?? null);

                return $data;
            })
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractGithubReadmeHtml')
            ->willReturn(self::README_HTML)
        ;

        $syncPort
            ->expects(self::once())
            ->method('updatePackageReadmeHtml')
            ->willReturn($upsertedPackage)
        ;

        $syncPort
            ->expects(self::once())
            ->method('markSyncSucceeded')
            ->willReturn($source)
        ;

        $syncPort
            ->expects(self::never())
            ->method('markSyncFailed')
        ;

        $syncPort
            ->expects(self::once())
            ->method('recordAudit')
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new GithubPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $publicationSyncResult = $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertTrue($publicationSyncResult->created);
        self::assertSame(self::PACKAGE_NAME, $publicationSyncResult->package['name']);
    }

    public function testSynchronizePublishesBranchReferencesFromSnapshot(): void
    {
        $source = [
            'id'             => self::SOURCE_ID,
            'owner'          => self::OWNER,
            'repository'     => self::REPO,
            'organizationId' => self::ORG_ID,
            'visibility'     => 'public',
            'url'            => 'https://github.com/' . self::OWNER . '/' . self::REPO,
        ];

        $githubRepositorySnapshot = new GithubRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [
                new GitReference('v1.0.0', '1111111111111111111111111111111111111111', GitReference::TYPE_TAG),
            ],
            branches: [
                new GitReference('2.x', '2222222222222222222222222222222222222222', GitReference::TYPE_BRANCH),
                new GitReference(self::DEFAULT_BRANCH, '3333333333333333333333333333333333333333', GitReference::TYPE_BRANCH),
            ],
        );

        $metadataByReference = [];
        foreach (
            [
                ['v1.0.0', '1111111111111111111111111111111111111111', GitReference::TYPE_TAG],
                ['2.x', '2222222222222222222222222222222222222222', GitReference::TYPE_BRANCH],
                [self::DEFAULT_BRANCH, '3333333333333333333333333333333333333333', GitReference::TYPE_BRANCH],
            ] as [$reference, $commitReference, $refType]
        ) {
            $metadataByReference[$reference] = new GithubPackageMetadata(
                owner: self::OWNER,
                repository: self::REPO,
                reference: $reference,
                composer: [
                    'name'        => self::PACKAGE_NAME,
                    'description' => 'Test package with branch releases',
                ],
                defaultBranch: self::DEFAULT_BRANCH,
                commitReference: $commitReference,
                refType: $refType,
            );
        }

        $upsertedPackage = [
            'name'           => self::PACKAGE_NAME,
            'type'           => 'library',
            'description'    => 'Test package with branch releases',
            'visibility'     => 'public',
            'sourceType'     => 'github',
            'sourceUrl'      => 'https://github.com/' . self::OWNER . '/' . self::REPO,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(GithubComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(PackageReadmeExtractorInterface::class);
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
            ->willReturn($githubRepositorySnapshot)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new ComposerReleaseDateMap([], new DateTimeImmutable('2024-05-01T12:00:00+00:00')))
        ;

        $fetchedReferences = [];
        $fetcher
            ->expects(self::exactly(3))
            ->method('fetchReference')
            ->willReturnCallback(
                static function(string $owner, string $repository, GitReference $gitReference, string $defaultBranch) use (&$fetchedReferences, $metadataByReference): GithubPackageMetadata {
                    self::assertSame(self::OWNER, $owner);
                    self::assertSame(self::REPO, $repository);
                    self::assertSame(self::DEFAULT_BRANCH, $defaultBranch);
                    $fetchedReferences[] = $gitReference->name;

                    return $metadataByReference[$gitReference->name] ?? throw new RuntimeException('Unexpected reference.');
                },
            )
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
            ->willReturn($upsertedPackage)
        ;

        $publishedReleases = [];
        $syncPort
            ->expects(self::exactly(3))
            ->method('publishRelease')
            ->willReturnCallback(static function(array $data) use (&$publishedReleases): array {
                $publishedReleases[] = [
                    'reference'         => $data['reference'],
                    'upstreamReference' => $data['upstreamReference'],
                    'referenceType'     => $data['referenceType'],
                    'sourceReference'   => $data['source']['reference'],
                    'distReference'     => $data['dist']['reference'],
                    'version'           => $data['version'],
                ];

                return $data;
            })
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractGithubReadmeHtml')
            ->with(self::OWNER, self::REPO, self::DEFAULT_BRANCH, self::isInstanceOf(GithubAccessCredentials::class))
            ->willReturn(self::README_HTML)
        ;

        $syncPort
            ->expects(self::once())
            ->method('updatePackageReadmeHtml')
            ->willReturn($upsertedPackage)
        ;

        $syncPort
            ->expects(self::once())
            ->method('markSyncSucceeded')
            ->willReturn($source)
        ;

        $syncPort
            ->expects(self::once())
            ->method('recordAudit')
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new GithubPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertSame(['v1.0.0', '2.x', self::DEFAULT_BRANCH], $fetchedReferences);
        self::assertSame([
            [
                'reference'         => '1111111111111111111111111111111111111111',
                'upstreamReference' => 'v1.0.0',
                'referenceType'     => GitReference::TYPE_TAG,
                'sourceReference'   => '1111111111111111111111111111111111111111',
                'distReference'     => '1111111111111111111111111111111111111111',
                'version'           => '1.0.0',
            ],
            [
                'reference'         => '2222222222222222222222222222222222222222',
                'upstreamReference' => '2.x',
                'referenceType'     => GitReference::TYPE_BRANCH,
                'sourceReference'   => '2222222222222222222222222222222222222222',
                'distReference'     => '2222222222222222222222222222222222222222',
                'version'           => '2.x-dev',
            ],
            [
                'reference'         => '3333333333333333333333333333333333333333',
                'upstreamReference' => self::DEFAULT_BRANCH,
                'referenceType'     => GitReference::TYPE_BRANCH,
                'sourceReference'   => '3333333333333333333333333333333333333333',
                'distReference'     => '3333333333333333333333333333333333333333',
                'version'           => 'dev-main',
            ],
        ], $publishedReleases);
    }

    public function testSynchronizeSkipsReferencesThatResolveToAlreadyPublishedVersion(): void
    {
        $source = [
            'id'             => self::SOURCE_ID,
            'owner'          => self::OWNER,
            'repository'     => self::REPO,
            'organizationId' => self::ORG_ID,
            'visibility'     => 'public',
            'url'            => 'https://github.com/' . self::OWNER . '/' . self::REPO,
        ];

        $githubRepositorySnapshot = new GithubRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [],
            branches: ['2.x', self::DEFAULT_BRANCH],
        );

        $branchMetadata = new GithubPackageMetadata(
            owner: self::OWNER,
            repository: self::REPO,
            reference: '2.x',
            composer: [
                'name'        => self::PACKAGE_NAME,
                'description' => 'Test package with branch releases',
            ],
            defaultBranch: self::DEFAULT_BRANCH,
        );
        $defaultBranchMetadata = new GithubPackageMetadata(
            owner: self::OWNER,
            repository: self::REPO,
            reference: self::DEFAULT_BRANCH,
            composer: [
                'name'        => self::PACKAGE_NAME,
                'description' => 'Test package with branch releases',
                'extra'       => [
                    'branch-alias' => [
                        'dev-' . self::DEFAULT_BRANCH => '2.x-dev',
                    ],
                ],
            ],
            defaultBranch: self::DEFAULT_BRANCH,
        );

        $upsertedPackage = [
            'name'           => self::PACKAGE_NAME,
            'type'           => 'library',
            'description'    => 'Test package with branch releases',
            'visibility'     => 'public',
            'sourceType'     => 'github',
            'sourceUrl'      => 'https://github.com/' . self::OWNER . '/' . self::REPO,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(GithubComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(PackageReadmeExtractorInterface::class);
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
            ->willReturn($githubRepositorySnapshot)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new ComposerReleaseDateMap([], new DateTimeImmutable('2024-05-01T12:00:00+00:00')))
        ;

        $fetcher
            ->expects(self::exactly(2))
            ->method('fetchReference')
            ->willReturnCallback(
                static function(
                    string $owner,
                    string $repository,
                    GitReference $gitReference,
                    string $defaultBranch,
                    GithubAccessCredentials $githubAccessCredentials,
                ) use ($branchMetadata, $defaultBranchMetadata): GithubPackageMetadata {
                    self::assertSame(self::OWNER, $owner);
                    self::assertSame(self::REPO, $repository);
                    self::assertSame(self::DEFAULT_BRANCH, $defaultBranch);
                    self::assertTrue($githubAccessCredentials->hasToken());

                    if ('2.x' === $gitReference->name) {
                        return $branchMetadata;
                    }

                    self::assertSame(self::DEFAULT_BRANCH, $gitReference->name);

                    return $defaultBranchMetadata;
                },
            )
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
            ->willReturn($upsertedPackage)
        ;

        $syncPort
            ->expects(self::once())
            ->method('publishRelease')
            ->willReturnCallback(static function(array $data): array {
                self::assertSame('2.x', $data['reference']);
                self::assertSame('2.x-dev', $data['version']);

                return $data;
            })
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractGithubReadmeHtml')
            ->willReturn(self::README_HTML)
        ;

        $syncPort
            ->expects(self::once())
            ->method('updatePackageReadmeHtml')
            ->willReturn($upsertedPackage)
        ;

        $syncPort
            ->expects(self::once())
            ->method('markSyncSucceeded')
            ->willReturn($source)
        ;

        $syncPort
            ->expects(self::once())
            ->method('recordAudit')
            ->with(
                self::stringContains('publication.github'),
                self::callback(static function(array $context): bool {
                    self::assertSame(['2.x-dev'], $context['versions']);
                    self::assertSame(1, $context['skipped_references_count']);
                    self::assertSame(self::DEFAULT_BRANCH, $context['skipped_references'][0]['reference'] ?? null);
                    self::assertStringContainsString('2.x-dev', $context['skipped_references'][0]['error'] ?? '');

                    return true;
                }),
                self::identicalTo(self::ORG_ID),
                self::identicalTo(self::SOURCE_ID),
                self::identicalTo(self::PACKAGE_NAME),
            )
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new GithubPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $publicationSyncResult = $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertCount(1, $publicationSyncResult->releases);
        self::assertSame('2.x-dev', $publicationSyncResult->releases[0]['version']);
    }
}
