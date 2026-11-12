<?php

declare(strict_types=1);

namespace Packio\Organization\Domain\Entity;

use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

use function trim;

class OrganizationMember implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    public function __construct(private string $organizationId = '', private string $userId = '', private string $role = 'member')
    {
        $this->organizationId = trim($organizationId);
        $this->userId         = trim($userId);
        $this->role           = 'owner' === $role ? 'owner' : 'member';
    }

    public static function join(string $organizationId, string $userId, string $role = 'member'): self
    {
        return new self(organizationId: $organizationId, userId: $userId, role: $role);
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function promoteToOwner(): void
    {
        $this->role = 'owner';
    }

    public function demoteToMember(): void
    {
        $this->role = 'member';
    }
}
