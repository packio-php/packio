<?php

declare(strict_types=1);

namespace Packio\Access\Infrastructure\Rbac\Rule;

use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Sirix\Mezzio\Rbac\Contract\ActorInterface;
use Sirix\Mezzio\Rbac\Contract\RuleInterface;

final readonly class OrganizationOwnerRule implements RuleInterface
{
    public function __construct(private OrganizationPolicy $organizationPolicy) {}

    public function allows(ActorInterface $actor, string $permission, array $context): bool
    {
        $organization = $context['organization'] ?? null;
        $membership   = $context['membership'] ?? null;

        return $this->organizationPolicy->canManage(
            $organization instanceof OrganizationDto ? $organization : null,
            $membership instanceof OrganizationMemberDto ? $membership : null,
        );
    }
}
