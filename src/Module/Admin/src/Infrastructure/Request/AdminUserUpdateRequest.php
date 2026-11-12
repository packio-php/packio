<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Yiisoft\Validator\Rule\Count;
use Yiisoft\Validator\Rule\Each;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

use function array_filter;
use function array_intersect;
use function array_map;
use function array_unique;
use function array_values;
use function trim;

final readonly class AdminUserUpdateRequest
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[Required]
        #[Email]
        public string $email = '',
        #[Required]
        #[Length(min: 2, max: 100)]
        public string $displayName = '',
        #[Count(min: 1)]
        #[Each([new In(['user', 'admin'], strict: true)])]
        public array $roles = ['user'],
    ) {}

    /**
     * @return list<string>
     */
    public function normalizedRoles(): array
    {
        $roles = array_map(trim(...), $this->roles);

        $roles = array_values(array_unique(array_filter($roles, static fn (string $role): bool => '' !== $role)));
        $roles = array_values(array_intersect($roles, ['user', 'admin']));

        return [] === $roles ? ['user'] : $roles;
    }
}
