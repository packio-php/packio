<?php

declare(strict_types=1);

namespace PackioTest\Feature\Rest;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Http\TestResponse;
use PHPUnit\Framework\Attributes\Test;

use function array_column;
use function bin2hex;
use function explode;
use function random_bytes;

final class ApiOrganizationIsolationFeatureTest extends FeatureTestCase
{
    #[Test]
    public function userCannotAccessForeignOrganizationBySlugOrId(): void
    {
        $fixture = $this->fixture();

        $this->get('/api/v1/organizations/' . $fixture->organizationB->slug, $fixture->headersA)
            ->assertStatus(403)
        ;

        $this->get('/api/v1/organizations/' . $fixture->organizationB->id, $fixture->headersA)
            ->assertStatus(403)
        ;

        $this->request('PATCH', '/api/v1/organizations/' . $fixture->organizationB->slug, [
            'name'             => 'Compromised Organization',
            'anonymous_access' => true,
        ], $fixture->headersA, true)
            ->assertStatus(403)
        ;

        $this->request('DELETE', '/api/v1/organizations/' . $fixture->organizationB->slug, [], $fixture->headersA)
            ->assertStatus(403)
        ;

        $organizationB = $this->organization()->findOrganization($fixture->organizationB->id);
        self::assertInstanceOf(OrganizationDto::class, $organizationB);
        self::assertSame($fixture->organizationB->name, $organizationB->name);
        self::assertFalse($organizationB->anonymousAccess);
    }

    #[Test]
    public function ownerCannotUseForeignResourceIdsUnderOwnedOrganization(): void
    {
        $fixture = $this->fixture();

        $issuedRegistryAccessTokenDto = $this->getService(IdentityCommandService::class)->issueRegistryTokenForUser(
            $fixture->organizationB->id,
            $fixture->ownerB->id,
            'Foreign registry token',
        );

        $this->request(
            'DELETE',
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/registry-tokens/' . $issuedRegistryAccessTokenDto->id,
            [],
            $fixture->headersA,
        )
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found')
        ;
        self::assertNotNull($this->identity()->authenticateRegistryAccessToken($issuedRegistryAccessTokenDto->plainTextToken));

        $sourceA = $this->createSource($fixture->organizationA, $fixture->headersA, 'isolation-a');
        $sourceB = $this->createSource($fixture->organizationB, $fixture->headersB, 'isolation-b');

        $sourceList = $this->get('/api/v1/organizations/' . $fixture->organizationA->slug . '/sources', $fixture->headersA);
        $sourceList->assertStatus(200);
        $listedIds = array_column($sourceList->json()['data'] ?? [], 'id');
        self::assertContains($sourceA, $listedIds);
        self::assertNotContains($sourceB, $listedIds);

        $this->assertApiNotFound($this->get(
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/sources/' . $sourceB,
            $fixture->headersA,
        ));
        $this->assertApiNotFound($this->request(
            'PATCH',
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/sources/' . $sourceB,
            [
                'visibility'         => 'public',
                'active'             => true,
                'keep_last_releases' => 3,
            ],
            $fixture->headersA,
            true,
        ));
        $this->assertApiNotFound($this->request(
            'DELETE',
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/sources/' . $sourceB,
            [],
            $fixture->headersA,
        ));
        $this->get('/api/v1/organizations/' . $fixture->organizationB->slug . '/sources/' . $sourceB, $fixture->headersB)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $sourceB)
        ;

        $authorizationB = $this->createProviderAuthorization($fixture->organizationB, $fixture->headersB);
        $this->assertApiNotFound($this->request(
            'DELETE',
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/provider-authorizations/' . $authorizationB,
            [
                'new_authorization_id' => null,
            ],
            $fixture->headersA,
            true,
        ));
        $testResponse = $this->get('/api/v1/organizations/' . $fixture->organizationB->slug . '/provider-authorizations?provider=generic_git', $fixture->headersB);
        $testResponse->assertStatus(200);
        self::assertContains($authorizationB, array_column($testResponse->json()['data'] ?? [], 'id'));

