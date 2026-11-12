<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Persistence\Cycle;

use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<OrganizationInvitation>
 */
final class OrganizationInvitationRepository extends AbstractWriteRepository implements OrganizationInvitationRepositoryInterface
{
    /**
     * @throws RepositoryPersistException
     */
    public function save(OrganizationInvitation $organizationInvitation): OrganizationInvitation
    {
        $this->persistAndFlush($organizationInvitation);

        return $organizationInvitation;
    }

    public function findById(string $id): ?OrganizationInvitation
    {
        return $this->select()->where([
            'uuid' => $id,
        ])->fetchOne();
    }

    public function findByToken(string $token): ?OrganizationInvitation
    {
        return $this->select()->where([
            'tokenHash' => OrganizationInvitation::hashToken($token),
        ])->fetchOne();
    }

    public function findByOrganization(string $organizationId): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId' => $organizationId,
                ])
                ->orderBy([
                    'createdAt' => 'DESC',
                ])
                ->fetchAll(),
            false,
        );
    }

    public function findBatchAfter(?string $afterId, int $limit): array
    {
        $select = $this->select()->orderBy('uuid', 'ASC')->limit($limit);
        if (null !== $afterId) {
            $select->where('uuid', '>', $afterId);
        }

        return iterator_to_array($select->fetchAll(), false);
    }

    public function countByOrganization(string $organizationId): int
    {
        return $this->select()->where('organizationId', $organizationId)->count('*');
    }
}
