<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Authentication;

use Packio\Shared\Api\Authentication\CurrentUserActorInterface;
use Packio\Shared\Api\Dto\UserDto;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function trim;

final readonly class UserActor implements CurrentUserActorInterface
{
    /** @var list<string> */
    private array $roles;

    /**
     * @param list<string> $roles
     */
    public function __construct(public string $userId, array $roles, private ?UserDto $userDto = null)
    {
        $this->roles = $this->normalizeRoles($roles);
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function currentUser(): ?UserDto
    {
        return $this->userDto;
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $normalized = array_map(
            trim(...),
            $roles,
        )
                |> (fn ($x) => array_filter($x, static fn (string $role): bool => '' !== $role))
                |> array_unique(...)
                |> array_values(...);

        return [] !== $normalized ? $normalized : ['user'];
    }
}
