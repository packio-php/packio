<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Entity;

use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class UserSignIn implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;

    public function __construct(
        private readonly string $userUuid = '',
        private readonly string $method = '',
        private readonly ?string $ip = null,
        private readonly ?string $userAgent = null,
    ) {}

    public function userUuid(): string
    {
        return $this->userUuid;
    }

    /** Either `password` or an OAuth provider value. */
    public function method(): string
    {
        return $this->method;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }
}
