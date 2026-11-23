<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Resource;

use Packio\Identity\Api\Dto\UserSignInDto;

final readonly class UserSignInResource
{
    /**
     * @return array{id: string, method: string, method_label: string, occurred_at: string, ip: ?string, user_agent: ?string}
     */
    public static function fromDto(UserSignInDto $userSignInDto): array
    {
        return [
            'id'           => $userSignInDto->id,
            'method'       => $userSignInDto->method,
            'method_label' => $userSignInDto->methodLabel,
            'occurred_at'  => $userSignInDto->occurredAt,
            'ip'           => $userSignInDto->ip,
            'user_agent'   => $userSignInDto->userAgent,
        ];
    }
}
