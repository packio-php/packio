<?php

declare(strict_types=1);

namespace PackioTest\Feature\Rest;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;

final class ApiPackageDeletionFeatureTest extends FeatureTestCase
{
    private const string PACKAGE = 'acme/demo';

    private RecordingArchiveStorageCleanupApiFacade $recordingArchiveStorageCleanupApiFacade;

    protected function setUp(): void
    {
        $this->recordingArchiveStorageCleanupApiFacade                          = new RecordingArchiveStorageCleanupApiFacade();
        $this->serviceOverrides[ArchiveStorageCleanupApiFacadeInterface::class] = $this->recordingArchiveStorageCleanupApiFacade;

        parent::setUp();
    }

    #[Test]
    public function deletingPackageDeletesReleasesAndSchedulesArchiveCleanup(): void
    {
        [$organizationDto, $headers] = $this->ownerWithToken();
        $this->publishPackage($organizationDto, $headers, true);

        $this->request('DELETE', $this->packageUrl($organizationDto), [], $headers)->assertStatus(204);

        self::assertSame([[$organizationDto->id, self::PACKAGE, null, 'api']], $this->recordingArchiveStorageCleanupApiFacade->calls);
        self::assertSame([], $this->releases()->releasesForPackage(self::PACKAGE, true, $organizationDto->id));
        self::assertNull($this->packages()->findPackageInOrganization($organizationDto->id, self::PACKAGE));
    }

    #[Test]
    public function deletingPackageWithoutReleasesReturns204(): void
    {
        [$organizationDto, $headers] = $this->ownerWithToken();
        $this->publishPackage($organizationDto, $headers, false);
        $this->recordingArchiveStorageCleanupApiFacade->discovered = 0;

        $this->request('DELETE', $this->packageUrl($organizationDto), [], $headers)->assertStatus(204);

        self::assertNull($this->packages()->findPackageInOrganization($organizationDto->id, self::PACKAGE));
    }

    #[Test]
    public function deletingPackageReturns503WhenArchiveCleanupFails(): void
    {
        [$organizationDto, $headers] = $this->ownerWithToken();
        $this->publishPackage($organizationDto, $headers, true);
        $this->recordingArchiveStorageCleanupApiFacade->failed = 1;

        $testResponse = $this->request('DELETE', $this->packageUrl($organizationDto), [], $headers);

        $testResponse->assertStatus(503);
        self::assertSame('service_unavailable', $testResponse->json()['error']['code'] ?? null);
        self::assertSame('Package deletion is temporarily unavailable. Retry the request.', $testResponse->json()['error']['message'] ?? null);
        self::assertInstanceOf(PackageDto::class, $this->packages()->findPackageInOrganization($organizationDto->id, self::PACKAGE));
        self::assertCount(1, $this->releases()->releasesForPackage(self::PACKAGE, true, $organizationDto->id));
    }

    #[Test]
    public function deletingReleasesSchedulesArchiveCleanupFirst(): void
    {
        [$organizationDto, $headers] = $this->ownerWithToken();
        $this->publishPackage($organizationDto, $headers, true);

        $testResponse = $this->request('DELETE', $this->packageUrl($organizationDto) . '/releases', [], $headers);

        $testResponse->assertStatus(200);
        self::assertSame(1, $testResponse->json()['data']['deleted'] ?? null);
        self::assertSame([[$organizationDto->id, self::PACKAGE, null, 'api']], $this->recordingArchiveStorageCleanupApiFacade->calls);
    }

    #[Test]
    public function deletingReleasesReturns503WhenArchiveCleanupFails(): void
    {
        [$organizationDto, $headers] = $this->ownerWithToken();
        $this->publishPackage($organizationDto, $headers, true);
        $this->recordingArchiveStorageCleanupApiFacade->failed = 1;

        $testResponse = $this->request('DELETE', $this->packageUrl($organizationDto) . '/releases', [], $headers);

        $testResponse->assertStatus(503);
        self::assertSame('service_unavailable', $testResponse->json()['error']['code'] ?? null);
        self::assertCount(1, $this->releases()->releasesForPackage(self::PACKAGE, true, $organizationDto->id));
    }

