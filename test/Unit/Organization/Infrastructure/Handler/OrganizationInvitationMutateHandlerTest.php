<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Handler\OrganizationInvitationDeleteHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInvitationRevokeHandler;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Organization\ActorContext;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

#[CoversClass(OrganizationInvitationRevokeHandler::class)]
#[CoversClass(OrganizationInvitationDeleteHandler::class)]
final class OrganizationInvitationMutateHandlerTest extends TestCase
{
    /**
     * @param class-string     $handlerClass
     * @param non-empty-string $method
     */
    #[Test]
    #[DataProvider('handlerProvider')]
    public function missingInvitationFlashInvitationNotFoundAndRedirectsToMembers(string $handlerClass, string $method): void
    {
        $facade = $this->createMock(OrganizationApiFacadeInterface::class);
        $facade->expects(self::once())->method($method)->willReturn(false);

        $testInertia   = new TestInertia();
        $serverRequest = $this->request($testInertia);

        /** @var RequestHandlerInterface $handler */
        $handler = new $handlerClass(
            $facade,
            new InertiaNotificationMessenger(),
        );

        $response = $handler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/organization/acme/members', $response->getHeaderLine('Location'));

        $expectedMessage = OrganizationInvitationRevokeHandler::class === $handlerClass
            ? 'Invitation not found.'
            : 'Only non-pending invitations can be deleted.';
        self::assertSame([
            'error' => $expectedMessage,
        ], $testInertia->flash);
    }

    /**
     * @param class-string     $handlerClass
     * @param non-empty-string $method
     */
    #[Test]
    #[DataProvider('handlerProvider')]
    public function successfulActionFlashesSuccessMessageAndRedirectsToMembers(string $handlerClass, string $method): void
    {
        $facade = $this->createMock(OrganizationApiFacadeInterface::class);
        $facade->expects(self::once())->method($method)->willReturn(true);

        $testInertia   = new TestInertia();
        $serverRequest = $this->request($testInertia);

        /** @var RequestHandlerInterface $handler */
        $handler = new $handlerClass(
            $facade,
            new InertiaNotificationMessenger(),
        );

        $response = $handler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/organization/acme/members', $response->getHeaderLine('Location'));

        $expectedMessage = OrganizationInvitationRevokeHandler::class === $handlerClass
            ? 'Invitation revoked.'
            : 'Invitation deleted.';
        self::assertSame([
            'success' => $expectedMessage,
        ], $testInertia->flash);
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function handlerProvider(): iterable
    {
        yield 'revoke' => [OrganizationInvitationRevokeHandler::class, 'revokeInvitationInContext'];

        yield 'delete' => [OrganizationInvitationDeleteHandler::class, 'deleteInvitationInContext'];
    }

    private function request(TestInertia $testInertia): ServerRequest
    {
        return new ServerRequest()
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'acme', 'Acme'))
            ->withAttribute(
                RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT,
                new ActorContext('org-1', 'user-1', 'owner'),
            )
            ->withAttribute('invitationId', '22222222-2222-4222-8222-222222222222')
        ;
    }
}
