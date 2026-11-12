<?php

declare(strict_types=1);

namespace PackioTest\Integration\Publication\Infrastructure\Synchronizer;

use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Service\ComposerVersionResolver;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateProviderInterface;
use Packio\Publication\Infrastructure\GenericGit\GenericGitAccessCredentials;
use Packio\Publication\Infrastructure\GenericGit\GenericGitComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\GenericGit\GenericGitPackageMetadata;
use Packio\Publication\Infrastructure\GenericGit\GenericGitRepositorySnapshot;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Readme\GenericGitPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Synchronizer\GenericGitPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationSynchronizer;
use Packio\Publication\Infrastructure\Synchronizer\ProviderSourcePublicationSynchronizer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderPublicationSynchronizer::class)]
#[CoversClass(ProviderSourcePublicationSynchronizer::class)]
#[AllowMockObjectsWithoutExpectations]
final class GenericGitProviderPublicationSynchronizerTest extends TestCase
{
    private const string SOURCE_ID      = 'source-06';
    private const string ORG_ID         = 'org-01';
    private const string CLONE_URL      = 'https://git.example.test/vendor/readme-package.git';
    private const string PACKAGE_NAME   = 'vendor/readme-package';
    private const string DEFAULT_BRANCH = 'main';
    private const string README_HTML    = '<p>Package README</p>';

    public function testSynchronizeSkipsComposerResolverForNonRootComposerJsonPath(): void
    {
        $source = [
            'id'             => self::SOURCE_ID,
            'provider'       => 'generic_git',
            'owner'          => '',
            'repository'     => '',
            'organizationId' => self::ORG_ID,
            'visibility'     => 'public',
            'url'            => self::CLONE_URL,
            'providerConfig' => [
                'clone_url'          => self::CLONE_URL,
                'auth_scheme'        => 'bearer',
                'composer_json_path' => 'packages/composer.json',
            ],
        ];

        $genericGitRepositorySnapshot = new GenericGitRepositorySnapshot(
            defaultBranch: self::DEFAULT_BRANCH,
            tags: [],
        );

        $genericGitPackageMetadata = new GenericGitPackageMetadata(
            cloneUrl: self::CLONE_URL,
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
            'sourceType'     => 'generic_git',
            'sourceUrl'      => self::CLONE_URL,
            'createdAt'      => '2025-01-01T00:00:00+00:00',
            'updatedAt'      => '2025-01-01T00:00:00+00:00',
            'organizationId' => self::ORG_ID,
            'sourceId'       => self::SOURCE_ID,
        ];

        $syncPort        = $this->createMock(SynchronizerDependenciesPort::class);
        $fetcher         = $this->createMock(GenericGitComposerMetadataFetcherInterface::class);
        $readmeExtractor = $this->createMock(GenericGitPackageReadmeExtractorInterface::class);
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
            ->with(self::CLONE_URL, self::isInstanceOf(GenericGitAccessCredentials::class))
            ->willReturn($genericGitRepositorySnapshot)
        ;

        $releaseDates
            ->expects(self::once())
            ->method('resolve')
            ->with($source, 'test-token', self::isString())
            ->willReturnCallback(
                static fn (array $source, ?string $accessToken, string $fallbackReleasedAt): ComposerReleaseDateMap => ComposerReleaseDateMap::fallback($fallbackReleasedAt),
            )
        ;

        $fetcher
            ->expects(self::once())
            ->method('fetchReference')
            ->with(self::CLONE_URL, new GitReference(self::DEFAULT_BRANCH, self::DEFAULT_BRANCH, GitReference::TYPE_BRANCH), self::DEFAULT_BRANCH, self::isInstanceOf(GenericGitAccessCredentials::class))
            ->willReturn($genericGitPackageMetadata)
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
                self::assertNotEmpty($data['createdAt'] ?? null);
                self::assertSame($data['createdAt'], $data['releasedAt'] ?? null);

                return $data;
            })
        ;

        $readmeExtractor
            ->expects(self::once())
            ->method('extractGenericGitReadmeHtml')
            ->with(self::CLONE_URL, self::DEFAULT_BRANCH, self::isInstanceOf(GenericGitAccessCredentials::class))
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
        ;

        $providerSourcePublicationSynchronizer = new ProviderSourcePublicationSynchronizer(
            new ProviderPublicationSynchronizer($syncPort, new ComposerVersionResolver(), $releaseDates),
            new GenericGitPublicationAdapter($fetcher, $readmeExtractor, new PackageReadmeSyncService($syncPort)),
        );

        $publicationSyncResult = $providerSourcePublicationSynchronizer->synchronize($source);

        self::assertTrue($publicationSyncResult->created);
        self::assertSame(self::PACKAGE_NAME, $publicationSyncResult->package['name']);
    }
}
