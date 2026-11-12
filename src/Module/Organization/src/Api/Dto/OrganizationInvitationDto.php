<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Dto;

final readonly class OrganizationInvitationDto
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $invitedByUserId,
        public string $token,
        public string $role,
        public string $status,
        public ?string $acceptedByUserId = null,
        public string $createdAt = '',
        public string $updatedAt = '',
        public string $expiresAt = '',
        public string $acceptedAt = '',
        public string $revokedAt = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'organization_id'     => $this->organizationId,
            'invited_by_user_id'  => $this->invitedByUserId,
            'token'               => $this->token,
            'role'                => $this->role,
            'status'              => $this->status,
            'accepted_by_user_id' => $this->acceptedByUserId,
            'created_at'          => $this->createdAt,
            'updated_at'          => $this->updatedAt,
            'expires_at'          => $this->expiresAt,
            'accepted_at'         => $this->acceptedAt,
            'revoked_at'          => $this->revokedAt,
        ];
    }
}
