<?php

declare(strict_types=1);

namespace PackioTest\Feature\Registry;

use Cycle\ORM\ORMInterface;
use Laminas\Diactoros\Stream;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Application\Service\DistArchiveFetcherInterface;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Application\Storage\ArchiveReadStream;
use Packio\Registry\Application\Storage\DistArchiveStream;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Registry\Application\Storage\StoredArchive;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\ProviderAuthorizationRequestDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Fake\InMemoryPackageArchiveStorage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function base64_encode;
use function bin2hex;
use function fopen;
use function fwrite;
use function random_bytes;
use function rawurlencode;
use function rewind;

final class RegistryPrivateAccessFeatureTest extends FeatureTestCase
{
    private const string RELEASE_REFERENCE = '1111111111111111111111111111111111111111';

    private FakeDistArchiveFetcher $fakeDistArchiveFetcher;
    private ConfigurablePackageArchiveStorage $configurablePackageArchiveStorage;

    protected function setUp(): void
    {
        $this->fakeDistArchiveFetcher                                                            = new FakeDistArchiveFetcher();
        $this->configurablePackageArchiveStorage                                                 = new ConfigurablePackageArchiveStorage();
        $this->serviceOverrides[DistArchiveFetcherInterface::class]                              = $this->fakeDistArchiveFetcher;
        $this->serviceOverrides[PackageArchiveStorageInterface::class]                           = $this->configurablePackageArchiveStorage;
        $this->serviceOverrides['config.storage.archive.fallback_to_source_on_unavailable']      = true;

        parent::setUp();
    }

    #[Test]
    public function itRequiresRegistryTokenForPrivateRegistryEndpoints(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $this->createPackageWithRelease($organizationDto, 'vendor/private-package', 'private');

        $this->withHost($organizationDto->slug . '.packio.us');

        $this->get('/private/packages.json')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="Packio"')
        ;

        $this->get('/p2/vendor/private-package')->assertStatus(401);
        $this->get($this->distPath('vendor/private-package'))->assertStatus(401);
    }

    #[Test]
    public function itAllowsValidBasicTokenToReadPrivateRegistryMetadata(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: true);
        $userDto         = $this->createUser();
        $this->createPackageWithRelease($organizationDto, 'vendor/private-package', 'private');

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        $authorization = 'Basic ' . base64_encode('token:' . $issuedRegistryAccessTokenDto->plainTextToken);

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get('/private/packages.json', [
            'Authorization' => $authorization,
        ])->assertOk();
        self::assertSame('', $testResponse->header('Set-Cookie'));
        self::assertSame('private, max-age=60, must-revalidate', $testResponse->header('Cache-Control'));
        self::assertContains('vendor/private-package', $testResponse->json()['available-packages'] ?? []);

        $metadata = $this->get('/p2/vendor/private-package', [
            'Authorization' => $authorization,
        ])->assertOk();
        self::assertSame('', $metadata->header('Set-Cookie'));
        self::assertSame('private, max-age=60, must-revalidate', $metadata->header('Cache-Control'));
        self::assertSame(
            'https://' . $organizationDto->slug . '.packio.us' . $this->distPath('vendor/private-package'),
            $metadata->json()['packages']['vendor/private-package'][0]['dist']['url'] ?? null,
        );
        self::assertSame(
            '2024-04-01T12:00:00+00:00',
            $metadata->json()['packages']['vendor/private-package'][0]['time'] ?? null,
        );

