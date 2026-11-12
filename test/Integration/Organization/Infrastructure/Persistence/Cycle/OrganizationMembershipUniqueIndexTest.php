<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\Persistence\Cycle;

use Packio\Organization\Application\Command\OrganizationCommandService;
use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

use function array_filter;
use function bin2hex;
use function count;
use function random_bytes;

final class OrganizationMembershipUniqueIndexTest extends IntegrationTestCase
{
    #[Test]
    public function duplicateMembershipIsRejectedByTheDatabase(): void
    {
        /** @var OrganizationMemberRepositoryInterface $members */
        $members = $this->getService(OrganizationMemberRepositoryInterface::class);
        $members->save(OrganizationMember::join('org-x', 'user-x'));

        $this->expectException(RepositoryPersistException::class);
        $members->save(OrganizationMember::join('org-x', 'user-x'));
    }

    #[Test]
    public function acceptingTwiceKeepsOneMembership(): void
    {
        /** @var OrganizationCommandService $service */
        $service                  = $this->getService(OrganizationCommandService::class);
        $organization             = $service->registerOrganization('accept-twice-' . bin2hex(random_bytes(6)));
        $id                       = $organization->getIdentifier()->toString();
        $organizationInvitation   = $service->createInvitation($id, 'owner-user');

        /** @var OrganizationInvitationTokens $tokens */
        $tokens = $this->getService(OrganizationInvitationTokens::class);
        $token  = $tokens->decrypt($organizationInvitation->token());

        $service->acceptInvitation($token, 'user-y');
        $service->acceptInvitation($token, 'user-y');

        /** @var OrganizationMemberRepositoryInterface $members */
        $members = $this->getService(OrganizationMemberRepositoryInterface::class);
        $rows    = array_filter(
            $members->findByOrganization($id),
            static fn (OrganizationMember $organizationMember): bool => 'user-y' === $organizationMember->userId(),
        );
        self::assertSame(1, count($rows));
    }
}
