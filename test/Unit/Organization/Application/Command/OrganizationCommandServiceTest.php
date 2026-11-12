<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Application\Command;

use Common\Security\SodiumSecretEncryptor;
use Packio\Organization\Application\Command\OrganizationCommandService;
use Packio\Organization\Application\Config\ReservedOrganizationSlugs;
use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

final class OrganizationCommandServiceTest extends TestCase
{
    #[Test]
    public function ensureMembershipReturnsConcurrentlyInsertedRow(): void
    {
        $organizationMember = OrganizationMember::join('org-x', 'user-x');
        $members            = $this->createStub(OrganizationMemberRepositoryInterface::class);
        $members->method('findByOrganizationAndUser')->willReturnOnConsecutiveCalls(null, $organizationMember);
        $members->method('save')->willThrowException(new RepositoryPersistException('duplicate'));

        $organizationCommandService = new OrganizationCommandService(
            $this->createStub(OrganizationRepositoryInterface::class),
            $this->createStub(OrganizationInvitationRepositoryInterface::class),
            $members,
            new ReservedOrganizationSlugs([]),
            new OrganizationInvitationTokens(new SodiumSecretEncryptor('test-key')),
        );

        self::assertSame($organizationMember, $organizationCommandService->addMember('org-x', 'user-x'));
    }
}
