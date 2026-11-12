<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseProviderInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\RegistryTokenCommandService;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function hash;

final class RegistryAccessTokenRegenerationTest extends IntegrationTestCase
{
    #[Test]
    public function itPreservesPropertiesAndReplacesTheUsableSecret(): void
    {
        [$organizationId, $userId] = $this->createOwner();
        $service                   = $this->getService(RegistryTokenCommandService::class);
        $identity                  = $this->getService(IdentityApiFacadeInterface::class);
        $issued                    = $service->issueForOrganizationUser($organizationId, $userId, 'Deploy', ['packages:read:private']);
        self::assertNotNull($identity->authenticateRegistryAccessToken($issued->plainTextToken));

        $result = $service->regenerateForOrganizationUser($organizationId, $userId, $issued->id);

        self::assertTrue($result->success);
        self::assertSame($issued->id, $result->revokedId);
        self::assertNotNull($result->issued);
        self::assertSame($issued->name, $result->issued->name);
        self::assertSame($issued->permissions, $result->issued->permissions);
        self::assertNotSame($issued->id, $result->issued->id);
        self::assertNotSame($issued->plainTextToken, $result->issued->plainTextToken);
        self::assertNull($identity->authenticateRegistryAccessToken($issued->plainTextToken));
        self::assertNotNull($identity->authenticateRegistryAccessToken($result->issued->plainTextToken));
        self::assertTrue($service->regenerateForOrganizationUser($organizationId, $userId, $issued->id)->isNotFound());
        self::assertCount(1, $this->getService(RegistryAccessTokenRepositoryInterface::class)->latestForOrganization($organizationId));
    }

    #[Test]
    public function itRejectsUnknownTokensOtherOwnersAndOtherOrganizations(): void
    {
        [$organizationId, $userId]           = $this->createOwner();
        [$otherOrganizationId, $otherUserId] = $this->createOwner();
        $service                             = $this->getService(RegistryTokenCommandService::class);
        $issued                              = $service->issueForOrganizationUser($organizationId, $userId, 'Deploy');

        self::assertTrue($service->regenerateForOrganizationUser($otherOrganizationId, $userId, $issued->id)->isNotFound());
        self::assertTrue($service->regenerateForOrganizationUser($organizationId, $otherUserId, $issued->id)->isNotFound());
        self::assertTrue($service->regenerateForOrganizationUser($organizationId, $userId, Uuid::uuid7()->toString())->isNotFound());
        self::assertNotNull($this->getService(IdentityApiFacadeInterface::class)->authenticateRegistryAccessToken($issued->plainTextToken));
    }

    #[Test]
    #[DataProvider('failingWrites')]
    public function itRollsBackFailedWritesAndAllowsRetry(string $operation): void
    {
        [$organizationId, $userId] = $this->createOwner();
        $service                   = $this->getService(RegistryTokenCommandService::class);
        $issued                    = $service->issueForOrganizationUser($organizationId, $userId, 'Deploy');
        $provider                  = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $provider);
        $database = $provider->database('default');
        $database->execute("CREATE FUNCTION fail_registry_regeneration() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'forced regeneration failure'; END; $$ LANGUAGE plpgsql");
        $database->execute('CREATE TRIGGER fail_registry_regeneration BEFORE ' . $operation . ' ON registry_access_tokens FOR EACH ROW EXECUTE FUNCTION fail_registry_regeneration()');

        try {
            $result = $service->regenerateForOrganizationUser($organizationId, $userId, $issued->id);
            self::assertTrue($result->isPersistenceFailed());
        } finally {
            $database->execute('DROP TRIGGER fail_registry_regeneration ON registry_access_tokens');
            $database->execute('DROP FUNCTION fail_registry_regeneration()');
        }

        $repository = $this->getService(RegistryAccessTokenRepositoryInterface::class);
        self::assertCount(1, $repository->latestForOrganization($organizationId));
        self::assertNotNull($repository->findByTokenHash(hash('sha256', $issued->plainTextToken)));
        self::assertNotNull($this->getService(IdentityApiFacadeInterface::class)->authenticateRegistryAccessToken($issued->plainTextToken));
        self::assertTrue($service->regenerateForOrganizationUser($organizationId, $userId, $issued->id)->success);
    }

    /** @return iterable<string, array{string}> */
    public static function failingWrites(): iterable
    {
        yield 'replacement insert' => ['INSERT'];

        yield 'old token delete after replacement insert' => ['DELETE'];
    }

    /** @return array{string, string} */
    private function createOwner(): array
    {
        $user         = $this->getService(IdentityApiFacadeInterface::class)->createUser(Uuid::uuid7()->toString() . '@example.test', 'Owner', 'Password1!');
        $organization = $this->getService(OrganizationApiFacadeInterface::class)->registerOrganization(Uuid::uuid7()->toString(), $user->id);

        return [$organization->id, $user->id];
    }
}
