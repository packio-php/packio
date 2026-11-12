<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Middleware\ValidateMappedRequestMiddleware;
use Packio\Application\Validation\InvalidMappedRequestValidationPlan;
use Packio\Organization\Infrastructure\Request\OrganizationDeleteRequest;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Support\Fake\ValidationFailureCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;

#[CoversClass(ValidateMappedRequestMiddleware::class)]
#[AllowMockObjectsWithoutExpectations]
final class ValidateMappedRequestMiddlewareTest extends TestCase
{
    use ValidationFailureCollaborators;

    #[Test]
    public function invalidWebRequestRedirectsUsingConfiguredPathTemplate(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new Result()->addError('Required', [], ['confirmationName']));

        $validateMappedRequestMiddleware = self::validateMappedRequestMiddleware($validator);

        $serverRequest = $this->requestWithRouteOptions([
            'validation_failed'          => [[
                'redirect_to' => '/organization/{organization}/settings',
                'message'     => 'Invalid request data.',
                'notify'      => 'toast',
                'level'       => 'error',
                'json_status' => 422,
            ]],
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ]);

        $response = $validateMappedRequestMiddleware->process($serverRequest, new ValidationCapturingHandler());

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/acme/settings', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function invalidJsonRequestReturnsStructured422Response(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new Result()->addError('Required', [], ['confirmationName']));

        $validateMappedRequestMiddleware = self::validateMappedRequestMiddleware($validator);

        $serverRequest = $this->requestWithRouteOptions([
            'validation_failed'          => [[
                'message'     => 'Invalid request data.',
                'notify'      => 'none',
                'json_status' => 422,
            ]],
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ])->withHeader('Accept', 'application/json');

