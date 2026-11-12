<?php

declare(strict_types=1);

namespace PackioTest\Integration\Admin;

use Cake\Chronos\Chronos;
use Cycle\ORM\Heap\Heap;
use Cycle\ORM\ORMInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

final class AdminCollectionCountsTest extends IntegrationTestCase
{
    #[Test]
    public function itCountsAllLifecycleStatesWithoutHydratingCollections(): void
    {
        $organizationRepository = $this->getService(OrganizationRepositoryInterface::class);
        self::assertInstanceOf(OrganizationRepositoryInterface::class, $organizationRepository);
        $packageRepository = $this->getService(PackageRepositoryInterface::class);
        self::assertInstanceOf(PackageRepositoryInterface::class, $packageRepository);
        $sourceRepository = $this->getService(SourceRepositoryInterface::class);
        self::assertInstanceOf(SourceRepositoryInterface::class, $sourceRepository);

        $organization         = $organizationRepository->save(Organization::register('active', 'Active'));
        $deletingOrganization = $organizationRepository->save(new Organization(
            slug: 'deleting',
            name: 'Deleting',
            status: Organization::STATUS_DELETING,
        ));
        $organizationId         = $organization->getIdentifier()->toString();
        $deletingOrganizationId = $deletingOrganization->getIdentifier()->toString();
        $packageRepository->save(new Package(organizationId: $organizationId, name: 'vendor/active'));
        $packageRepository->save(new Package(organizationId: $organizationId, name: 'vendor/inactive', active: false));
        $packageRepository->save(new Package(organizationId: $deletingOrganizationId, name: 'vendor/other'));
        $sourceRepository->save(new Source(organizationId: $organizationId, canonicalUrl: 'https://example.test/active'));
        $sourceRepository->save(new Source(
            organizationId: $organizationId,
            canonicalUrl: 'https://example.test/archived',
            archivedAt: Chronos::now(),
        ));
        $sourceRepository->save(new Source(
            organizationId: $deletingOrganizationId,
            canonicalUrl: 'https://example.test/deleting',
            status: Source::STATUS_DELETING,
        ));

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $packageApiFacade = $this->getService(PackageApiFacadeInterface::class);
        self::assertInstanceOf(PackageApiFacadeInterface::class, $packageApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);
        $orm = $this->getService(ORMInterface::class);
        self::assertInstanceOf(ORMInterface::class, $orm);
        $orm->getHeap()->clean();

        self::assertSame(2, $organizationApiFacade->countOrganizations());
        self::assertSame(3, $packageApiFacade->countPackages());
        self::assertSame(3, $sourceApiFacade->countSources());
        self::assertSame(2, $packageApiFacade->countForOrganization($organizationId));
        self::assertSame(1, $packageApiFacade->countForOrganization($deletingOrganizationId));
        self::assertSame(2, $sourceApiFacade->countForOrganization($organizationId));
        $heap = $orm->getHeap();
        self::assertInstanceOf(Heap::class, $heap);
        self::assertCount(0, $heap->getIterator());
    }
}