    private function packageUrl(OrganizationDto $organizationDto): string
    {
        return '/api/v1/organizations/' . $organizationDto->slug . '/packages/' . self::PACKAGE;
    }

    /**
     * @return array{OrganizationDto, array<string, string>}
     */
    private function ownerWithToken(): array
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->organization()->registerOrganization('Deletion ' . bin2hex(random_bytes(4)), $userDto->id);
        $token           = $this->identity()
            ->issueApiAccessTokenForUser($userDto->id, 'Deletion API token', ['write'])
            ->plainTextToken
        ;

        return [$organizationDto, [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]];
    }

    /**
     * @param array<string, string> $headers
     */
    private function publishPackage(OrganizationDto $organizationDto, array $headers, bool $withRelease): void
    {
        $testResponse = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider'   => 'generic_git',
            'resource'   => 'https://git.example.test/acme/demo-' . bin2hex(random_bytes(4)) . '.git',
            'visibility' => 'private',
        ], $headers);
        $testResponse->assertStatus(201);
        $sourceId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($sourceId);

        $this->packages()->upsert(new PackageDto(
            name: self::PACKAGE,
            type: 'library',
            description: 'Demo package',
            visibility: 'private',
            sourceType: 'generic_git',
            sourceUrl: 'https://git.example.test/acme/demo.git',
            createdAt: '',
            updatedAt: '',
            organizationId: $organizationDto->id,
            active: true,
            sourceId: $sourceId,
            downloadCount: 0,
        ));

        if (! $withRelease) {
            return;
        }

        $this->releases()->publish(new ReleaseDto(
            packageName: self::PACKAGE,
            version: '1.0.0',
            reference: 'abc123',
            composer: [
                'name'    => self::PACKAGE,
                'version' => '1.0.0',
            ],
            source: [
                'type'      => 'git',
                'url'       => 'https://git.example.test/acme/demo.git',
                'reference' => 'abc123',
            ],
            dist: [
                'type' => 'zip',
                'url'  => 'https://packio.us/dist/acme/demo/1.0.0.zip',
            ],
            createdAt: '',
            organizationId: $organizationDto->id,
            active: true,
            downloadCount: 0,
        ));
    }

    private function createVerifiedUser(string $prefix): UserDto
    {
        $userDto = $this->identity()->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            'Deletion User',
            'Password1!',
        );
        $this->identity()->markUserEmailAsVerified($userDto->id);

        $verified = $this->identity()->findUserById($userDto->id);
        self::assertInstanceOf(UserDto::class, $verified);

        return $verified;
    }

    private function identity(): IdentityApiFacadeInterface
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);

        return $identity;
    }

    private function organization(): OrganizationApiFacadeInterface
    {
        $organization = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organization);

        return $organization;
    }

    private function packages(): PackageApiFacadeInterface
    {
        $packages = $this->getService(PackageApiFacadeInterface::class);
        self::assertInstanceOf(PackageApiFacadeInterface::class, $packages);

        return $packages;
    }

    private function releases(): ReleaseApiFacadeInterface
    {
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        self::assertInstanceOf(ReleaseApiFacadeInterface::class, $releases);

        return $releases;
    }
}

final class RecordingArchiveStorageCleanupApiFacade implements ArchiveStorageCleanupApiFacadeInterface
{
    /** @var list<array{0: string, 1: string, 2: ?string, 3: string}> */
    public array $calls = [];

    public int $discovered = 1;

    public int $failed = 0;

    public function schedulePackageArchiveCleanup(
        string $organizationId,
        string $packageName,
        ?string $deletionRunId = null,
        string $origin = '',
    ): array {
        $this->calls[] = [$organizationId, $packageName, $deletionRunId, $origin];

        return [
            'discovered' => $this->discovered,
            'scheduled'  => $this->discovered - $this->failed,
            'failed'     => $this->failed,
        ];
    }
}