        $publicBootstrap = $this->get('/packages.json')->assertOk();
        self::assertSame('', $publicBootstrap->header('Set-Cookie'));
        self::assertSame('private, max-age=60, must-revalidate', $publicBootstrap->header('Cache-Control'));
        self::assertNotContains('vendor/private-package', $publicBootstrap->json()['available-packages'] ?? []);
    }

    #[Test]
    public function itMarksRegistryTokenAsUsedAfterSuccessfulAuthentication(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $this->createPackageWithRelease($organizationDto, 'vendor/private-package', 'private');

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        /** @var RegistryAccessTokenRepositoryInterface $tokens */
        $tokens = $this->getService(RegistryAccessTokenRepositoryInterface::class);
        self::assertNull($tokens->findById($issuedRegistryAccessTokenDto->id)?->lastUsedAt());

        $this->withHost($organizationDto->slug . '.packio.us');

        $this->get('/private/packages.json', [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])->assertOk();

        self::assertNotNull($tokens->findById($issuedRegistryAccessTokenDto->id)?->lastUsedAt());
    }

    #[Test]
    public function itBlocksAndRestoresAPrivateRegistryTokenWhenItsOwnerIsBannedAndUnbanned(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $this->createPackageWithRelease($organizationDto, 'vendor/banned-owner-package', 'private');

        /** @var IdentityApiFacadeInterface $identity */
        $identity  = $this->getService(IdentityApiFacadeInterface::class);
        $moderator = $identity->createUser(
            'registry-moderator-' . $this->suffix() . '@example.test',
            'Registry Moderator',
            'Password1!',
            ['admin'],
        );
        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Banned owner token',
            ['packages.read.private'],
        );

        /** @var RegistryAccessTokenRepositoryInterface $tokens */
        $tokens = $this->getService(RegistryAccessTokenRepositoryInterface::class);

        $this->withHost($organizationDto->slug . '.packio.us');
        $identity->banUser($userDto->id, $moderator->id, 'Violation of project rules.');

        $this->get('/private/packages.json', [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])->assertStatus(403);
        self::assertNull($tokens->findById($issuedRegistryAccessTokenDto->id)?->lastUsedAt());

        $identity->unbanUser($userDto->id, $moderator->id);

        $this->get('/private/packages.json', [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])->assertOk();
        self::assertNotNull($tokens->findById($issuedRegistryAccessTokenDto->id)?->lastUsedAt());
    }

    #[Test]
    public function itKeepsAnonymousPublicMetadataAvailableWhenAProvidedTokenOwnerIsBanned(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: true);
        $userDto         = $this->createUser();
        $this->createPackageWithRelease($organizationDto, 'vendor/banned-owner-public-package', 'public');

        /** @var IdentityApiFacadeInterface $identity */
        $identity  = $this->getService(IdentityApiFacadeInterface::class);
        $moderator = $identity->createUser(
            'registry-anonymous-moderator-' . $this->suffix() . '@example.test',
            'Registry Anonymous Moderator',
            'Password1!',
            ['admin'],
        );
        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Banned public metadata token',
            ['packages.read.private'],
        );

        $identity->banUser($userDto->id, $moderator->id, 'Violation of project rules.');
        $this->withHost($organizationDto->slug . '.packio.us');

        $this->get('/p2/vendor/banned-owner-public-package', [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])->assertOk();
    }

    #[Test]
    public function itRejectsRegistryTokenWithoutPrivateReadPermissions(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $this->createPackageWithRelease($organizationDto, 'vendor/private-package', 'private');

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Read-only token',
            ['packages.read.public'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $this->get('/private/packages.json', [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'Registry token does not have the required permissions.')
        ;
    }

    #[Test]
    public function itAllowsRegistryTokenWildcardPermissionToReadPrivateRegistryMetadata(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $this->createPackageWithRelease($organizationDto, 'vendor/private-package', 'private');

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Wildcard read token',
            ['packages.read.*'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $this->get('/private/packages.json', [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])->assertOk();
    }

    #[Test]
    public function itRejectsInvalidRegistryAuthorizationHeaderGracefully(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $this->createPackageWithRelease($organizationDto, 'vendor/private-package', 'private');
        $this->withHost($organizationDto->slug . '.packio.us');

        $this->get('/private/packages.json', [
            'Authorization' => 'not-a-valid-header',
        ])
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="Packio"')
            ->assertJsonPath('error', 'Authentication required. Provide a valid registry token via Authorization header.')
        ;
    }

    #[Test]
    public function itRejectsRegistryTokenFromAnotherOrganizationOnSubdomainHost(): void
    {
        $organizationDto = $this->createOrganization(name: 'stage-three-org-a-' . $this->suffix());
        $organizationB   = $this->createOrganization(name: 'stage-three-org-b-' . $this->suffix());
        $userDto         = $this->createUser();

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationB->id,
            $userDto->id,
            'Org B token',
            ['packages.read.private'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $this->get('/private/packages.json', [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'Registry token does not belong to this organization.')
        ;
    }

    #[Test]
    public function itUsesReleasesFromRequestedOrganizationOnly(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: true);
        $organizationB   = $this->createOrganization(anonymousAccess: true);

        $this->createPackageWithRelease($organizationDto, 'vendor/collision-package', 'public', '1.0.0');
        $this->createPackageWithRelease($organizationB, 'vendor/collision-package', 'public', '2.0.0');

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get('/p2/vendor/collision-package')->assertOk();
        $releases     = $testResponse->json()['packages']['vendor/collision-package'] ?? [];

        self::assertCount(1, $releases);
        self::assertSame('1.0.0', $releases[0]['version'] ?? null);
    }

    #[Test]
    public function itExcludesInactivePublicPackageFromRegistryMetadata(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: true);
        $this->createPackageWithRelease(
            $organizationDto,
            'vendor/hidden-package',
            'public',
            active: false,
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get('/packages.json')->assertOk();
        self::assertNotContains('vendor/hidden-package', $testResponse->json()['available-packages'] ?? []);

        $this->get('/p2/vendor/hidden-package')->assertStatus(404);
    }

    #[Test]
    public function itExcludesInactiveReleaseFromRegistryMetadata(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: true);
        $this->createPackageWithRelease($organizationDto, 'vendor/release-filter-package', 'public', '1.0.0');
        $this->createRelease($organizationDto, 'vendor/release-filter-package', '2.0.0', active: false);

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get('/p2/vendor/release-filter-package')->assertOk();
        $releases     = $testResponse->json()['packages']['vendor/release-filter-package'] ?? [];

        self::assertCount(1, $releases);
        self::assertSame('1.0.0', $releases[0]['version'] ?? null);
    }

    #[Test]
    public function itDownloadsPrivateDistArchiveAndSchedulesDownloadCountJob(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $sourceId        = $this->createGithubSource($organizationDto, 'vendor/private-dist-package');
        $this->createPackageWithRelease(
            $organizationDto,
            'vendor/private-dist-package',
            'private',
            sourceId: $sourceId,
        );

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get($this->distPath('vendor/private-dist-package'), [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ]);

        $testResponse->assertStatus(200);
        $testResponse->assertHeader('Content-Type', 'application/zip');
        $testResponse->assertHeader('Content-Disposition', 'attachment; filename="private-dist-package-' . self::RELEASE_REFERENCE . '.zip"');
        self::assertSame('', $testResponse->header('Set-Cookie'));
        self::assertSame('private, max-age=31536000, immutable', $testResponse->header('Cache-Control'));
        self::assertSame('zip-content', $testResponse->body());

        /** @var JobRepositoryInterface $jobs */
        $jobs             = $this->getService(JobRepositoryInterface::class);
        $organizationJobs = $jobs->findByOrganization($organizationDto->id);

        self::assertCount(1, $organizationJobs);
        self::assertSame('increment.download.count', $organizationJobs[0]->type());
        self::assertSame($organizationDto->id, $organizationJobs[0]->payload()['organization_id'] ?? null);
        self::assertSame('vendor/private-dist-package', $organizationJobs[0]->payload()['package_name'] ?? null);
        self::assertSame('1.0.0', $organizationJobs[0]->payload()['version'] ?? null);
        self::assertCount(3, $organizationJobs[0]->payload());
        self::assertArrayNotHasKey('ip', $organizationJobs[0]->payload());
        self::assertArrayNotHasKey('downloaded_at', $organizationJobs[0]->payload());

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $package  = $packages->findPackageInOrganization($organizationDto->id, 'vendor/private-dist-package');
        self::assertSame(0, $package?->downloadCount);

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $release  = $releases->findRelease('vendor/private-dist-package', '1.0.0', $organizationDto->id);
        self::assertSame(0, $release?->downloadCount);
    }

    #[Test]
    public function itDownloadsBranchDistArchiveWithSlashVersionAndProcessesDownloadCountJob(): void
    {
        $version         = 'dev-refactor/new-di-cache';
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $sourceId        = $this->createGithubSource($organizationDto, 'vendor/branch-dist-package');
        $this->createPackageWithRelease(
            $organizationDto,
            'vendor/branch-dist-package',
            'private',
            version: $version,
            sourceId: $sourceId,
        );

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );
        $headers = [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ];

        $this->withHost($organizationDto->slug . '.packio.us');

        $metadata = $this->get('/p2/vendor/branch-dist-package', $headers)->assertOk();
        self::assertSame(
            'https://' . $organizationDto->slug . '.packio.us' . $this->distPath('vendor/branch-dist-package', $version),
            $metadata->json()['packages']['vendor/branch-dist-package'][0]['dist']['url'] ?? null,
        );

        $testResponse = $this->get($this->distPath('vendor/branch-dist-package', $version), $headers);

        $testResponse->assertStatus(200);
        $testResponse->assertHeader('Content-Type', 'application/zip');
        self::assertSame('zip-content', $testResponse->body());

        /** @var JobRepositoryInterface $jobRepository */
        $jobRepository    = $this->getService(JobRepositoryInterface::class);
        $organizationJobs = $jobRepository->findByOrganization($organizationDto->id, [
            'type' => 'increment.download.count',
        ]);

        self::assertCount(1, $organizationJobs);
        self::assertSame('vendor/branch-dist-package', $organizationJobs[0]->payload()['package_name'] ?? null);
        self::assertSame($version, $organizationJobs[0]->payload()['version'] ?? null);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($organizationJobs[0]);
        // Counters are atomic SQL updates; the runtime resets the heap between jobs.
        $this->getService(ORMInterface::class)->getHeap()->clean();

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $package  = $packages->findPackageInOrganization($organizationDto->id, 'vendor/branch-dist-package');
        self::assertSame(1, $package?->downloadCount);

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $release  = $releases->findRelease('vendor/branch-dist-package', $version, $organizationDto->id);
        self::assertSame(1, $release?->downloadCount);
    }

    #[Test]
    public function itServesSecondPrivateDistArchiveDownloadFromArchiveStorage(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $sourceId        = $this->createGithubSource($organizationDto, 'vendor/cached-dist-package');
        $this->createPackageWithRelease(
            $organizationDto,
            'vendor/cached-dist-package',
            'private',
            sourceId: $sourceId,
        );

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );
        $headers = [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ];

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get($this->distPath('vendor/cached-dist-package'), $headers);
        $testResponse->assertOk();
        self::assertSame('zip-content', $testResponse->body());
        self::assertSame(1, $this->fakeDistArchiveFetcher->fetchCount);

        $secondResponse = $this->get($this->distPath('vendor/cached-dist-package'), $headers);
        $secondResponse->assertOk();
        self::assertSame('zip-content', $secondResponse->body());
        self::assertSame(1, $this->fakeDistArchiveFetcher->fetchCount);
    }

    #[Test]
    public function itReturnsBadGatewayWhenPrivateDistArchiveFetchFails(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $sourceId        = $this->createGithubSource($organizationDto, 'vendor/failing-dist-package');
        $this->createPackageWithRelease(
            $organizationDto,
            'vendor/failing-dist-package',
            'private',
            sourceId: $sourceId,
        );
        $this->fakeDistArchiveFetcher->throwOnFetch = true;

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get($this->distPath('vendor/failing-dist-package'), [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ]);

        $testResponse->assertStatus(502);
        $testResponse->assertHeader('Content-Type', 'text/plain');
        self::assertSame('Archive could not be fetched from upstream.', $testResponse->body());

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertSame([], $jobs->findByOrganization($organizationDto->id));
    }

    #[Test]
    public function itFallsBackToSourceWhenPrivateDistArchiveStorageReadFails(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $sourceId        = $this->createGithubSource($organizationDto, 'vendor/storage-failing-dist-package');
        $this->createPackageWithRelease(
            $organizationDto,
            'vendor/storage-failing-dist-package',
            'private',
            sourceId: $sourceId,
        );
        $this->configurablePackageArchiveStorage->failRead = true;

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get($this->distPath('vendor/storage-failing-dist-package'), [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ]);

        $testResponse->assertStatus(200);
        $testResponse->assertHeader('Content-Type', 'application/zip');
        self::assertSame('zip-content', $testResponse->body());
        self::assertSame(2, $this->fakeDistArchiveFetcher->fetchCount);

        /** @var JobRepositoryInterface $jobs */
        $jobs             = $this->getService(JobRepositoryInterface::class);
        $organizationJobs = $jobs->findByOrganization($organizationDto->id);
        self::assertCount(1, $organizationJobs);
        self::assertSame('increment.download.count', $organizationJobs[0]->type());
    }

    #[Test]
    public function itFallsBackToSourceWhenPrivateDistArchiveStorageExistenceCheckFails(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $sourceId        = $this->createGithubSource($organizationDto, 'vendor/storage-exists-failing-dist-package');
        $this->createPackageWithRelease(
            $organizationDto,
            'vendor/storage-exists-failing-dist-package',
            'private',
            sourceId: $sourceId,
        );
        $this->configurablePackageArchiveStorage->failExists = true;

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get($this->distPath('vendor/storage-exists-failing-dist-package'), [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ]);

        $testResponse->assertStatus(200);
        $testResponse->assertHeader('Content-Type', 'application/zip');
        self::assertSame('zip-content', $testResponse->body());
        self::assertSame(1, $this->fakeDistArchiveFetcher->fetchCount);

        /** @var JobRepositoryInterface $jobs */
        $jobs             = $this->getService(JobRepositoryInterface::class);
        $organizationJobs = $jobs->findByOrganization($organizationDto->id);
        self::assertCount(1, $organizationJobs);
        self::assertSame('increment.download.count', $organizationJobs[0]->type());
    }

    #[Test]
    public function itFallsBackToSourceWhenPrivateDistArchiveStorageWriteFails(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $sourceId        = $this->createGithubSource($organizationDto, 'vendor/storage-write-failing-dist-package');
        $this->createPackageWithRelease(
            $organizationDto,
            'vendor/storage-write-failing-dist-package',
            'private',
            sourceId: $sourceId,
        );
        $this->configurablePackageArchiveStorage->failPut = true;

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $testResponse = $this->get($this->distPath('vendor/storage-write-failing-dist-package'), [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ]);

        $testResponse->assertStatus(200);
        $testResponse->assertHeader('Content-Type', 'application/zip');
        self::assertSame('zip-content', $testResponse->body());
        self::assertSame(2, $this->fakeDistArchiveFetcher->fetchCount);

        /** @var JobRepositoryInterface $jobs */
        $jobs             = $this->getService(JobRepositoryInterface::class);
        $organizationJobs = $jobs->findByOrganization($organizationDto->id);
        self::assertCount(1, $organizationJobs);
        self::assertSame('increment.download.count', $organizationJobs[0]->type());
    }

    #[Test]
    public function itDownloadsCodebergDistArchiveAndSchedulesDownloadCountJob(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $sourceId        = $this->createCodebergSource($organizationDto, 'vendor/codeberg-dist-package');
        $this->createPackageWithRelease(
            $organizationDto,
            'vendor/codeberg-dist-package',
            'private',
            sourceId: $sourceId,
            sourceType: 'codeberg',
        );

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');

        $metadata = $this->get('/p2/vendor/codeberg-dist-package', [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])->assertOk();
        self::assertSame(
            'https://' . $organizationDto->slug . '.packio.us' . $this->distPath('vendor/codeberg-dist-package'),
            $metadata->json()['packages']['vendor/codeberg-dist-package'][0]['dist']['url'] ?? null,
        );

        $testResponse = $this->get($this->distPath('vendor/codeberg-dist-package'), [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ]);

        $testResponse->assertStatus(200);
        $testResponse->assertHeader('Content-Type', 'application/zip');
        self::assertSame('zip-content', $testResponse->body());

        /** @var JobRepositoryInterface $jobs */
        $jobs             = $this->getService(JobRepositoryInterface::class);
        $organizationJobs = $jobs->findByOrganization($organizationDto->id);

        self::assertCount(1, $organizationJobs);
        self::assertSame('increment.download.count', $organizationJobs[0]->type());
        self::assertSame('vendor/codeberg-dist-package', $organizationJobs[0]->payload()['package_name'] ?? null);
        self::assertSame('1.0.0', $organizationJobs[0]->payload()['version'] ?? null);
    }

    #[Test]
    public function itListsOnlyRequestedOrganizationPackagesInPublicBootstrap(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: true);
        $organizationB   = $this->createOrganization(anonymousAccess: true);
        $nameA           = 'vendor/bootstrap-a-' . $this->suffix();
        $nameB           = 'vendor/bootstrap-b-' . $this->suffix();

        $this->createPackageWithRelease($organizationDto, $nameA, 'public');
        $this->createPackageWithRelease($organizationB, $nameB, 'public');

        $this->withHost($organizationDto->slug . '.packio.us');

        $available = $this->get('/packages.json')->assertOk()->json()['available-packages'] ?? [];

        self::assertContains($nameA, $available);
        self::assertNotContains($nameB, $available);
    }

    #[Test]
    public function distDownloadPassesSourceTokenToFetcher(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();

        /** @var SourceApiFacadeInterface $sources */
        $sources         = $this->getService(SourceApiFacadeInterface::class);
        $authorizationId = $sources->authorizeProvider(new ProviderAuthorizationRequestDto(
            'github',
            $organizationDto->id,
            'token',
            [
                'token' => 'source-secret-token',
            ],
        ))->id;
        $sourceId = $this->createGithubSource($organizationDto, 'vendor/token-package', $authorizationId);
        $this->createPackageWithRelease($organizationDto, 'vendor/token-package', 'private', sourceId: $sourceId);

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');
        $this->get($this->distPath('vendor/token-package'), [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])->assertOk();

        self::assertSame('source-secret-token', $this->fakeDistArchiveFetcher->lastToken);
    }

    #[Test]
    public function packagesJsonOfOrganizationAOmitsPackagesOfOrganizationB(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $organizationB   = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();
        $nameA           = 'vendor/isolation-a-' . $this->suffix();
        $nameB           = 'vendor/isolation-b-' . $this->suffix();
        $this->createPackageWithRelease($organizationDto, $nameA, 'private');
        $this->createPackageWithRelease($organizationB, $nameB, 'private');

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $organizationDto->id,
            $userDto->id,
            'Composer client',
            ['packages.read.private'],
        );

        $this->withHost($organizationDto->slug . '.packio.us');
        $body = $this->get('/private/packages.json', [
            'Authorization' => 'Bearer ' . $issuedRegistryAccessTokenDto->plainTextToken,
        ])->assertOk()->json();

        self::assertContains($nameA, $body['available-packages'] ?? []);
        self::assertNotContains($nameB, $body['available-packages'] ?? []);
    }

    #[Test]
    public function itRejectsAnonymousDistDownloadOfPrivatePackageWhenAnonymousAccessIsEnabled(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: true);
        $sourceId        = $this->createGithubSource($organizationDto, 'vendor/anonymous-private-dist');
        $this->createPackageWithRelease($organizationDto, 'vendor/anonymous-private-dist', 'private', sourceId: $sourceId);

        $this->withHost($organizationDto->slug . '.packio.us');

        $this->get($this->distPath('vendor/anonymous-private-dist'))->assertStatus(401);

        self::assertSame(0, $this->fakeDistArchiveFetcher->fetchCount);
    }

    private function createUser(): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'registry-' . $this->suffix() . '@example.test',
            'Registry User',
            'Password1!',
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createOrganization(?string $name = null, bool $anonymousAccess = false): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations   = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDto = $organizations->registerOrganization($name ?? 'stage-three-registry-' . $this->suffix());

        return $anonymousAccess
            ? $organizations->updateOrganization($organizationDto->id, $organizationDto->name, $organizationDto->slug, true)
            : $organizationDto;
    }

    private function createPackageWithRelease(
        OrganizationDto $organizationDto,
        string $packageName,
        string $visibility,
        string $version = '1.0.0',
        bool $active = true,
        string $sourceId = '',
        string $sourceType = 'github',
    ): void {
        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $packages->upsert(new PackageDto(
            name: $packageName,
            type: 'library',
            description: 'Registry package ' . $packageName,
            visibility: $visibility,
            sourceType: $sourceType,
            sourceUrl: 'codeberg' === $sourceType
                ? 'https://codeberg.org/' . $packageName
                : 'https://github.com/' . $packageName,
            createdAt: '2026-05-20T00:00:00+00:00',
            updatedAt: '2026-05-20T00:00:00+00:00',
            organizationId: $organizationDto->id,
            sourceId: '' !== $sourceId ? $sourceId : $packageName,
        ));

        if (! $active) {
            $packages->updateActivityBySourceIdInContext(
                new ActorContext($organizationDto->id, 'test-owner', 'owner'),
                $packageName,
                false,
            );
        }

        $this->createRelease($organizationDto, $packageName, $version);
    }

    private function createRelease(OrganizationDto $organizationDto, string $packageName, string $version, bool $active = true): void
    {
        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $releases->publish(new ReleaseDto(
            packageName: $packageName,
            version: $version,
            reference: self::RELEASE_REFERENCE,
            composer: [
                'name'        => $packageName,
                'description' => 'Registry package ' . $packageName,
                'type'        => 'library',
            ],
            source: [
                'reference' => self::RELEASE_REFERENCE,
            ],
            dist: [
                'reference' => self::RELEASE_REFERENCE,
            ],
            createdAt: '2026-05-20T00:00:00+00:00',
            organizationId: $organizationDto->id,
            releasedAt: '2024-04-01T12:00:00+00:00',
        ));

        if (! $active) {
            $releases->updateActivityInContext(
                new ActorContext($organizationDto->id, 'test-owner', 'owner'),
                $packageName,
                $version,
                false,
            );
        }
    }

    private function distPath(string $packageName, string $version = '1.0.0'): string
    {
        return '/dist/' . $packageName . '/' . rawurlencode($version) . '/' . self::RELEASE_REFERENCE . '.zip';
    }

    private function suffix(): string
    {
        return bin2hex(random_bytes(6));
    }

    private function createGithubSource(OrganizationDto $organizationDto, string $repository, string $providerAuthorizationId = ''): string
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources   = $this->getService(SourceApiFacadeInterface::class);
        $sourceDto = $sources->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/' . $repository,
            organizationId: $organizationDto->id,
            visibility: 'private',
            providerAuthorizationId: $providerAuthorizationId,
        ));

        return $sourceDto->id;
    }

    private function createCodebergSource(OrganizationDto $organizationDto, string $repository): string
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources   = $this->getService(SourceApiFacadeInterface::class);
        $sourceDto = $sources->registerSource(new SourceRegistrationRequestDto(
            provider: 'codeberg',
            resource: 'https://codeberg.org/' . $repository,
            organizationId: $organizationDto->id,
            visibility: 'private',
        ));

        return $sourceDto->id;
    }
}

