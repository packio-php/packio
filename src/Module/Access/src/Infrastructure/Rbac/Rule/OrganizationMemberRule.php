<?php

declare(strict_types=1);

namespace Packio\Access\Infrastructure\Rbac\Rule;

use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Sirix\Mezzio\Rbac\Contract\ActorInterface;
use Sirix\Mezzio\Rbac\Contract\RuleInterface;

final readonly class OrganizationMemberRule implements RuleInterface
{
    public function allows(ActorInterface $actor, string $permission, array $context): bool
    {
        $organization = $context['organization'] ?? null;
        $membership   = $context['membership'] ?? null;

        return $organization instanceof OrganizationDto
            && $membership instanceof OrganizationMemberDto
            && $membership->organizationId === $organization->id
            && '' !== $membership->userId;
    }
}
