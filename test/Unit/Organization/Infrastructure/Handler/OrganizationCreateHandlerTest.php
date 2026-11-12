<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\Exception\OrganizationSlugException;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Handler\OrganizationCreateHandler;
use Packio\Organization\Infrastructure\Request\OrganizationCreateRequest;
use Packio\Shared\Api\Dto\OrganizationDto;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

#[CoversClass(OrganizationCreateHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class OrganizationCreateHandlerTest extends TestCase
{
    private MockObject&OrganizationApiFacadeInterface $organizations;
    private InertiaNotificationMessenger $inertiaNotificationMessenger;
    private ValidationRedirectState $validationRedirectState;

    protected function setUp(): void
    {
        $this->organizations                 = $this->createMock(OrganizationApiFacadeInterface::class);
        $this->inertiaNotificationMessenger  = new InertiaNotificationMessenger();
        $this->validationRedirectState       = new ValidationRedirectState();
    }

    #[Test]
    public function createsOrganizationThroughFacadeWithCurrentUserAsOwner(): void
    {
        $serverRequest = $this->buildRequest('user-123', 'Acme Inc');

        $this->organizations->expects(self::once())
            ->method('registerOrganization')
            ->with('Acme Inc', 'user-123')
            ->willReturn(new OrganizationDto(
                id: 'org-123',
                slug: 'acme-inc',
                name: 'Acme Inc',
            ))
        ;

        $response = $this->handler()->handle($serverRequest);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/acme-inc/packages', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function redirectsBackWithNameErrorWhenSlugIsAlreadyTaken(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest('user-123', 'Acme Inc', $inMemorySession);

        $this->organizations->expects(self::once())
            ->method('registerOrganization')
            ->with('Acme Inc', 'user-123')
            ->willThrowException(OrganizationSlugException::alreadyTaken())
        ;

        $response = $this->handler()->handle($serverRequest);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
        self::assertSame([
            'name' => ['Organization slug is already taken.'],
        ], $this->validationRedirectState->consumeErrors($serverRequest));
        self::assertSame([
            'name' => 'Acme Inc',
        ], $this->validationRedirectState->consumeOld($serverRequest));
    }

    private function handler(): OrganizationCreateHandler
    {
        return new OrganizationCreateHandler(
            $this->organizations,
            $this->inertiaNotificationMessenger,
            $this->validationRedirectState,
        );
    }

    private function buildRequest(string $userId, string $name, ?InMemorySession $inMemorySession = null): ServerRequest
    {
        $inMemorySession ??= new InMemorySession();

        return new ServerRequest()
            ->withAttribute(OrganizationCreateRequest::class, new OrganizationCreateRequest($name))
            ->withAttribute(RequestContextAttributes::CURRENT_USER_ID, $userId)
            ->withAttribute('session', $inMemorySession)
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
        ;
    }
}
