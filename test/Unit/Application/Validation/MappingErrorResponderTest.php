<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Validation;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Packio\Application\Validation\MappingErrorDetailsNormalizer;
use Packio\Application\Validation\MappingErrorResponder;
use Packio\Application\Validation\ValidationFailureRequestPolicy;
use Packio\Application\Validation\ValidationFailureRoutePolicyResolver;
use PackioTest\Support\Fake\ValidationFailureCollaborators;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Sirix\Mezzio\Valinor\Error\DefaultMappingErrorResponder;
use Sirix\Mezzio\Valinor\Error\MappingErrorContext;
use Sirix\Mezzio\Valinor\Error\RequestInputError;

use function json_decode;

#[CoversClass(MappingErrorResponder::class)]
final class MappingErrorResponderTest extends TestCase
{
    use ValidationFailureCollaborators;

    /**
     * @param 'body'|'query'|'route'|'source' $source
     */
    #[Test]
    #[DataProvider('mappingSources')]
    public function itReturnsTheApiValidationEnvelopeForEveryMappingSourceRegardlessOfAcceptHeader(
        MapRequest $mapRequest,
        string $source,
    ): void {
        $response = $this->responder()->respond($this->context(
            new ServerRequest([], [], '/api/v1/examples', 'POST'),
            $mapRequest,
            $source,
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error' => [
                'code'    => 'validation_failed',
                'message' => 'Invalid request data.',
                'details' => [
                    'root' => ['Invalid value.'],
                ],
            ],
        ], $this->json($response));
    }

    /**
     * @return iterable<string, array{MapRequest, 'body'|'query'|'route'|'source'}>
     */
    public static function mappingSources(): iterable
    {
        yield 'body' => [new MapRequest(body: ExampleRequest::class), 'body'];

        yield 'query' => [new MapRequest(query: ExampleRequest::class), 'query'];

        yield 'route' => [new MapRequest(route: ExampleRequest::class), 'route'];

        yield 'source' => [new MapRequest(source: ExampleRequest::class), 'source'];
    }

    #[Test]
    public function itUsesTheSharedValidationPolicyForMatchingNonApiRoutes(): void
    {
        $route = new Route('/examples', $this->middleware(), ['POST'], 'examples');
        $route->setOptions([
            'validation_failed' => [[
                'message'     => 'Invalid example.',
                'json_status' => 400,
            ]],
            'valinor_mappings'  => [[
                'body'    => 'ExampleRequest',
                'methods' => ['POST'],
            ]],
        ]);
        $serverRequest = new ServerRequest([], [], '/examples', 'POST')
            ->withHeader('Accept', 'application/json')
            ->withAttribute(RouteResult::class, RouteResult::fromRoute($route))
        ;

        $response = $this->responder()->respond($this->context($serverRequest));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([
            'error'    => 'Invalid example.',
            'messages' => [
                'root' => ['Invalid value.'],
            ],
        ], $this->json($response));
    }

    #[Test]
    public function itDelegatesTheOriginalContextToThePackageFallbackWithoutAPolicy(): void
    {
        $response = $this->responder()->respond($this->context(
            new ServerRequest([], [], '/examples', 'POST'),
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame([
            'error'    => 'Mapping failed',
            'messages' => [
                '' => ["Value 'invalid' does not match `array{}`."],
            ],
        ], $this->json($response));
    }

    #[Test]
    public function itDelegatesToThePackageFallbackWhenTheMappingErrorIsNotOfTheFirstMappedDto(): void
    {
        $route = new Route('/examples', $this->middleware(), ['POST'], 'examples');
        $route->setOptions([
            'validation_failed' => [[
                'message'     => 'Invalid example.',
                'json_status' => 400,
            ]],
            'valinor_mappings'  => [
                [
                    'route' => 'OtherRequest',
                ],
                [
                    'body' => 'ExampleRequest',
                ],
            ],
        ]);
        $serverRequest = new ServerRequest([], [], '/examples', 'POST')
            ->withAttribute(RouteResult::class, RouteResult::fromRoute($route))
        ;

        $response = $this->responder()->respond($this->context($serverRequest));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error'    => 'Mapping failed',
            'messages' => [
                '' => ["Value 'invalid' does not match `array{}`."],
            ],
        ], $this->json($response));
    }

    #[Test]
    public function itReturnsTheSafeRequestInputErrorEnvelopeForApiPaths(): void
    {
        $response = $this->responder()->respond(new MappingErrorContext(
            RequestInputError::unsupportedParsedBody(),
            new ServerRequest([], [], '/api/v1/examples', 'POST'),
            new MapRequest(body: ExampleRequest::class),
            ExampleRequest::class,
            'body',
            'ExampleRequest',
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error' => [
                'code'    => 'validation_failed',
                'message' => 'Invalid request data.',
                'details' => [
                    'root' => ['Parsed request body must be an array or null.'],
                ],
            ],
        ], $this->json($response));
    }

    private function responder(): MappingErrorResponder
    {
        return new MappingErrorResponder(
            self::requestAwareValidationFailureResponseFactory(),
            new ValidationFailureRequestPolicy(),
            new ValidationFailureRoutePolicyResolver(),
            self::validationFailureResponseDispatcher(),
            new MappingErrorDetailsNormalizer(),
            new DefaultMappingErrorResponder(new ResponseFactory(), new StreamFactory()),
        );
    }

    /**
     * @param 'body'|'query'|'route'|'source' $source
     */
    private function context(
        ServerRequestInterface $serverRequest,
        ?MapRequest $mapRequest = null,
        string $source = 'body',
    ): MappingErrorContext {
        /** @var class-string $dtoClass */
        $dtoClass = ExampleRequest::class;

        return new MappingErrorContext(
            $this->mappingError(),
            $serverRequest,
            $mapRequest ?? new MapRequest(body: $dtoClass),
            $dtoClass,
            $source,
            'ExampleRequest',
        );
    }

    private function mappingError(): MappingError
    {
        try {
            new MapperBuilder()->mapper()->map('stdClass', 'invalid');
            self::fail('Mapping should fail.');
        } catch (MappingError $error) {
            return $error;
        }
    }

    private function middleware(): MiddlewareInterface
    {
        return new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}

final class ExampleRequest {}
