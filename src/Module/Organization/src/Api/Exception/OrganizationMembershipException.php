<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Exception;

use RuntimeException;

final class OrganizationMembershipException extends RuntimeException implements OrganizationExceptionInterface
{
    private const string INVALID_ROLE      = 'invalid_role';
    private const string LAST_OWNER_REMOVE = 'last_owner_remove';
    private const string LAST_OWNER_DEMOTE = 'last_owner_demote';
    private const string SELF_REMOVE       = 'self_remove';
    private const string SELF_ROLE_CHANGE  = 'self_role_change';

    private function __construct(private readonly string $reason)
    {
        parent::__construct($reason);
    }

    public static function invalidRole(): self
    {
        return new self(self::INVALID_ROLE);
    }

    public static function lastOwnerCannotBeRemoved(): self
    {
        return new self(self::LAST_OWNER_REMOVE);
    }

    public static function lastOwnerCannotBeDemoted(): self
    {
        return new self(self::LAST_OWNER_DEMOTE);
    }

    public static function selfRemove(): self
    {
        return new self(self::SELF_REMOVE);
    }

    public static function selfRoleChange(): self
    {
        return new self(self::SELF_ROLE_CHANGE);
    }

    public function userMessage(): string
    {
        return match ($this->reason) {
            self::INVALID_ROLE      => 'Invalid organization member role.',
            self::LAST_OWNER_REMOVE => 'The last owner cannot be removed from the organization.',
            self::LAST_OWNER_DEMOTE => 'The last owner cannot be demoted from the organization.',
            self::SELF_REMOVE       => 'You cannot remove yourself from the organization.',
            self::SELF_ROLE_CHANGE  => 'You cannot change your own organization role.',
            default                 => 'Unable to update organization membership.',
        };
    }
}
