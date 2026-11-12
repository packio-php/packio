<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Http;

use Laminas\Diactoros\ServerRequest;
use LogicException;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RequestContextDto;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestContextAttributes::class)]
final class RequestContextAttributesTest extends TestCase
{
    #[Test]
    public function contextReturnsResolvedRequestContext(): void
    {
        $userDto           = new UserDto('user-1', 'user@example.com', 'User', ['user'], '2026-01-01T00:00:00+00:00');
        $requestContextDto = RequestContextDto::empty()->withCurrentUser($userDto);
        $serverRequest     = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CONTEXT, $requestContextDto)
        ;

        self::assertSame($requestContextDto, RequestContextAttributes::context($serverRequest));
    }

    #[Test]
    public function contextReturnsEmptyContextWhenMissingOrMalformed(): void
    {
        self::assertEquals(RequestContextDto::empty(), RequestContextAttributes::context(new ServerRequest()));

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CONTEXT, 'not-context')
        ;

        self::assertEquals(RequestContextDto::empty(), RequestContextAttributes::context($serverRequest));
    }

    #[Test]
    public function requireCurrentOrganizationReturnsResolvedOrganization(): void
    {
        $organizationDto = new OrganizationDto('org-1', 'acme', 'Acme');
        $serverRequest   = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
        ;

        self::assertSame($organizationDto, RequestContextAttributes::requireCurrentOrganization($serverRequest));
    }

    #[Test]
    public function requireCurrentOrganizationThrowsWhenOrganizationIsMissing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Current organization is not resolved.');

        RequestContextAttributes::requireCurrentOrganization(new ServerRequest());
    }

    #[Test]
    public function requireCurrentOrganizationActorContextReturnsResolvedActorContext(): void
    {
        $actorContext  = new ActorContext('org-1', 'user-1', 'owner');
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
        ;

        self::assertSame($actorContext, RequestContextAttributes::requireCurrentOrganizationActorContext($serverRequest));
    }

    #[Test]
    public function requireCurrentOrganizationActorContextThrowsWhenMissing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Organization actor context is not resolved.');

        RequestContextAttributes::requireCurrentOrganizationActorContext(new ServerRequest());
    }

    #[Test]
    public function requireCurrentOrganizationActorContextThrowsWhenMalformed(): void
    {
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, 'not-context')
        ;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Organization actor context is not resolved.');

        RequestContextAttributes::requireCurrentOrganizationActorContext($serverRequest);
    }

    #[Test]
    public function currentOrganizationReturnsResolvedOrganization(): void
    {
        $organizationDto = new OrganizationDto('org-1', 'acme', 'Acme');
        $serverRequest   = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
        ;

        self::assertSame($organizationDto, RequestContextAttributes::currentOrganization($serverRequest));
    }

    #[Test]
    public function currentOrganizationReturnsNullWhenMissingOrMalformed(): void
    {
        self::assertNull(RequestContextAttributes::currentOrganization(new ServerRequest()));

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, 'not-organization')
        ;

        self::assertNull(RequestContextAttributes::currentOrganization($serverRequest));
    }

    #[Test]
    public function currentOrganizationActorContextReturnsResolvedActorContext(): void
    {
        $actorContext  = new ActorContext('org-1', 'user-1', 'owner');
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
        ;

        self::assertSame($actorContext, RequestContextAttributes::currentOrganizationActorContext($serverRequest));
    }

    #[Test]
    public function currentOrganizationActorContextReturnsNullWhenMissingOrMalformed(): void
    {
        self::assertNull(RequestContextAttributes::currentOrganizationActorContext(new ServerRequest()));

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, 'not-context')
        ;

        self::assertNull(RequestContextAttributes::currentOrganizationActorContext($serverRequest));
    }

    #[Test]
    public function currentUserReturnsResolvedUser(): void
    {
        $userDto       = new UserDto('user-1', 'user@example.com', 'User', ['user'], '2026-01-01T00:00:00+00:00');
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto)
        ;

        self::assertSame($userDto, RequestContextAttributes::currentUser($serverRequest));
    }

    #[Test]
    public function currentUserReturnsNullWhenMissingOrMalformed(): void
    {
        self::assertNull(RequestContextAttributes::currentUser(new ServerRequest()));

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_USER, 'not-user')
        ;

        self::assertNull(RequestContextAttributes::currentUser($serverRequest));
    }

    #[Test]
    public function currentMembershipReturnsResolvedMembership(): void
    {
        $organizationMemberDto = new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'owner');
        $serverRequest         = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_MEMBERSHIP, $organizationMemberDto)
        ;

        self::assertSame($organizationMemberDto, RequestContextAttributes::currentMembership($serverRequest));
    }

    #[Test]
    public function currentMembershipReturnsNullWhenMissingOrMalformed(): void
    {
        self::assertNull(RequestContextAttributes::currentMembership(new ServerRequest()));

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_MEMBERSHIP, 'not-membership')
        ;

        self::assertNull(RequestContextAttributes::currentMembership($serverRequest));
    }

    #[Test]
    public function currentUserIdReturnsResolvedUserId(): void
    {
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_USER_ID, 'user-1')
        ;

        self::assertSame('user-1', RequestContextAttributes::currentUserId($serverRequest));
    }

    #[Test]
    public function currentUserIdReturnsNullWhenMissingEmptyOrMalformed(): void
    {
        self::assertNull(RequestContextAttributes::currentUserId(new ServerRequest()));

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_USER_ID, '')
        ;
        self::assertNull(RequestContextAttributes::currentUserId($serverRequest));

        $malformedRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_USER_ID, 42)
        ;
        self::assertNull(RequestContextAttributes::currentUserId($malformedRequest));
    }

    #[Test]
    public function currentOrganizationSlugReturnsResolvedSlug(): void
    {
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION_SLUG, 'acme')
        ;

        self::assertSame('acme', RequestContextAttributes::currentOrganizationSlug($serverRequest));
    }

    #[Test]
    public function currentOrganizationSlugReturnsNullWhenMissingEmptyOrMalformed(): void
    {
        self::assertNull(RequestContextAttributes::currentOrganizationSlug(new ServerRequest()));

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION_SLUG, '')
        ;
        self::assertNull(RequestContextAttributes::currentOrganizationSlug($serverRequest));

        $malformedRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION_SLUG, 42)
        ;
        self::assertNull(RequestContextAttributes::currentOrganizationSlug($malformedRequest));
    }

    #[Test]
    public function withCurrentUserWritesDtoAndCompatibilityAttributesAtomically(): void
    {
        $userDto       = new UserDto('user-1', 'user@example.com', 'User', ['user'], '2026-01-01T00:00:00+00:00');
        $serverRequest = RequestContextAttributes::withCurrentUser(new ServerRequest(), $userDto);

        self::assertSame($userDto, RequestContextAttributes::currentUser($serverRequest));
        self::assertSame('user-1', RequestContextAttributes::currentUserId($serverRequest));
        self::assertSame($userDto, RequestContextAttributes::context($serverRequest)->currentUser);
        self::assertSame('user-1', RequestContextAttributes::context($serverRequest)->currentUserId);
    }

    #[Test]
    public function withCurrentUserNullClearsUserAndKeepsOrganizationContext(): void
    {
        $organizationDto = new OrganizationDto('org-1', 'acme', 'Acme');
        $userDto         = new UserDto('user-1', 'user@example.com', 'User', ['user'], '2026-01-01T00:00:00+00:00');
        $serverRequest   = RequestContextAttributes::withOrganizationContext(new ServerRequest(), 'acme', $organizationDto);
        $serverRequest   = RequestContextAttributes::withCurrentUser($serverRequest, $userDto);
        $serverRequest   = RequestContextAttributes::withCurrentUser($serverRequest, null);

        self::assertNull(RequestContextAttributes::currentUser($serverRequest));
        self::assertNull(RequestContextAttributes::currentUserId($serverRequest));
        self::assertNull(RequestContextAttributes::context($serverRequest)->currentUser);
        self::assertNull(RequestContextAttributes::context($serverRequest)->currentUserId);
        self::assertSame($organizationDto, RequestContextAttributes::currentOrganization($serverRequest));
        self::assertSame('acme', RequestContextAttributes::context($serverRequest)->currentOrganizationSlug);
    }

    #[Test]
    public function withOrganizationContextWritesAllOrganizationAttributesAtomically(): void
    {
        $organizationDto         = new OrganizationDto('org-1', 'acme', 'Acme');
        $organizationMemberDto   = new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'owner');
        $actorContext            = new ActorContext('org-1', 'user-1', 'owner');

        $serverRequest = RequestContextAttributes::withOrganizationContext(
            new ServerRequest(),
            'acme',
            $organizationDto,
            $organizationMemberDto,
            $actorContext,
        );

        self::assertSame('acme', RequestContextAttributes::currentOrganizationSlug($serverRequest));
        self::assertSame($organizationDto, RequestContextAttributes::currentOrganization($serverRequest));
        self::assertSame($organizationMemberDto, RequestContextAttributes::currentMembership($serverRequest));
        self::assertSame($actorContext, RequestContextAttributes::currentOrganizationActorContext($serverRequest));

        $requestContextDto = RequestContextAttributes::context($serverRequest);
        self::assertSame($organizationDto, $requestContextDto->currentOrganization);
        self::assertSame($organizationMemberDto, $requestContextDto->currentMembership);
        self::assertSame($actorContext, $requestContextDto->organizationActorContext);
    }

    #[Test]
    public function withOrganizationContextWithoutMembershipPreservesExistingMembershipAndActorContextForSameOrganization(): void
    {
        $organizationDto         = new OrganizationDto('org-1', 'acme', 'Acme');
        $organizationMemberDto   = new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'owner');
        $actorContext            = new ActorContext('org-1', 'user-1', 'owner');

        $serverRequest = RequestContextAttributes::withOrganizationContext(
            new ServerRequest(),
            'acme',
            $organizationDto,
            $organizationMemberDto,
            $actorContext,
        );
        $replacedRequest = RequestContextAttributes::withOrganizationContext($serverRequest, 'acme', $organizationDto);

        self::assertSame($organizationMemberDto, RequestContextAttributes::currentMembership($replacedRequest));
        self::assertSame($actorContext, RequestContextAttributes::currentOrganizationActorContext($replacedRequest));
        self::assertSame($organizationMemberDto, RequestContextAttributes::context($replacedRequest)->currentMembership);
        self::assertSame($actorContext, RequestContextAttributes::context($replacedRequest)->organizationActorContext);
    }

    #[Test]
    public function withOrganizationContextSwitchingOrganizationsDropsForeignMembershipAndActorContext(): void
    {
        $organizationDto         = new OrganizationDto('org-1', 'acme', 'Acme');
        $organizationMemberDto   = new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'owner');
        $actorContext            = new ActorContext('org-1', 'user-1', 'owner');

        $serverRequest = RequestContextAttributes::withOrganizationContext(
            new ServerRequest(),
            'acme',
            $organizationDto,
            $organizationMemberDto,
            $actorContext,
        );
        $switchedRequest = RequestContextAttributes::withOrganizationContext($serverRequest, 'other', new OrganizationDto('org-2', 'other', 'Other'));

        self::assertNull(RequestContextAttributes::currentMembership($switchedRequest));
        self::assertNull(RequestContextAttributes::currentOrganizationActorContext($switchedRequest));
        self::assertNull(RequestContextAttributes::context($switchedRequest)->currentMembership);
        self::assertNull(RequestContextAttributes::context($switchedRequest)->organizationActorContext);
        self::assertSame('other', RequestContextAttributes::currentOrganizationSlug($switchedRequest));
    }

    #[Test]
    public function withOrganizationContextNullsClearOrganizationState(): void
    {
        $serverRequest = RequestContextAttributes::withOrganizationContext(new ServerRequest(), null, null);

        self::assertNull(RequestContextAttributes::currentOrganizationSlug($serverRequest));
        self::assertNull(RequestContextAttributes::currentOrganization($serverRequest));
        self::assertNull(RequestContextAttributes::currentMembership($serverRequest));
        self::assertNull(RequestContextAttributes::currentOrganizationActorContext($serverRequest));
        self::assertEquals(RequestContextDto::empty(), RequestContextAttributes::context($serverRequest));
    }

    #[Test]
    public function withCurrentMembershipWritesDtoAndCompatibilityAttribute(): void
    {
        $organizationMemberDto   = new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'owner');
        $serverRequest           = RequestContextAttributes::withCurrentMembership(new ServerRequest(), $organizationMemberDto);

        self::assertSame($organizationMemberDto, RequestContextAttributes::currentMembership($serverRequest));
        self::assertSame($organizationMemberDto, RequestContextAttributes::context($serverRequest)->currentMembership);
    }

    #[Test]
    public function withCurrentMembershipNullClearsMembershipAndKeepsOtherFields(): void
    {
        $organizationDto         = new OrganizationDto('org-1', 'acme', 'Acme');
        $organizationMemberDto   = new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'owner');

        $serverRequest = RequestContextAttributes::withOrganizationContext(new ServerRequest(), 'acme', $organizationDto);
        $serverRequest = RequestContextAttributes::withCurrentMembership($serverRequest, $organizationMemberDto);
        $serverRequest = RequestContextAttributes::withCurrentMembership($serverRequest, null);

        self::assertNull(RequestContextAttributes::currentMembership($serverRequest));
        self::assertNull(RequestContextAttributes::context($serverRequest)->currentMembership);
        self::assertSame($organizationDto, RequestContextAttributes::currentOrganization($serverRequest));
    }

    #[Test]
    public function withOrganizationActorContextWritesDtoAndCompatibilityAttribute(): void
    {
        $actorContext  = new ActorContext('org-1', 'user-1', 'owner');
        $serverRequest = RequestContextAttributes::withOrganizationActorContext(new ServerRequest(), $actorContext);

        self::assertSame($actorContext, RequestContextAttributes::currentOrganizationActorContext($serverRequest));
        self::assertSame($actorContext, RequestContextAttributes::context($serverRequest)->organizationActorContext);
    }

    #[Test]
    public function withOrganizationActorContextNullClearsActorContextAndKeepsOtherFields(): void
    {
        $organizationDto = new OrganizationDto('org-1', 'acme', 'Acme');
        $actorContext    = new ActorContext('org-1', 'user-1', 'owner');

        $serverRequest = RequestContextAttributes::withOrganizationContext(new ServerRequest(), 'acme', $organizationDto);
        $serverRequest = RequestContextAttributes::withOrganizationActorContext($serverRequest, $actorContext);
        $serverRequest = RequestContextAttributes::withOrganizationActorContext($serverRequest, null);

        self::assertNull(RequestContextAttributes::currentOrganizationActorContext($serverRequest));
        self::assertNull(RequestContextAttributes::context($serverRequest)->organizationActorContext);
        self::assertSame($organizationDto, RequestContextAttributes::currentOrganization($serverRequest));
    }
}
