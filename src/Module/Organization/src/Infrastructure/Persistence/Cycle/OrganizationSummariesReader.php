<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Cycle\ORM\ORMInterface;
use Packio\Organization\Api\Dto\OrganizationSummaryDto;
use Packio\Organization\Application\Query\OrganizationSummariesReaderInterface;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Shared\Domain\Pagination\PaginationCriteria;

use function ceil;
use function count;
use function max;
use function min;
use function str_replace;
use function strtoupper;

final readonly class OrganizationSummariesReader implements OrganizationSummariesReaderInterface
{
    public function __construct(private ORMInterface $orm) {}

    public function fetch(?PaginationCriteria $paginationCriteria): array
    {
        $database   = $this->orm->getSource(Organization::class)->getDatabase();
        $where      = '';
        $parameters = [];
        if (null !== $paginationCriteria?->search && '' !== $paginationCriteria->search) {
            $where      = ' WHERE (o.name ILIKE ? OR o.slug ILIKE ?)';
            $search     = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $paginationCriteria->search) . '%';
            $parameters = [$search, $search];
        }
        $total = 0;
        $page  = 1;
        $limit = '';
        if ($paginationCriteria instanceof PaginationCriteria) {
            $total = (int) $database->query('SELECT COUNT(*) FROM organizations o' . $where, $parameters)->fetchColumn();
            $page  = min($paginationCriteria->page, max(1, (int) ceil($total / $paginationCriteria->perPage)));
            $limit = ' LIMIT ' . $paginationCriteria->perPage . ' OFFSET ' . (($page - 1) * $paginationCriteria->perPage);
        }
        $sort = match ($paginationCriteria?->sortBy) {
            'name'  => 'o.name',
            'slug'  => 'o.slug',
            default => 'o.created_at',
        };
        $direction = $paginationCriteria instanceof PaginationCriteria ? strtoupper($paginationCriteria->sortDirection->value) : 'DESC';
        // Aggregate each relation separately so members and invitations cannot multiply each other's counts.
        $rows = $database->query(
            'SELECT o.*, COALESCE(m.member_count, 0) AS member_count, COALESCE(i.invitation_count, 0) AS invitation_count'
            . ' FROM organizations o'
            . ' LEFT JOIN (SELECT organization_id, COUNT(*) AS member_count FROM organization_members GROUP BY organization_id) m ON m.organization_id = o.uuid::text'
            . ' LEFT JOIN (SELECT organization_id, COUNT(*) AS invitation_count FROM organization_invitations GROUP BY organization_id) i ON i.organization_id = o.uuid::text'
            . $where . ' ORDER BY ' . $sort . ' ' . $direction . ', o.uuid ASC' . $limit,
            $parameters,
        )->fetchAll();
        $items = [];
        foreach ($rows as $row) {
            $items[] = new OrganizationSummaryDto(
                id: (string) $row['uuid'],
                slug: (string) $row['slug'],
                name: (string) $row['name'],
                anonymousAccess: (bool) $row['anonymous_access'],
                active: (bool) $row['active'],
                status: (string) $row['status'],
                createdAt: new Chronos((string) $row['created_at'])->format(DATE_ATOM),
                updatedAt: null === $row['updated_at'] ? '' : new Chronos((string) $row['updated_at'])->format(DATE_ATOM),
                memberCount: (int) $row['member_count'],
                invitationCount: (int) $row['invitation_count'],
            );
        }

        return [
            'items'       => $items,
            'total'       => $paginationCriteria instanceof PaginationCriteria ? $total : count($items),
            'currentPage' => $page,
            'perPage'     => $paginationCriteria->perPage ?? max(1, count($items)),
        ];
    }
}
