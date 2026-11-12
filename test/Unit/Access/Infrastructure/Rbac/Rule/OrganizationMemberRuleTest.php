<?php

declare(strict_types=1);

namespace PackioTest\Unit\Access\Infrastructure\Rbac\Rule;

use Packio\Access\Infrastructure\Rbac\Rule\OrganizationMemberRule;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Rbac\Actor\Actor;

#[CoversClass(OrganizationMemberRule::class)]
final class OrganizationMemberRuleTest extends TestCase
{
    private OrganizationMemberRule $organizationMemberRule;

    protected function setUp(): void
    {
        $this->organizationMemberRule = new OrganizationMemberRule();
    }

    #[Test]
    public function itAllowsMatchingOrganizationMembership(): void
    {
        self::assertTrue($this->organizationMemberRule->allows($this->actor(), 'organizations.view', [
            'organization' => new OrganizationDto('org-1', 'acme', 'Acme'),
            'membership'   => new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'member'),
        ]));
    }

    #[Test]
    public function itDeniesMissingOrganization(): void
    {
        self::assertFalse($this->organizationMemberRule->allows($this->actor(), 'organizations.view', [
            'membership' => new OrganizationMemberDto('member-1', 'org-1', 'user-1', 'member'),
        ]));
    }

    #[Test]
    public function itDeniesMissingMembership(): void
    {
        self::assertFalse($this->organizationMemberRule->allows($this->actor(), 'organizations.view', [
            'organization' => new OrganizationDto('org-1', 'acme', 'Acme'),
        ]));
    }

    #[Test]
    public function itDeniesMismatchedOrganization(): void
    {
        self::assertFalse($this->organizationMemberRule->allows($this->actor(), 'organizations.view', [
            'organization' => new OrganizationDto('org-1', 'acme', 'Acme'),
            'membership'   => new OrganizationMemberDto('member-1', 'org-2', 'user-1', 'member'),
        ]));
    }

    #[Test]
    public function itDeniesEmptyUserId(): void
    {
        self::assertFalse($this->organizationMemberRule->allows($this->actor(), 'organizations.view', [
            'organization' => new OrganizationDto('org-1', 'acme', 'Acme'),
            'membership'   => new OrganizationMemberDto('member-1', 'org-1', '', 'member'),
        ]));
    }

    private function actor(): Actor
    {
        return new Actor(['user']);
    }
}
