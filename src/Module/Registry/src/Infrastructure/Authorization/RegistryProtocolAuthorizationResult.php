<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Authorization;

use Packio\Identity\Api\Dto\RegistryAccessTokenDto;
use Packio\Shared\Api\Dto\OrganizationDto;

final readonly class RegistryProtocolAuthorizationResult
{
    private function __construct(
        public RegistryProtocolAuthorizationStatus $status,
        public ?RegistryAccessTokenDto $registryActor = null,
        public ?OrganizationDto $resolvedOrganization = null,
        public ?int $retryAfter = null,
    ) {}

    public static function anonymousAllowed(): self
    {
        return new self(RegistryProtocolAuthorizationStatus::AnonymousAllowed);
    }

    public static function tokenAllowed(RegistryAccessTokenDto $registryAccessTokenDto, ?OrganizationDto $organizationDto = null): self
    {
        return new self(
            RegistryProtocolAuthorizationStatus::TokenAllowed,
            registryActor: $registryAccessTokenDto,
            resolvedOrganization: $organizationDto,
        );
    }

    public static function userBanned(): self
    {
        return new self(RegistryProtocolAuthorizationStatus::UserBanned);
    }

    public static function unauthorized(): self
    {
        return new self(RegistryProtocolAuthorizationStatus::Unauthorized);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(
            RegistryProtocolAuthorizationStatus::RateLimited,
            retryAfter: $retryAfter,
        );
    }

    public static function rateLimitPolicyMissing(): self
    {
        return new self(
            RegistryProtocolAuthorizationStatus::RateLimitPolicyMissing,
            retryAfter: 60,
        );
    }

    public static function permissionDenied(): self
    {
        return new self(RegistryProtocolAuthorizationStatus::PermissionDenied);
    }

    public static function organizationMismatch(): self
    {
        return new self(RegistryProtocolAuthorizationStatus::OrganizationMismatch);
    }

    public static function tokenOrganizationNotFound(): self
    {
        return new self(RegistryProtocolAuthorizationStatus::TokenOrganizationNotFound);
    }
}
