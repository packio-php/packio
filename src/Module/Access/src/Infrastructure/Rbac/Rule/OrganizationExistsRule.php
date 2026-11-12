<?php

declare(strict_types=1);

namespace Packio\Access\Infrastructure\Rbac\Rule;

use Packio\Shared\Api\Dto\OrganizationDto;
use Sirix\Mezzio\Rbac\Contract\ActorInterface;
use Sirix\Mezzio\Rbac\Contract\RuleInterface;

final readonly class OrganizationExistsRule implements RuleInterface
{
    public function allows(ActorInterface $actor, string $permission, array $context): bool
    {
        return ($context['organization'] ?? null) instanceof OrganizationDto;
    }
}
