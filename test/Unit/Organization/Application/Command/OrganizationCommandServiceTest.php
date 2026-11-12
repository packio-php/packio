<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Application\Command;

use Common\Security\SodiumSecretEncryptor;
use Packio\Organization\Api\Exception\OrganizationNameException;
use Packio\Organization\Application\Command\OrganizationCommandService;
use Packio\Organization\Application\Config\ReservedOrganizationSlugs;
use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use Packio\Shared\Domain\Organization\ActorContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
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

    #[Test]
    public function existingOrganizationWithReservedNameAndSlugCanSaveSettings(): void
    {
        $organization                    = $this->organization('test', 'Demo');
        $organizationCommandService      = $this->serviceFor($organization);

        $updated = $organizationCommandService->updateOrganization($organization->getIdentifier()->toString(), 'Demo', 'test', true);
        self::assertTrue($updated->anonymousAccess());

        $updated = $organizationCommandService->updateOrganizationSettingsInContext(
            new ActorContext($organization->getIdentifier()->toString(), 'user-1', 'owner'),
            'demo',
            false,
        );
        self::assertFalse($updated->anonymousAccess());
    }

    #[Test]
    public function renamingToReservedNameIsRejected(): void
    {
        $organization                    = $this->organization('acme', 'Acme');
        $organizationCommandService      = $this->serviceFor($organization);

        $this->expectException(OrganizationNameException::class);
        $organizationCommandService->updateOrganization($organization->getIdentifier()->toString(), 'Mail', 'acme', false);
    }

    #[Test]
    public function renamingToReservedNameInContextIsRejected(): void
    {
        $organization                    = $this->organization('acme', 'Acme');
        $organizationCommandService      = $this->serviceFor($organization);

        $this->expectException(OrganizationNameException::class);
        $organizationCommandService->updateOrganizationSettingsInContext(
            new ActorContext($organization->getIdentifier()->toString(), 'user-1', 'owner'),
            'Mail',
            false,
        );
    }

    private function organization(string $slug, string $name): Organization
    {
        $organization = Organization::register($slug, $name);
        $organization->setIdentifier(Uuid::uuid7());

        return $organization;
    }

    private function serviceFor(Organization $organization): OrganizationCommandService
    {
        /** @var OrganizationRepositoryInterface&Stub $organizations */
        $organizations = $this->createStub(OrganizationRepositoryInterface::class);
        $organizations->method('findById')->willReturn($organization);
        $organizations->method('existsWithNameKey')->willReturn(false);
        $organizations->method('save')->willReturnArgument(0);

        return new OrganizationCommandService(
            $organizations,
            $this->createStub(OrganizationInvitationRepositoryInterface::class),
            $this->createStub(OrganizationMemberRepositoryInterface::class),
            new ReservedOrganizationSlugs(ReservedOrganizationSlugs::DEFAULT_SLUGS),
            new OrganizationInvitationTokens(new SodiumSecretEncryptor('test-key')),
        );
    }
}
