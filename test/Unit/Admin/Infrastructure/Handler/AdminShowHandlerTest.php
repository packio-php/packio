<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationShowHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserShowHandler;
use Packio\Admin\Infrastructure\UserModeration\UserModerationService;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\Dto\UserOAuthAccountDto;
use Packio\Identity\Api\Dto\UserSignInDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;

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

    #[Test]
    public function userShowExposesSignInMethodsAndHistory(): void
    {
        $userId = '0197a0f0-0000-7000-8000-000000000001';
        $facade = $this->createStub(IdentityApiFacadeInterface::class);
        $facade->method('findUserById')->willReturn(new UserDto($userId, 'a@example.test', 'A', ['user'], '2026-11-01T10:00:00+00:00', hasPassword: true));
        $facade->method('oauthAccountsForUser')->willReturn([
            new UserOAuthAccountDto('acc-1', 'github', 'GitHub', '@octo', 'o@example.test', 'octo'),
        ]);
        $facade->method('recentSignIns')->willReturn([
            new UserSignInDto('s-1', 'github', 'GitHub', '2026-11-22T10:00:00+00:00', '203.0.113.7', 'Mozilla/5.0'),
        ]);

        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->expects(self::once())->method('render')->with(
            'Admin/UserDetails',
            self::callback(static function(array $props): bool {
                self::assertSame([
                    'has_password'   => true,
                    'oauth_accounts' => [[
                        'id'                => 'acc-1',
                        'provider'          => 'github',
                        'provider_label'    => 'GitHub',
                        'account_label'     => '@octo',
                        'provider_email'    => 'o@example.test',
                        'provider_username' => 'octo',
                    ]],
                ], $props['sign_in_methods']);
                self::assertSame([[
                    'id'           => 's-1',
                    'method'       => 'github',
                    'method_label' => 'GitHub',
                    'occurred_at'  => '2026-11-22T10:00:00+00:00',
                    'ip'           => '203.0.113.7',
                    'user_agent'   => 'Mozilla/5.0',
                ]], $props['sign_in_history']);

                return true;
            }),
        )->willReturn(new EmptyResponse());

        $handler = new AdminUserShowHandler(
            $facade,
            new UserModerationService($facade, $this->createStub(AuditApiFacadeInterface::class), $this->createStub(LoggerInterface::class)),
            new InertiaNotificationMessenger(),
        );

        $handler->handle(new ServerRequest()
            ->withAttribute('userId', $userId)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia));
    }
}
