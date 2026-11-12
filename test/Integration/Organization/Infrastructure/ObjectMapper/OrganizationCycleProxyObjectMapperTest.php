<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\ObjectMapper;

use Cycle\ORM\EntityProxyInterface;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function bin2hex;
use function random_bytes;

final class OrganizationCycleProxyObjectMapperTest extends IntegrationTestCase
{
    #[Test]
    public function itMapsPersistedAndReloadedOrganizationEntitiesThroughDirectCycleProxies(): void
    {
        $suffix       = bin2hex(random_bytes(6));
        $organization = Organization::register('Organization ' . $suffix, 'Organization ' . $suffix, true);
        $organization->markDeleting();

        /** @var OrganizationRepositoryInterface $organizations */
        $organizations = $this->getService(OrganizationRepositoryInterface::class);
        $organizations->save($organization);

        $organizationMember = OrganizationMember::join($organization->getIdentifier()->toString(), 'member-' . $suffix, 'owner');

        /** @var OrganizationMemberRepositoryInterface $members */
        $members = $this->getService(OrganizationMemberRepositoryInterface::class);
        $members->save($organizationMember);

        $organizationInvitation = OrganizationInvitation::invite(
            $organization->getIdentifier()->toString(),
            'inviter-' . $suffix,
            'member',
        );
        $organizationInvitation->accept('accepted-' . $suffix);

        /** @var OrganizationInvitationRepositoryInterface $invitations */
        $invitations = $this->getService(OrganizationInvitationRepositoryInterface::class);
        $invitations->save($organizationInvitation);

        $organizationId = $organization->getIdentifier()->toString();
        $memberId       = $organizationMember->getIdentifier()->toString();
        $invitationId   = $organizationInvitation->getIdentifier()->toString();

        $this->rebuildContainer();

        /** @var OrganizationRepositoryInterface $organizations */
        $organizations = $this->getService(OrganizationRepositoryInterface::class);

        /** @var OrganizationMemberRepositoryInterface $members */
        $members = $this->getService(OrganizationMemberRepositoryInterface::class);

        /** @var OrganizationInvitationRepositoryInterface $invitations */
        $invitations = $this->getService(OrganizationInvitationRepositoryInterface::class);

        /** @var ObjectMapperInterface $objectMapper */
        $objectMapper = $this->getService(ObjectMapperInterface::class);

        $persistedOrganization = $organizations->findById($organizationId);
        $persistedMember       = $members->findById($memberId);
        $persistedInvitation   = $invitations->findById($invitationId);

        self::assertInstanceOf(Organization::class, $persistedOrganization);
        self::assertInstanceOf(OrganizationMember::class, $persistedMember);
        self::assertInstanceOf(OrganizationInvitation::class, $persistedInvitation);
        self::assertInstanceOf(EntityProxyInterface::class, $persistedOrganization);
        self::assertInstanceOf(EntityProxyInterface::class, $persistedMember);
        self::assertInstanceOf(EntityProxyInterface::class, $persistedInvitation);

        $organizationDto             = $objectMapper->map($persistedOrganization, OrganizationDto::class);
        $organizationMemberDto       = $objectMapper->map($persistedMember, OrganizationMemberDto::class);
        $organizationInvitationDto   = $objectMapper->map($persistedInvitation, OrganizationInvitationDto::class);

        self::assertSame($organizationId, $organizationDto->id);
        self::assertTrue($organizationDto->anonymousAccess);
        self::assertFalse($organizationDto->active);
        self::assertSame('deleting', $organizationDto->status);
        self::assertNotSame('', $organizationDto->createdAt);
        self::assertSame($memberId, $organizationMemberDto->id);
        self::assertSame($organizationId, $organizationMemberDto->organizationId);
        self::assertSame('owner', $organizationMemberDto->role);
        self::assertNotSame('', $organizationMemberDto->createdAt);
        self::assertSame($invitationId, $organizationInvitationDto->id);
        self::assertSame('accepted', $organizationInvitationDto->status);
        self::assertSame('accepted-' . $suffix, $organizationInvitationDto->acceptedByUserId);
        self::assertNotSame('', $organizationInvitationDto->createdAt);
        self::assertNotSame('', $organizationInvitationDto->expiresAt);
        self::assertNotSame('', $organizationInvitationDto->acceptedAt);
        self::assertSame('', $organizationInvitationDto->revokedAt);
    }
}