        $instanceB = $this->createSourceProviderInstance($fixture->organizationB, $fixture->headersB);
        $this->assertApiNotFound($this->request(
            'PATCH',
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/source-provider-instances/' . $instanceB,
            [
                'provider_type' => 'gitlab_self_hosted',
                'label'         => 'Foreign GitLab',
                'base_url'      => 'https://gitlab-foreign-' . bin2hex(random_bytes(4)) . '.example.test',
                'auth_mode'     => 'token',
            ],
            $fixture->headersA,
            true,
        ));
        $this->assertApiNotFound($this->request(
            'DELETE',
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/source-provider-instances/' . $instanceB,
            [],
            $fixture->headersA,
        ));
        $instancesB = $this->get('/api/v1/organizations/' . $fixture->organizationB->slug . '/source-provider-instances?provider_type=gitlab_self_hosted', $fixture->headersB);
        $instancesB->assertStatus(200);
        self::assertContains($instanceB, array_column($instancesB->json()['data'] ?? [], 'id'));

        $jobDto = $this->jobs()->enqueue($fixture->organizationB->id, 'source.sync', [
            'source_id' => $sourceB,
        ]);
        $this->assertApiNotFound($this->get(
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/jobs/' . $jobDto->id,
            $fixture->headersA,
        ));
        $this->assertApiNotFound($this->postJson(
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/jobs/' . $jobDto->id . '/cancel',
            [],
            $fixture->headersA,
        ));
        $this->assertApiNotFound($this->get(
            '/api/v1/organizations/' . $fixture->organizationB->slug . '/jobs/' . $jobDto->id,
            $fixture->headersB,
        ));

        $packageName = 'foreign/pkg-' . bin2hex(random_bytes(3));
        $this->publishPackageAndRelease($fixture->organizationB, $packageName, $sourceB);
        [$vendor, $package] = explode('/', $packageName, 2);

        $this->assertApiNotFound($this->get(
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/packages/' . $vendor . '/' . $package,
            $fixture->headersA,
        ));
        $this->assertApiNotFound($this->request(
            'DELETE',
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/packages/' . $vendor . '/' . $package,
            [],
            $fixture->headersA,
        ));
        $this->assertApiNotFound($this->get(
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/packages/' . $vendor . '/' . $package . '/releases/by-version?version=1.0.0',
            $fixture->headersA,
        ));

        $releaseDelete = $this->request(
            'DELETE',
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/packages/' . $vendor . '/' . $package . '/releases',
            [],
            $fixture->headersA,
        );
        $releaseDelete
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', 0)
        ;
        $this->get('/api/v1/organizations/' . $fixture->organizationB->slug . '/packages/' . $vendor . '/' . $package, $fixture->headersB)
            ->assertStatus(200)
            ->assertJsonPath('data.name', $packageName)
        ;
        $this->get(
            '/api/v1/organizations/' . $fixture->organizationB->slug . '/packages/' . $vendor . '/' . $package . '/releases/by-version?version=1.0.0',
            $fixture->headersB,
        )
            ->assertStatus(200)
            ->assertJsonPath('data.package_name', $packageName)
        ;

        $deliveryB       = 'delivery-' . bin2hex(random_bytes(4));
        $webhookEvent    = WebhookEvent::receive($sourceB, 'github', $deliveryB, 'push', 'payload-hash');
        $webhookEvent->markRejected('Signature mismatch.');
        $this->webhookEvents()->save($webhookEvent);

        $this->assertApiNotFound($this->postJson(
            '/api/v1/organizations/' . $fixture->organizationA->slug . '/sources/' . $sourceA . '/webhook-events/' . $deliveryB . '/reprocess',
            [],
            $fixture->headersA,
        ));

