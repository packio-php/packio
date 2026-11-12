<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Http;

use LogicException;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Psr\Http\Message\ServerRequestInterface;

use function func_num_args;
use function is_string;

final class RequestContextAttributes
{
    public const string CONTEXT                    = 'request_context';
    public const string CURRENT_USER               = 'current_user';
    public const string CURRENT_USER_ID            = 'current_user_id';
    public const string CURRENT_ORGANIZATION_SLUG  = 'current_organization_slug';
    public const string CURRENT_ORGANIZATION       = 'current_organization';
    public const string CURRENT_MEMBERSHIP         = 'current_membership';
    public const string ORGANIZATION_ACTOR_CONTEXT = 'organization_actor_context';
    public const string REGISTRY_ACTOR             = 'registry_actor';

    public static function context(ServerRequestInterface $serverRequest): RequestContextDto
    {
        $context = $serverRequest->getAttribute(self::CONTEXT);

        return $context instanceof RequestContextDto ? $context : RequestContextDto::empty();
    }

    public static function requireCurrentOrganization(ServerRequestInterface $serverRequest): OrganizationDto
    {
        $organization = self::currentOrganization($serverRequest);

        if (! $organization instanceof OrganizationDto) {
            throw new LogicException('Current organization is not resolved.');
        }

        return $organization;
    }

    public static function currentOrganization(ServerRequestInterface $serverRequest): ?OrganizationDto
    {
        $organization = $serverRequest->getAttribute(self::CURRENT_ORGANIZATION);

        return $organization instanceof OrganizationDto ? $organization : null;
    }

    public static function requireCurrentOrganizationActorContext(ServerRequestInterface $serverRequest): ActorContext
    {
        $actorContext = self::currentOrganizationActorContext($serverRequest);

        if (! $actorContext instanceof ActorContext) {
            throw new LogicException('Organization actor context is not resolved.');
        }

        return $actorContext;
    }

    public static function currentOrganizationActorContext(ServerRequestInterface $serverRequest): ?ActorContext
    {
        $actorContext = $serverRequest->getAttribute(self::ORGANIZATION_ACTOR_CONTEXT);

        return $actorContext instanceof ActorContext ? $actorContext : null;
    }

    public static function currentUser(ServerRequestInterface $serverRequest): ?UserDto
    {
        $user = $serverRequest->getAttribute(self::CURRENT_USER);

        return $user instanceof UserDto ? $user : null;
    }

    public static function currentMembership(ServerRequestInterface $serverRequest): ?OrganizationMemberDto
    {
        $membership = $serverRequest->getAttribute(self::CURRENT_MEMBERSHIP);

        return $membership instanceof OrganizationMemberDto ? $membership : null;
    }

    public static function currentUserId(ServerRequestInterface $serverRequest): ?string
    {
        $userId = $serverRequest->getAttribute(self::CURRENT_USER_ID);

        return is_string($userId) && '' !== $userId ? $userId : null;
    }

    public static function currentOrganizationSlug(ServerRequestInterface $serverRequest): ?string
    {
        $organizationSlug = $serverRequest->getAttribute(self::CURRENT_ORGANIZATION_SLUG);

        return is_string($organizationSlug) && '' !== $organizationSlug ? $organizationSlug : null;
    }

    /**
     * Atomically writes the current user into both the context DTO and its
     * compatibility request attributes, so consumers cannot observe diverging
     * values between the two representations.
     */
    public static function withCurrentUser(ServerRequestInterface $serverRequest, ?UserDto $userDto): ServerRequestInterface
    {
        $requestContextDto = self::context($serverRequest)->withCurrentUser($userDto);

        return $serverRequest
            ->withAttribute(self::CURRENT_USER, $userDto)
            ->withAttribute(self::CURRENT_USER_ID, $userDto?->id)
            ->withAttribute(self::CONTEXT, $requestContextDto)
        ;
    }

    /**
     * Atomically writes the current organization, membership and actor context
     * into both the context DTO and its compatibility request attributes.
     */
    public static function withOrganizationContext(
        ServerRequestInterface $serverRequest,
        ?string $organizationSlug,
        ?OrganizationDto $organizationDto,
        ?OrganizationMemberDto $organizationMemberDto = null,
        ?ActorContext $actorContext = null,
    ): ServerRequestInterface {
        $requestContextDto = self::context($serverRequest);
        $context           = $requestContextDto->withCurrentOrganization($organizationSlug, $organizationDto);

        if (3 === func_num_args()) {
            // When membership and actor context are not provided explicitly, carry
            // the previous ones over only while the same organization is still
            // resolved. Switching organizations must never inherit another
            // organization's membership or actor context.
            if (self::sameOrganization($requestContextDto->currentOrganization, $organizationDto)) {
                $organizationMemberDto ??= $requestContextDto->currentMembership;
                $actorContext ??= $requestContextDto->organizationActorContext;
            }
        }

        $context = $context->withCurrentMembership($organizationMemberDto)->withOrganizationActorContext($actorContext);

        return $serverRequest
            ->withAttribute(self::CURRENT_ORGANIZATION_SLUG, $organizationSlug)
            ->withAttribute(self::CURRENT_ORGANIZATION, $organizationDto)
            ->withAttribute(self::CURRENT_MEMBERSHIP, $organizationMemberDto)
            ->withAttribute(self::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(self::CONTEXT, $context)
        ;
    }

    public static function withCurrentMembership(
        ServerRequestInterface $serverRequest,
        ?OrganizationMemberDto $organizationMemberDto
    ): ServerRequestInterface {
        $requestContextDto = self::context($serverRequest)->withCurrentMembership($organizationMemberDto);

        return $serverRequest
            ->withAttribute(self::CURRENT_MEMBERSHIP, $organizationMemberDto)
            ->withAttribute(self::CONTEXT, $requestContextDto)
        ;
    }

    public static function withOrganizationActorContext(
        ServerRequestInterface $serverRequest,
        ?ActorContext $actorContext
    ): ServerRequestInterface {
        $requestContextDto = self::context($serverRequest)->withOrganizationActorContext($actorContext);

        return $serverRequest
            ->withAttribute(self::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(self::CONTEXT, $requestContextDto)
        ;
    }

    private static function sameOrganization(
        ?OrganizationDto $resolvedOrganization,
        ?OrganizationDto $organizationDto
    ): bool {
        return $organizationDto instanceof OrganizationDto
            && $resolvedOrganization instanceof OrganizationDto
            && $resolvedOrganization->id === $organizationDto->id;
    }
}
