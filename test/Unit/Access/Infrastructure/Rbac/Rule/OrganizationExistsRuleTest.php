<?php

declare(strict_types=1);

namespace PackioTest\Unit\Access\Infrastructure\Rbac\Rule;

use Packio\Access\Infrastructure\Rbac\Rule\OrganizationExistsRule;
use Packio\Shared\Api\Dto\OrganizationDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Rbac\Actor\Actor;

#[CoversClass(OrganizationExistsRule::class)]
final class OrganizationExistsRuleTest extends TestCase
{
    private OrganizationExistsRule $organizationExistsRule;

    protected function setUp(): void
    {
        $this->organizationExistsRule = new OrganizationExistsRule();
    }

    #[Test]
    public function itAllowsWhenOrganizationContextExists(): void
    {
        self::assertTrue($this->organizationExistsRule->allows($this->actor(), 'packages.view', [
            'organization' => new OrganizationDto('org-1', 'acme', 'Acme'),
        ]));
    }

    #[Test]
    public function itDeniesWhenOrganizationContextIsMissing(): void
    {
        self::assertFalse($this->organizationExistsRule->allows($this->actor(), 'packages.view', []));
    }

    private function actor(): Actor
    {
        return new Actor(['user']);
    }
}
