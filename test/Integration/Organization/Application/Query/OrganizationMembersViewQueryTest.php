<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Application\Query;

use Cycle\Database\DatabaseManager;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Route;
use Mezzio\Router\RouterInterface;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use Packio\Organization\Infrastructure\Handler\OrganizationMembersHandler;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;

final class OrganizationMembersViewQueryTest extends IntegrationTestCase
{
    #[Test]
    public function membersEndpointQueryCount(): void
    {
        $this->getService(RouterInterface::class)->addRoute(new Route('/organization/invite/:token', $this->createStub(MiddlewareInterface::class), ['GET'], 'organization.invite.accept'));
        $organizations = $this->getService(OrganizationRepositoryInterface::class);
        $members       = $this->getService(OrganizationMemberRepositoryInterface::class);
        $invitations   = $this->getService(OrganizationInvitationRepositoryInterface::class);
        $users         = $this->getService(UserRepositoryInterface::class);
        $organization  = $organizations->save(Organization::register('read-model', 'Read model'));
        $id            = $organization->getIdentifier()->toString();
        $handler       = $this->getService(OrganizationMembersHandler::class);
        $database      = $this->getService('dbal');
        self::assertInstanceOf(DatabaseManager::class, $database);
        foreach ([0, 1, 100] as $size) {
            for ($i = 0; $i < $size; ++$i) {
                $user   = $users->save(new User("user-{$size}-{$i}@example.test", "User {$i}"));
                $userId = $user->getIdentifier()->toString();
                $members->save(OrganizationMember::join($id, $userId));
                $invitations->save(OrganizationInvitation::invite($id, $userId));
            }
            $total   = 100 === $size ? 101 : $size;
            $inertia = $this->createMock(InertiaInterface::class);
            $inertia->expects(self::once())->method('render')->with('Organization/Members', self::callback(static function(array $props) use ($total): bool {
                self::assertCount($total, $props['members']);
                self::assertCount($total, $props['invitations']);
                if ($total > 0) {
                    self::assertStringContainsString('/organization/invite/orgi_', $props['invitations'][0]['accept_url']);
                    self::assertArrayNotHasKey('token', $props['invitations'][0]);
                    self::assertStringStartsWith('User ', $props['invitations'][0]['invited_by']);
                    self::assertStringStartsWith('User ', $props['members'][0]['display_name']);
                }

                return true;
            }))->willReturn(new Response());
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::exactly(0 === $total ? 2 : 3))->method('info');
            $database->setLogger($logger);
            $handler->handle(new ServerRequest()->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto($id, 'read-model', 'Read model'))->withAttribute(RequestContextAttributes::CURRENT_MEMBERSHIP, new OrganizationMemberDto('owner-member', $id, 'owner-user', 'owner'))->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia));
            $database->setLogger(new NullLogger());
        }
    }
}
