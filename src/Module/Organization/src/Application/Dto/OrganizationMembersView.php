<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Dto;

final readonly class OrganizationMembersView
{
    /**
     * @param list<array{id: string, display_name: string, email: string, role: string, is_current_user: bool, can_remove: bool, can_update_role: bool, can_promote: bool, can_demote: bool, created_at: string}> $members
     * @param list<array{id: string, role: string, status: string, invited_by: string, token: string, expires_at: string}>                                                                                        $invitations
     */
    public function __construct(public array $members, public array $invitations, public int $ownersCount, public bool $canManageMembers) {}
}
