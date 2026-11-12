<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Organization\Api\Dto\OrganizationInvitationDto;

final readonly class OrganizationInvitationResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(OrganizationInvitationDto $organizationInvitationDto, bool $includeToken = false): array
    {
        $payload = [
            'id'                  => $organizationInvitationDto->id,
            'organization_id'     => $organizationInvitationDto->organizationId,
            'invited_by_user_id'  => $organizationInvitationDto->invitedByUserId,
            'role'                => $organizationInvitationDto->role,
            'status'              => $organizationInvitationDto->status,
            'accepted_by_user_id' => $organizationInvitationDto->acceptedByUserId,
            'created_at'          => '' !== $organizationInvitationDto->createdAt ? $organizationInvitationDto->createdAt : null,
            'updated_at'          => '' !== $organizationInvitationDto->updatedAt ? $organizationInvitationDto->updatedAt : null,
            'expires_at'          => '' !== $organizationInvitationDto->expiresAt ? $organizationInvitationDto->expiresAt : null,
            'accepted_at'         => '' !== $organizationInvitationDto->acceptedAt ? $organizationInvitationDto->acceptedAt : null,
            'revoked_at'          => '' !== $organizationInvitationDto->revokedAt ? $organizationInvitationDto->revokedAt : null,
        ];

        if ($includeToken) {
            $payload['token'] = $organizationInvitationDto->token;
        }

        return $payload;
    }
}
