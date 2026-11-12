<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Shared\Api\Dto\UserDto;

final readonly class UserResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(UserDto $userDto): array
    {
        return [
            'id'                => $userDto->id,
            'email'             => $userDto->email,
            'display_name'      => $userDto->displayName,
            'roles'             => $userDto->roles,
            'created_at'        => $userDto->createdAt,
            'updated_at'        => '' !== $userDto->updatedAt ? $userDto->updatedAt : null,
            'has_password'      => $userDto->hasPassword,
            'is_email_verified' => $userDto->isEmailVerified,
            'is_banned'         => $userDto->isBanned,
            'banned_at'         => $userDto->bannedAt,
            'ban_reason'        => $userDto->banReason,
        ];
    }
}
