<?php

declare(strict_types=1);

namespace PackioTest\Unit\Access\Infrastructure\Rbac\Rule;

use Packio\Access\Infrastructure\Rbac\Rule\OrganizationOwnerRule;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Rbac\Actor\Actor;

#[CoversClass(OrganizationOwnerRule::class)]
#[CoversClass(OrganizationPolicy::class)]
final class OrganizationOwnerRuleTest extends TestCase
{
    private OrganizationOwnerRule $organizationOwnerRule;

    protected function setUp(): void
    {
        $this->organizationOwnerRule = new OrganizationOwnerRule(new OrganizationPolicy());
    }

    #[Test]
    public function itAllowsOwnerMembership(): void
    {
        self::assertTrue($this->organizationOwnerRule->allows($this->actor(), 'organizations.manage', [
            'organization' => new OrganizationDto('org-1', 'acme', 'Acme'),
            'membership'   => new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'owner'),
        ]));
    }

    #[Test]
    public function itDeniesNonOwnerMembership(): void
    {
        self::assertFalse($this->organizationOwnerRule->allows($this->actor(), 'organizations.manage', [
            'organization' => new OrganizationDto('org-1', 'acme', 'Acme'),
            'membership'   => new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'member'),
        ]));
    }

    #[Test]
    public function itDeniesMismatchedOrganization(): void
    {
        self::assertFalse($this->organizationOwnerRule->allows($this->actor(), 'organizations.manage', [
            'organization' => new OrganizationDto('org-1', 'acme', 'Acme'),
            'membership'   => new OrganizationMemberDto('member-1', 'org-2', 'user-1', 'owner'),
        ]));
    }

    #[Test]
    public function itDeniesMissingContext(): void
    {
        self::assertFalse($this->organizationOwnerRule->allows($this->actor(), 'organizations.manage', []));
    }

    private function actor(): Actor
    {
        return new Actor(['user']);
    }
}