        $response = $validateMappedRequestMiddleware->process($serverRequest, new ValidationCapturingHandler());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Invalid request data.', (string) $response->getBody());
        self::assertStringContainsString('confirmation_name', (string) $response->getBody());
    }

    #[Test]
    public function invalidInertiaRequestDoesNotUseJsonEvenWhenSentAsXmlHttpRequest(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new Result()->addError('Required', [], ['confirmationName']));

        $validateMappedRequestMiddleware = self::validateMappedRequestMiddleware($validator);

        $serverRequest = $this->requestWithRouteOptions([
            'validation_failed'          => [[
                'redirect_to' => '/organization/{organization}/settings',
                'message'     => 'Invalid request data.',
                'notify'      => 'none',
                'json_status' => 422,
            ]],
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ])
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ;

        $response = $validateMappedRequestMiddleware->process($serverRequest, new ValidationCapturingHandler());

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/acme/settings', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function invalidApiRequestReturnsJsonWithoutAcceptHeader(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new Result()->addError('Required', [], ['confirmationName']));

        $validateMappedRequestMiddleware = self::validateMappedRequestMiddleware($validator);

        $serverRequest = $this->requestWithRouteOptions([
            'validation_failed'          => [[
                'message'     => 'Invalid request data.',
                'notify'      => 'none',
                'json_status' => 422,
            ]],
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ])->withUri(new ServerRequest([], [], '/api/examples', 'POST')->getUri());

        $response = $validateMappedRequestMiddleware->process($serverRequest, new ValidationCapturingHandler());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Invalid request data.', (string) $response->getBody());
    }

    #[Test]
    public function validRequestPassesToNextHandler(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new Result());

        $validateMappedRequestMiddleware = self::validateMappedRequestMiddleware($validator);

        $validationCapturingHandler = new ValidationCapturingHandler();
        $serverRequest              = $this->requestWithRouteOptions([
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ])->withAttribute(OrganizationDeleteRequest::class, new OrganizationDeleteRequest('Acme'));

        $response = $validateMappedRequestMiddleware->process($serverRequest, $validationCapturingHandler);

        self::assertTrue($validationCapturingHandler->called);
        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function invalidRequestPersistsOnlyRouteAllowlistedOldInput(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new Result()->addError('Required', [], ['confirmationName']));
        $validationRedirectState         = new ValidationRedirectState();
        $validateMappedRequestMiddleware = self::validateMappedRequestMiddleware($validator, self::validationFailureResponseDispatcher($validationRedirectState));
        $inMemorySession                 = new InMemorySession();
        $serverRequest                   = $this->requestWithRouteOptions([
            'validation_failed'          => [[
                'old_input' => ['confirmationName'],
            ]],
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ])
            ->withParsedBody([
                'confirmationName'      => 'Acme',
                'password'              => 'secret',
                'token'                 => 'token-value',
                'cf-turnstile-response' => 'captcha-value',
                'verification_code'     => '123456',
                'api_key'               => 'api-credential',
                'client_secret'         => 'oauth-client-secret',
                'nested'                => [
                    'dynamic_honeypot' => 'bot',
                ],
            ])
            ->withAttribute(SessionInterface::class, $inMemorySession)
        ;

        $validateMappedRequestMiddleware->process($serverRequest, new ValidationCapturingHandler());

        self::assertSame([
            'confirmationName' => 'Acme',
        ], $validationRedirectState->consumeOld($serverRequest));
    }

    #[Test]
    public function mapperOnlyRouteWithoutValidationMetadataRunsTheHandlerWithoutTheValidator(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $validateMappedRequestMiddleware                    = self::validateMappedRequestMiddleware($validator);
        $validationCapturingHandler                         = new ValidationCapturingHandler();

        $serverRequest = $this->requestWithRouteOptions([
            'valinor_mappings' => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
        ])->withAttribute(OrganizationDeleteRequest::class, new OrganizationDeleteRequest('Acme'));

        $validateMappedRequestMiddleware->process($serverRequest, $validationCapturingHandler);

        self::assertTrue($validationCapturingHandler->called);
    }

    #[Test]
    public function explicitValidationRunsEvenWithoutValidationFailedOverride(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())->method('validate')->willReturn(new Result()->addError('Required', [], ['name']));

        $validateMappedRequestMiddleware                    = self::validateMappedRequestMiddleware($validator);
        $validationCapturingHandler                         = new ValidationCapturingHandler();

        $serverRequest = $this->requestWithRouteOptions([
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ])->withAttribute(OrganizationDeleteRequest::class, new OrganizationDeleteRequest(''));

        $response = $validateMappedRequestMiddleware->process($serverRequest, $validationCapturingHandler);

        self::assertFalse($validationCapturingHandler->called);
        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function missingMappedTargetFailsClosedWithTypedException(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $validateMappedRequestMiddleware                    = self::validateMappedRequestMiddleware($validator);
        $validationCapturingHandler                         = new ValidationCapturingHandler();

        $serverRequest = $this->requestWithRouteOptions([
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ]);

        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $validateMappedRequestMiddleware->process($serverRequest->withoutAttribute(OrganizationDeleteRequest::class), $validationCapturingHandler);
    }

    #[Test]
    public function nonObjectMappedTargetFailsClosed(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $validateMappedRequestMiddleware                    = self::validateMappedRequestMiddleware($validator);
        $validationCapturingHandler                         = new ValidationCapturingHandler();

        $serverRequest = $this->requestWithRouteOptions([
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ])->withAttribute(OrganizationDeleteRequest::class, 'not-an-object');

        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $validateMappedRequestMiddleware->process($serverRequest, $validationCapturingHandler);
    }

    #[Test]
    public function secondInvalidSelectedTargetStopsBeforeTheHandler(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new Result()->addError('Required', [], ['name']));

        $validateMappedRequestMiddleware                    = self::validateMappedRequestMiddleware($validator);
        $validationCapturingHandler                         = new ValidationCapturingHandler();

        $serverRequest = $this->requestWithRouteOptions([
            'valinor_mappings'           => [
                [
                    'body'    => OrganizationDeleteRequest::class,
                    'output'  => OrganizationDeleteRequest::class,
                    'methods' => ['POST'],
                ],
                [
                    'body'    => SecondValidationDto::class,
                    'output'  => SecondValidationDto::class,
                    'methods' => ['POST'],
                ],
            ],
            'mapped_request_validations' => [
                [
                    'dto' => OrganizationDeleteRequest::class,
                ],
                [
                    'dto' => SecondValidationDto::class,
                ],
            ],
        ])
            ->withAttribute(OrganizationDeleteRequest::class, new OrganizationDeleteRequest(''))
            ->withAttribute(SecondValidationDto::class, new SecondValidationDto(''))
        ;

        $response = $validateMappedRequestMiddleware->process($serverRequest, $validationCapturingHandler);

        self::assertFalse($validationCapturingHandler->called);
        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function apiPathWithInertiaHeaderStillReturnsTheApiEnvelope(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new Result()->addError('Required', [], ['confirmationName']));

        $validateMappedRequestMiddleware = self::validateMappedRequestMiddleware($validator);

        $serverRequest = $this->requestWithRouteOptions([
            'valinor_mappings'           => [[
                'body'    => OrganizationDeleteRequest::class,
                'output'  => OrganizationDeleteRequest::class,
                'methods' => ['POST'],
            ]],
            'mapped_request_validations' => [[
                'dto' => OrganizationDeleteRequest::class,
            ]],
        ])
            ->withUri(new ServerRequest([], [], '/api/examples', 'POST')->getUri())
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ;

        $response = $validateMappedRequestMiddleware->process($serverRequest, new ValidationCapturingHandler());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('error', (string) $response->getBody());
    }

    /** @param array<string, mixed> $options */
    private function requestWithRouteOptions(array $options): ServerRequestInterface
    {
        $route = new Route(
            '/organization/:organization/settings/delete',
            new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            },
            ['POST'],
            'organization.settings.delete',
        );
        $route->setOptions($options);

        $routeResult = RouteResult::fromRoute($route, [
            'organization' => 'acme',
        ]);

        return new ServerRequest([], [], '/organization/acme/settings/delete', 'POST')
            ->withParsedBody([
                'confirmationName' => '',
            ])
            ->withAttribute(RouteResult::class, $routeResult)
            ->withAttribute('organization', 'acme')
            ->withAttribute(OrganizationDeleteRequest::class, new OrganizationDeleteRequest(''))
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
        ;
    }
}

final class SecondValidationDto
{
    public function __construct(public string $name = '') {}
}

final class ValidationCapturingHandler implements RequestHandlerInterface
{
    public bool $called = false;

    public function handle(ServerRequestInterface $request): EmptyResponse
    {
        $this->called = true;

        return new EmptyResponse(204);
    }
}
