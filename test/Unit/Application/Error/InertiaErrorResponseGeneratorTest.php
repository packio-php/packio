<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Error;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Mezzio\Template\TemplateRendererInterface;
use Packio\Application\Error\AuthorizationExceptionResponseFactory;
use Packio\Application\Error\InertiaErrorResponseGenerator;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Service\InitialThemeResolver;
use Packio\Shared\Api\Dto\OrganizationDto;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\InertiaPsr15\Service\InertiaVersionProviderInterface;
use Sirix\Mezzio\Rbac\Exception\AuthorizationException;

use function json_decode;

#[CoversClass(AuthorizationExceptionResponseFactory::class)]
#[CoversClass(InertiaErrorResponseGenerator::class)]
#[AllowMockObjectsWithoutExpectations]
final class InertiaErrorResponseGeneratorTest extends TestCase
{
    #[Test]
    public function itReturnsJsonForAuthorizationExceptionWhenJsonIsExpected(): void
    {
        $response = ($this->generator())(
            new AuthorizationException('admin.access', 'Forbidden', [
                'X-Rbac' => 'denied',
            ]),
            new ServerRequest([], [], '/api/admin', 'GET', 'php://memory', [
                'Accept' => 'application/json',
            ]),
            new Response(),
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('denied', $response->getHeaderLine('X-Rbac'));
        self::assertSame([
            'error' => 'Forbidden',
        ], json_decode((string) $response->getBody(), true));
    }

    #[Test]
    public function itReturnsJsonForApiExceptionsEvenWhenHtmlIsAccepted(): void
    {
        $response = ($this->generator())(
            new RuntimeException('Sensitive backend detail'),
            new ServerRequest([], [], '/api/v1/broken', 'GET', 'php://memory', [
                'Accept' => 'text/html',
            ]),
            new Response(),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame([
            'error' => [
                'code'    => 'internal_server_error',
                'message' => 'The request could not be completed.',
            ],
        ], json_decode((string) $response->getBody(), true));
    }

    #[Test]
    public function itRedirectsOrganizationManageDenialToCurrentOrganizationWithFlash(): void
    {
        $inMemorySession     = new InMemorySession();
        $organizationDto     = new OrganizationDto('org-1', 'acme', 'Acme');
        $testInertia         = new TestInertia();
        $serverRequest       = new ServerRequest([], [], '/organization/acme/settings', 'POST')
            ->withHeader('X-Inertia', 'true')
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
        ;

        $response = ($this->generator())(
            new AuthorizationException('organizations.manage'),
            $serverRequest,
            new Response(),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/acme', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'Owner access is required.',
        ], $testInertia->flash);
    }

    #[Test]
    public function itRedirectsTokenViewDenialToOrganizationsWithFlash(): void
    {
        $inMemorySession     = new InMemorySession();
        $testInertia         = new TestInertia();
        $serverRequest       = new ServerRequest([], [], '/organization/acme/tokens', 'GET')
            ->withHeader('X-Inertia', 'true')
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
        ;

        $response = ($this->generator())(
            new AuthorizationException('tokens.view'),
            $serverRequest,
            new Response(),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'Access denied.',
        ], $testInertia->flash);
    }