final class FakeDistArchiveFetcher implements DistArchiveFetcherInterface
{
    public int $fetchCount    = 0;
    public bool $throwOnFetch = false;
    public ?string $lastToken = null;

    /**
     * @param array<string, mixed> $providerConfig
     */
    public function fetchArchive(
        string $provider,
        string $owner,
        string $repository,
        string $reference,
        ?string $token = null,
        string $baseUrl = '',
        array $providerConfig = [],
    ): DistArchiveStream {
        ++$this->fetchCount;
        $this->lastToken = $token;

        if ($this->throwOnFetch) {
            throw new RuntimeException('Upstream archive fetch failed.');
        }

        $resource = fopen('php://temp', 'rb+');
        if (false === $resource) {
            throw new RuntimeException('Failed to open archive stream.');
        }

        fwrite($resource, 'zip-content');
        rewind($resource);

        return new DistArchiveStream(new Stream($resource), 'application/zip', 11);
    }
}

final class ConfigurablePackageArchiveStorage implements PackageArchiveStorageInterface
{
    public bool $failExists = false;
    public bool $failPut    = false;
    public bool $failRead   = false;

    private readonly InMemoryPackageArchiveStorage $inMemoryPackageArchiveStorage;

    public function __construct()
    {
        $this->inMemoryPackageArchiveStorage = new InMemoryPackageArchiveStorage();
    }

    public function exists(string $key): bool
    {
        if ($this->failExists) {
            throw new RuntimeException('Archive storage existence check failed.');
        }

        return $this->inMemoryPackageArchiveStorage->exists($key);
    }

    public function list(string $prefix): iterable
    {
        return $this->inMemoryPackageArchiveStorage->list($prefix);
    }

    public function put(string $key, mixed $body, ArchiveObjectMetadata $archiveObjectMetadata): StoredArchive
    {
        if ($this->failPut) {
            throw new RuntimeException('Archive storage write failed.');
        }

        return $this->inMemoryPackageArchiveStorage->put($key, $body, $archiveObjectMetadata);
    }

    public function read(string $key): ArchiveReadStream
    {
        if ($this->failRead) {
            throw new RuntimeException('Archive storage read failed.');
        }

        return $this->inMemoryPackageArchiveStorage->read($key);
    }

    public function delete(string $key): void
    {
        $this->inMemoryPackageArchiveStorage->delete($key);
    }
}
