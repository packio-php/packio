<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\Persistence\Cycle;

use Packio\Organization\Api\Exception\OrganizationNameException;
use Packio\Organization\Application\Command\OrganizationCommandService;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function random_int;

final class OrganizationNameAvailabilityTest extends IntegrationTestCase
{
    #[Test]
    public function nameTakenIgnoringCaseAndSpacesIsRejected(): void
    {
        $service = $this->service();
        $suffix  = random_int(100000, 999999);

        $service->registerOrganization('Acme  Corp ' . $suffix);

        $this->expectException(OrganizationNameException::class);
        $service->registerOrganization('acme corp ' . $suffix);
    }

    #[Test]
    public function renameToOwnNameInOtherCaseIsAllowed(): void
    {
        $service      = $this->service();
        $suffix       = random_int(100000, 999999);
        $organization = $service->registerOrganization('Own Name ' . $suffix);

        $updated = $service->updateOrganization(
            $organization->getIdentifier()->toString(),
            'OWN NAME ' . $suffix,
            $organization->slug(),
            false,
        );

        self::assertSame('OWN NAME ' . $suffix, $updated->name());
    }

    #[Test]
    public function renameToAnotherOrganizationNameIsRejected(): void
    {
        $service = $this->service();
        $suffix  = random_int(100000, 999999);

        $service->registerOrganization('First Org ' . $suffix);
        $organization = $service->registerOrganization('Second Org ' . $suffix);

        $this->expectException(OrganizationNameException::class);
        $service->updateOrganization(
            $organization->getIdentifier()->toString(),
            'first org ' . $suffix,
            $organization->slug(),
            false,
        );
    }

    #[Test]
    public function nonAsciiNameCollisionIgnoringCaseIsRejected(): void
    {
        $service = $this->service();
        $suffix  = random_int(100000, 999999);

        $service->registerOrganization('ärzte ' . $suffix);

        $this->expectException(OrganizationNameException::class);
        $service->registerOrganization('ÄRZTE ' . $suffix);
    }

    private function service(): OrganizationCommandService
    {
        $service = $this->getService(OrganizationCommandService::class);
        self::assertInstanceOf(OrganizationCommandService::class, $service);

        return $service;
    }
}
