<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\Persistence\Cycle;

use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;

final class OrganizationMemberRepositoryPaginationTest extends IntegrationTestCase
{
    #[Test]
    public function itCountsBeforeSelectingOnlyTheCanonicalRequestedMemberPage(): void
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations    = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDto  = $organizations->registerOrganization('member-page-' . bin2hex(random_bytes(6)));

        /** @var OrganizationMemberRepositoryInterface $members */
        $members = $this->getService(OrganizationMemberRepositoryInterface::class);
        for ($index = 0; $index < 101; ++$index) {
            $members->save(OrganizationMember::join($organizationDto->id, 'user-' . $index));
        }

        $page = $members->findPaginatedByOrganization(
            $organizationDto->id,
            new PaginationCriteria(page: 99, perPage: 100),
        );

        self::assertSame(101, $page['total']);
        self::assertSame(2, $page['currentPage']);
        self::assertSame(100, $page['perPage']);
        self::assertCount(1, $page['items']);
    }
}
