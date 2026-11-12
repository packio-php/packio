<?php

declare(strict_types=1);

namespace Packio\Shared\Api\Authentication;

use Packio\Shared\Api\Dto\UserDto;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;

/**
 * Public contract for authenticated actors that can expose the current user.
 */
interface CurrentUserActorInterface extends ActorInterface
{
    public function currentUser(): ?UserDto;
}
