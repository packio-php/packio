<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Http;

use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;

final readonly class RequestContextDto
{
    public function __construct(
        public ?UserDto $currentUser = null,
        public ?string $currentUserId = null,
        public ?string $currentOrganizationSlug = null,
        public ?OrganizationDto $currentOrganization = null,
        public ?OrganizationMemberDto $currentMembership = null,
        public ?ActorContext $organizationActorContext = null
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    public function withCurrentUser(?UserDto $userDto): self
    {
        return new self(
            currentUser: $userDto,
            currentUserId: $userDto?->id,
            currentOrganizationSlug: $this->currentOrganizationSlug,
            currentOrganization: $this->currentOrganization,
            currentMembership: $this->currentMembership,
            organizationActorContext: $this->organizationActorContext,
        );
    }

    public function withCurrentOrganization(?string $slug, ?OrganizationDto $organizationDto): self
    {
        return new self(
            currentUser: $this->currentUser,
            currentUserId: $this->currentUserId,
            currentOrganizationSlug: $slug,
            currentOrganization: $organizationDto,
            currentMembership: $this->currentMembership,
            organizationActorContext: $this->organizationActorContext,
        );
    }

    public function withCurrentMembership(?OrganizationMemberDto $organizationMemberDto): self
    {
        return new self(
            currentUser: $this->currentUser,
            currentUserId: $this->currentUserId,
            currentOrganizationSlug: $this->currentOrganizationSlug,
            currentOrganization: $this->currentOrganization,
            currentMembership: $organizationMemberDto,
            organizationActorContext: $this->organizationActorContext,
        );
    }

    public function withOrganizationActorContext(?ActorContext $actorContext): self
    {
        return new self(
            currentUser: $this->currentUser,
            currentUserId: $this->currentUserId,
            currentOrganizationSlug: $this->currentOrganizationSlug,
            currentOrganization: $this->currentOrganization,
            currentMembership: $this->currentMembership,
            organizationActorContext: $actorContext,
        );
    }
}
