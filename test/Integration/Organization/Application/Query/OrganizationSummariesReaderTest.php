<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Application\Query;

use Cycle\Database\DatabaseManager;
use Cycle\ORM\Heap\Heap;
use Cycle\ORM\ORMInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationsHandler;
use Packio\Organization\Application\Query\OrganizationSummariesReaderInterface;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Shared\Domain\Pagination\SortDirection;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;

use function min;

final class OrganizationSummariesReaderTest extends IntegrationTestCase
{
    #[Test]
    public function summariesHaveExactCountsAndBoundedQueries(): void
    {
        /** @var OrganizationRepositoryInterface $organizations */
        $organizations = $this->getService(OrganizationRepositoryInterface::class);

        /** @var OrganizationMemberRepositoryInterface $members */
        $members = $this->getService(OrganizationMemberRepositoryInterface::class);

        /** @var OrganizationInvitationRepositoryInterface $invitations */
        $invitations = $this->getService(OrganizationInvitationRepositoryInterface::class);

        /** @var OrganizationSummariesReaderInterface $reader */
        $reader    = $this->getService(OrganizationSummariesReaderInterface::class);
        $summaries = static fn (PaginationCriteria $paginationCriteria): PaginatedResultDto => new PaginatedResultDto(...$reader->fetch($paginationCriteria));

        /** @var DatabaseManager $database */
        $database              = $this->getService('dbal');
        $paginatedResultDto    = $summaries(new PaginationCriteria(page: 99));
        self::assertSame([], $paginatedResultDto->items);
        self::assertSame(0, $paginatedResultDto->total);
        self::assertSame(1, $paginatedResultDto->currentPage);
        $ids = [];
        for ($i = 0; $i < 5; ++$i) {
            $organization = new Organization(slug: "org-{$i}", name: "Organization {$i}", anonymousAccess: true, active: 4 !== $i, status: 4 === $i ? 'deleting' : 'active');
            $organizations->save($organization);
            $id    = $organization->getIdentifier()->toString();
            $database->database()->execute('UPDATE organizations SET created_at = ?, updated_at = NULL WHERE uuid = ?', ['2026-01-0' . ($i + 1), $id]);
            $ids[] = $id;
            for ($j = 0; $j < $i; ++$j) {
                $members->save(OrganizationMember::join($id, "user-{$j}"));
            }

            for ($j = 0; $j < $i * 2; ++$j) {
                $invitation = OrganizationInvitation::invite($id, 'inviter');
                if (0 === $j % 2) {
                    $invitation->revoke();
                }
                $invitations->save($invitation);
            }
        }

        /** @var ORMInterface $orm */
        $orm = $this->getService(ORMInterface::class);
        $orm->getHeap()->clean();
        foreach ([1, 3, 100] as $size) {
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::exactly(2))->method('info');
            $database->setLogger($logger);
            $result = $summaries(new PaginationCriteria(perPage: $size));
            $database->setLogger(new NullLogger());
            self::assertSame(5, $result->total);
            self::assertSame(1, $result->currentPage);
            self::assertSame($size, $result->perPage);
            self::assertCount(min(5, $size), $result->items);
            foreach ($result->items as $index => $summary) {
                $expected = 4 - $index;
                self::assertSame($ids[$expected], $summary->id);
                self::assertSame($expected, $summary->memberCount);
                self::assertSame($expected * 2, $summary->invitationCount);
            }
        }
        $last = $summaries(new PaginationCriteria(page: 999, perPage: 2));
        self::assertSame(3, $last->currentPage);
        self::assertSame(3, $last->lastPage());
        self::assertSame(5, $last->from());
        self::assertSame(5, $last->to());
        self::assertCount(1, $last->items);
        self::assertSame($ids[0], $last->items[0]->id);
        $search = $summaries(new PaginationCriteria(search: 'org-2'));
        self::assertSame(1, $search->total);
        self::assertSame($ids[2], $search->items[0]->id);
        self::assertSame(0, $summaries(new PaginationCriteria(search: '%'))->total);
        $ascending = $summaries(new PaginationCriteria(sortBy: 'name', sortDirection: SortDirection::ASC));
        self::assertSame($ids[0], $ascending->items[0]->id);

        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->expects(self::once())->method('render')->with('Admin/Organizations', self::callback(static function(array $props) use ($ids): bool {
            $organizations = $props['organizations']();
            self::assertCount(5, $organizations);
            self::assertSame($ids[4], $organizations[0]['id']);
            self::assertSame(4, $organizations[0]['members_count']);
            self::assertSame(8, $organizations[0]['invitations_count']);
            self::assertFalse($organizations[0]['active']);
            self::assertTrue($organizations[0]['anonymous_access']);
            self::assertSame('deleting', $organizations[0]['status']);
            self::assertSame('2026-01-05T00:00:00+00:00', $organizations[0]['created_at']);
            self::assertSame('', $organizations[0]['updated_at']);

            return true;
        }))->willReturn(new Response());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');
        $database->setLogger($logger);
        $this->getService(AdminOrganizationsHandler::class)->handle(new ServerRequest()->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia));
        $database->setLogger(new NullLogger());
        $heap = $orm->getHeap();
        self::assertInstanceOf(Heap::class, $heap);
        self::assertCount(0, $heap->getIterator());
    }
}
