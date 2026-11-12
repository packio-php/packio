<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

use Packio\Identity\Domain\Entity\User;

final readonly class OAuthUserResolution
{
    public function __construct(public OAuthUserResolutionType $type, public ?User $user = null) {}
}