    #[Test]
    public function itRedirectsSourceViewDenialToOrganizationsWithFlash(): void
    {
        $inMemorySession     = new InMemorySession();
        $testInertia         = new TestInertia();
        $serverRequest       = new ServerRequest([], [], '/organization/acme/sources', 'GET')
            ->withHeader('X-Inertia', 'true')
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
        ;

        $response = ($this->generator())(
            new AuthorizationException('sources.view'),
            $serverRequest,
            new Response(),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'Access denied.',
        ], $testInertia->flash);
    }

    #[Test]
    public function itRedirectsOrganizationInvitationManageDenialToCurrentOrganizationWithFlash(): void
    {
        $inMemorySession     = new InMemorySession();
        $organizationDto     = new OrganizationDto('org-1', 'acme', 'Acme');
        $testInertia         = new TestInertia();
        $serverRequest       = new ServerRequest([], [], '/organization/acme/invitations', 'POST')
            ->withHeader('X-Inertia', 'true')
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
        ;

        $response = ($this->generator())(
            new AuthorizationException('organizations.invitations.manage'),
            $serverRequest,
            new Response(),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/acme', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'Owner access is required.',
        ], $testInertia->flash);
    }

    #[Test]
    public function itRedirectsPackageManageDenialToCurrentOrganizationWithFlash(): void
    {
        $inMemorySession     = new InMemorySession();
        $organizationDto     = new OrganizationDto('org-1', 'acme', 'Acme');
        $testInertia         = new TestInertia();
        $serverRequest       = new ServerRequest([], [], '/organization/acme/packages/acme/pkg/releases/1.0.0/activity', 'POST')
            ->withHeader('X-Inertia', 'true')
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
        ;

        $response = ($this->generator())(
            new AuthorizationException('packages.manage'),
            $serverRequest,
            new Response(),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/acme', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'Owner access is required.',
        ], $testInertia->flash);
    }

    #[Test]
    public function itRedirectsWebhookManageDenialToCurrentOrganizationWithFlash(): void
    {
        $inMemorySession     = new InMemorySession();
        $organizationDto     = new OrganizationDto('org-1', 'acme', 'Acme');
        $testInertia         = new TestInertia();
        $serverRequest       = new ServerRequest([], [], '/organization/acme/sources/webhooks/source-1/delivery-1/reprocess', 'POST')
            ->withHeader('X-Inertia', 'true')
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
        ;

        $response = ($this->generator())(
            new AuthorizationException('webhooks.manage'),
            $serverRequest,
            new Response(),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/acme', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'Owner access is required.',
        ], $testInertia->flash);
    }

    #[Test]
    public function itKeepsAdminAuthorizationDenialOnDefaultErrorFlowForBrowserRequests(): void
    {
        $response = ($this->generator())(
            new AuthorizationException('admin.access'),
            new ServerRequest([], [], '/admin'),
            new Response(),
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function itDoesNotExposeExceptionDetailsInProductionInertiaServerError(): void
    {
        $response = ($this->generatorWithServerErrorProps(function(array $props): void {
            self::assertSame(503, $props['status']);
            self::assertSame('Server error', $props['title']);
            self::assertSame('Something went wrong while processing your request.', $props['message']);
            self::assertFalse($props['debug']);
            self::assertArrayNotHasKey('exceptionClass', $props);
            self::assertArrayNotHasKey('file', $props);
            self::assertArrayNotHasKey('line', $props);
            self::assertArrayNotHasKey('trace', $props);
        }))(
            new RuntimeException('Sensitive backend detail', 503),
            new ServerRequest([], [], '/organization/acme/settings')->withHeader('X-Inertia', 'true'),
            new Response(),
        );

        self::assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function itExposesExceptionDetailsInDebugInertiaServerError(): void
    {
        $response = ($this->generatorWithServerErrorProps(function(array $props): void {
            self::assertSame('Sensitive backend detail', $props['message']);
            self::assertTrue($props['debug']);
            self::assertSame(RuntimeException::class, $props['exceptionClass']);
            self::assertIsString($props['file']);
            self::assertIsInt($props['line']);
            self::assertIsString($props['trace']);
        }, debug: true))(
            new RuntimeException('Sensitive backend detail', 503),
            new ServerRequest([], [], '/organization/acme/settings')->withHeader('X-Inertia', 'true'),
            new Response(),
        );

        self::assertSame(503, $response->getStatusCode());
    }

    private function generator(?InertiaFactoryInterface $inertiaFactory = null, bool $debug = false): InertiaErrorResponseGenerator
    {
        return new InertiaErrorResponseGenerator(
            $inertiaFactory ?? $this->createMock(InertiaFactoryInterface::class),
            $this->inertiaVersionProvider(),
            debug: $debug,
            templateRenderer: $this->createStub(TemplateRendererInterface::class),
            brandingConfig: new BrandingConfig(),
            initialThemeResolver: new InitialThemeResolver(),
            authorizationExceptionResponseFactory: new AuthorizationExceptionResponseFactory(new InertiaNotificationMessenger()),
        );
    }

    /**
     * @param callable(array<string, mixed>): void $assertProps
     */
    private function generatorWithServerErrorProps(callable $assertProps, bool $debug = false): InertiaErrorResponseGenerator
    {
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia
            ->expects($this->once())
            ->method('render')
            ->with(
                'Errors/ServerError',
                self::callback(function(array $props) use ($assertProps): bool {
                    $assertProps($props);

                    return true;
                }),
            )
            ->willReturn(new Response())
        ;

        $inertiaFactory = $this->createMock(InertiaFactoryInterface::class);
        $inertiaFactory
            ->expects($this->once())
            ->method('fromRequest')
            ->with(self::isInstanceOf(ServerRequest::class), 'active-version')
            ->willReturn($inertia)
        ;

        return $this->generator($inertiaFactory, $debug);
    }

    private function inertiaVersionProvider(): InertiaVersionProviderInterface
    {
        $inertiaVersionProvider = $this->createMock(InertiaVersionProviderInterface::class);
        $inertiaVersionProvider
            ->method('currentVersion')
            ->willReturn('active-version')
        ;

        return $inertiaVersionProvider;
    }
}
