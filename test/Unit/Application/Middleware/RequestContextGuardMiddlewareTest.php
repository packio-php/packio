<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RequestContextDto;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Shared\Api\Dto\OrganizationDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RequireExistingOrganizationMiddleware::class)]
final class RequestContextGuardMiddlewareTest extends TestCase
{
    #[Test]
    public function requireExistingOrganizationMiddlewareRedirectsClassicRequestsToOrganizationsPage(): void
    {
        $requireExistingOrganizationMiddleware = new RequireExistingOrganizationMiddleware();
        $response                              = $requireExistingOrganizationMiddleware->process(new ServerRequest(), new GuardCapturingHandler());

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function requireExistingOrganizationMiddlewareReturnsJsonNotFoundForJsonRequests(): void
    {
        $requireExistingOrganizationMiddleware = new RequireExistingOrganizationMiddleware();
        $response                              = $requireExistingOrganizationMiddleware->process(
            new ServerRequest()->withHeader('Accept', 'application/json'),
            new GuardCapturingHandler(),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Organization not found.', (string) $response->getBody());
    }

    #[Test]
    public function requireExistingOrganizationMiddlewareReturnsJsonNotFoundForApiRequests(): void
    {
        $requireExistingOrganizationMiddleware = new RequireExistingOrganizationMiddleware();
        $response                              = $requireExistingOrganizationMiddleware->process(
            new ServerRequest([], [], '/api/v1/organizations/acme/packages', 'GET'),
            new GuardCapturingHandler(),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Organization not found.', (string) $response->getBody());
    }

    #[Test]
    public function requireExistingOrganizationMiddlewarePassesWhenOrganizationIsResolved(): void
    {
        $organizationDto = new OrganizationDto('org-1', 'acme', 'Acme');
        $serverRequest   = new ServerRequest()
            ->withAttribute(
                RequestContextAttributes::CONTEXT,
                RequestContextDto::empty()->withCurrentOrganization('acme', $organizationDto),
            )
        ;

        $requireExistingOrganizationMiddleware = new RequireExistingOrganizationMiddleware();
        $response                              = $requireExistingOrganizationMiddleware->process($serverRequest, new GuardCapturingHandler());

        self::assertSame(204, $response->getStatusCode());
    }
}

final class GuardCapturingHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): EmptyResponse
    {
        return new EmptyResponse();
    }
}
