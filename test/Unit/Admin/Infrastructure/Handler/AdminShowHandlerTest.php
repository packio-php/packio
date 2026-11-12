<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationShowHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserShowHandler;
use Packio\Admin\Infrastructure\UserModeration\UserModerationService;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Source\Api\SourceApiFacadeInterface;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

#[CoversClass(AdminOrganizationShowHandler::class)]
#[CoversClass(AdminUserShowHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class AdminShowHandlerTest extends TestCase
{
    #[Test]
    public function organizationShowUsesTheNotificationFacadeForAMissingId(): void
    {
        $testInertia                  = new TestInertia();
        $adminOrganizationShowHandler = new AdminOrganizationShowHandler(
            $this->createMock(OrganizationApiFacadeInterface::class),
            $this->createMock(IdentityApiFacadeInterface::class),
            $this->createMock(SourceApiFacadeInterface::class),
            $this->createMock(PackageApiFacadeInterface::class),
            new InertiaNotificationMessenger(),
        );

        $response = $adminOrganizationShowHandler->handle(new ServerRequest()
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/organizations', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'Organization not found.',
        ], $testInertia->flash);
    }

    #[Test]
    public function userShowUsesTheNotificationFacadeForAMissingId(): void
    {
        $testInertia          = new TestInertia();
        $adminUserShowHandler = new AdminUserShowHandler(
            $this->createMock(IdentityApiFacadeInterface::class),
            new UserModerationService(
                $this->createMock(IdentityApiFacadeInterface::class),
                $this->createMock(AuditApiFacadeInterface::class),
                $this->createMock(LoggerInterface::class),
            ),
            new InertiaNotificationMessenger(),
        );

        $response = $adminUserShowHandler->handle(new ServerRequest()
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/users', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'User not found.',
        ], $testInertia->flash);
    }
}
