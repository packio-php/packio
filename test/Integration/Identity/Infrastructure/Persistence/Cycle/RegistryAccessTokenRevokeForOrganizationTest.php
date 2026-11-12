<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Persistence\Cycle;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\RegistryTokenCommandService;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class RegistryAccessTokenRevokeForOrganizationTest extends IntegrationTestCase
{
    #[Test]
    public function revokesOnlyTheOrganizationTokens(): void
    {
        [$orgA, $userA] = $this->createOwner();
        [$orgB, $userB] = $this->createOwner();
        $service        = $this->getService(RegistryTokenCommandService::class);
        $service->issueForOrganizationUser($orgA, $userA, 'One');
        $service->issueForOrganizationUser($orgA, $userA, 'Two');
        $service->issueForOrganizationUser($orgB, $userB, 'Other');

        self::assertSame(2, $service->revokeForOrganization($orgA));

        $repository = $this->getService(RegistryAccessTokenRepositoryInterface::class);
        self::assertCount(0, $repository->latestForOrganization($orgA));
        self::assertCount(1, $repository->latestForOrganization($orgB));
    }

    /**
     * @return array{string, string}
     */
    private function createOwner(): array
    {
        $user         = $this->getService(IdentityApiFacadeInterface::class)->createUser(Uuid::uuid7()->toString() . '@example.test', 'Owner', 'Password1!');
        $organization = $this->getService(OrganizationApiFacadeInterface::class)->registerOrganization(Uuid::uuid7()->toString(), $user->id);

        return [$organization->id, $user->id];
    }
}
