<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Adapter;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Application\Port\UserDirectoryPort;

final readonly class IdentityUserDirectoryAdapter implements UserDirectoryPort
{
    public function __construct(private IdentityApiFacadeInterface $identityApiFacade) {}

    public function findByIds(array $ids): array
    {
        $users = [];
        foreach ($this->identityApiFacade->findAllUsersByIds($ids) as $userDto) {
            $users[$userDto->id] = [
                'displayName' => $userDto->displayName,
                'email'       => $userDto->email,
            ];
        }

        return $users;
    }
}