        $storedEventB = $this->webhookEvents()->findBySourceIdAndDeliveryId($sourceB, $deliveryB);
        self::assertInstanceOf(WebhookEvent::class, $storedEventB);
        self::assertSame('rejected', $storedEventB->status());
        self::assertSame(0, $storedEventB->reprocessCount());
    }

    private function fixture(): ApiIsolationFixture
    {
        $userDto           = $this->createVerifiedUser('owner-a');
        $ownerB            = $this->createVerifiedUser('owner-b');
        $organizationDto   = $this->organization()->registerOrganization('Isolation A ' . bin2hex(random_bytes(4)), $userDto->id);
        $orgB              = $this->organization()->registerOrganization('Isolation B ' . bin2hex(random_bytes(4)), $ownerB->id);
        $tokenA            = $this->issueApiToken($userDto, ['write']);
        $tokenB            = $this->issueApiToken($ownerB, ['write']);

        return new ApiIsolationFixture(
            ownerA: $userDto,
            ownerB: $ownerB,
            organizationA: $organizationDto,
            organizationB: $orgB,
            headersA: $this->headers($tokenA),
            headersB: $this->headers($tokenB),
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function createSource(OrganizationDto $organizationDto, array $headers, string $prefix): string
    {
        $testResponse = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider'   => 'generic_git',
            'resource'   => 'https://git.example.test/acme/' . $prefix . '-' . bin2hex(random_bytes(4)) . '.git',
            'visibility' => 'private',
        ], $headers);
        $testResponse->assertStatus(201);

        $sourceId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($sourceId);
        self::assertNotSame('', $sourceId);

        return $sourceId;
    }

    /**
     * @param array<string, string> $headers
     */
    private function createProviderAuthorization(OrganizationDto $organizationDto, array $headers): string
    {
        $testResponse = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/provider-authorizations', [
            'provider'      => 'generic_git',
            'auth_strategy' => 'token',
            'payload'       => [
                'token'       => 'secret-token-' . bin2hex(random_bytes(3)),
                'auth_scheme' => 'bearer',
            ],
        ], $headers);
        $testResponse->assertStatus(201);

        $authorizationId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($authorizationId);
        self::assertNotSame('', $authorizationId);

        return $authorizationId;
    }

    /**
     * @param array<string, string> $headers
     */
    private function createSourceProviderInstance(OrganizationDto $organizationDto, array $headers): string
    {
        $testResponse = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/source-provider-instances', [
            'provider_type' => 'gitlab_self_hosted',
            'label'         => 'GitLab Foreign',
            'base_url'      => 'https://gitlab-' . bin2hex(random_bytes(4)) . '.example.test',
            'auth_mode'     => 'token',
        ], $headers);
        $testResponse->assertStatus(201);

        $instanceId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($instanceId);
        self::assertNotSame('', $instanceId);

        return $instanceId;
    }

    private function publishPackageAndRelease(OrganizationDto $organizationDto, string $packageName, string $sourceId): void
    {
        $this->packages()->upsert(new PackageDto(
            name: $packageName,
            type: 'library',
            description: 'Foreign package',
            visibility: 'private',
            sourceType: 'generic_git',
            sourceUrl: 'https://git.example.test/' . $packageName . '.git',
            createdAt: '',
            updatedAt: '',
            organizationId: $organizationDto->id,
            active: true,
            sourceId: $sourceId,
            downloadCount: 0,
        ));
        $this->releases()->publish(new ReleaseDto(
            packageName: $packageName,
            version: '1.0.0',
            reference: 'abc123',
            composer: [
                'name'    => $packageName,
                'version' => '1.0.0',
            ],
            source: [
                'type'      => 'git',
                'url'       => 'https://git.example.test/' . $packageName . '.git',
                'reference' => 'abc123',
            ],
            dist: [
                'type' => 'zip',
                'url'  => 'https://packio.us/dist/' . $packageName . '/1.0.0.zip',
            ],
            createdAt: '',
            organizationId: $organizationDto->id,
            active: true,
            downloadCount: 0,
        ));
    }

    private function assertApiNotFound(TestResponse $testResponse): void
    {
        $testResponse->assertStatus(404);
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

    private function jobs(): JobApiFacadeInterface
    {
        $jobs = $this->getService(JobApiFacadeInterface::class);
        self::assertInstanceOf(JobApiFacadeInterface::class, $jobs);

        return $jobs;
    }

    private function webhookEvents(): WebhookEventRepositoryInterface
    {
        $webhookEvents = $this->getService(WebhookEventRepositoryInterface::class);
        self::assertInstanceOf(WebhookEventRepositoryInterface::class, $webhookEvents);

        return $webhookEvents;
    }

    private function createVerifiedUser(string $prefix): UserDto
    {
        $userDto = $this->identity()->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            'Isolation User',
            'Password1!',
        );
        $this->identity()->markUserEmailAsVerified($userDto->id);

        $verified = $this->identity()->findUserById($userDto->id);
        self::assertInstanceOf(UserDto::class, $verified);

        return $verified;
    }

    /**
     * @param list<string> $scopes
     */
    private function issueApiToken(UserDto $userDto, array $scopes): string
    {
        return $this->identity()
            ->issueApiAccessTokenForUser($userDto->id, 'Isolation API token', $scopes)
            ->plainTextToken
        ;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $token): array
    {
        return [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}

final readonly class ApiIsolationFixture
{
    /**
     * @param array<string, string> $headersA
     * @param array<string, string> $headersB
     */
    public function __construct(
        public UserDto $ownerA,
        public UserDto $ownerB,
        public OrganizationDto $organizationA,
        public OrganizationDto $organizationB,
        public array $headersA,
        public array $headersB,
    ) {}
}
