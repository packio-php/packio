<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Validation;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Packio\Application\Validation\ValidationFailureRoutePolicyResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(ValidationFailureRoutePolicyResolver::class)]
final class ValidationFailureRoutePolicyResolverTest extends TestCase
{
    #[Test]
    public function itSelectsTheFirstPolicyForTheDtoOfTheFirstMapping(): void
    {
        $policy = new ValidationFailureRoutePolicyResolver()->resolve($this->request([
            'validation_failed' => [
                [
                    'message' => 'First.',
                ],
                [
                    'message' => 'Second.',
                ],
            ],
            'valinor_mappings'  => [
                [
                    'route' => 'RouteRequest',
                ],
                [
                    'body' => 'BodyRequest',
                ],
            ],
        ]));

        self::assertNotNull($policy);
        self::assertSame('RouteRequest', $policy->targetAttribute);
        self::assertSame([
            'message' => 'First.',
        ], $policy->options);
    }

    #[Test]
    public function itSelectsThePolicyWhenTheRequestedAttributeIsTheFirstMappingDto(): void
    {
        $policy = new ValidationFailureRoutePolicyResolver()->resolve($this->request([
            'validation_failed' => [[
                'message' => 'First.',
            ]],
            'valinor_mappings'  => [
                [
                    'route' => 'RouteRequest',
                ],
                [
                    'body' => 'BodyRequest',
                ],
            ],
        ]), 'RouteRequest');

        self::assertNotNull($policy);
        self::assertSame('RouteRequest', $policy->targetAttribute);
    }

    #[Test]
    public function itReturnsNullForAMappingErrorOfAnotherDto(): void
    {
        self::assertNull(new ValidationFailureRoutePolicyResolver()->resolve($this->request([
            'validation_failed' => [[
                'message' => 'First.',
            ]],
            'valinor_mappings'  => [
                [
                    'route' => 'RouteRequest',
                ],
                [
                    'body' => 'BodyRequest',
                ],
            ],
        ]), 'BodyRequest'));
    }

    #[Test]
    public function itReturnsNullWithoutAPolicy(): void
    {
        self::assertNull(new ValidationFailureRoutePolicyResolver()->resolve($this->request([
            'valinor_mappings' => [[
                'body' => 'BodyRequest',
            ]],
        ])));
    }

    /** @param array<string, mixed> $options */
    private function request(array $options): ServerRequestInterface
    {
        $route = new Route('/example', new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        }, ['POST'], 'example');
        $route->setOptions($options);

        return new ServerRequest([], [], '/example', 'POST')
            ->withAttribute(RouteResult::class, RouteResult::fromRoute($route))
        ;
    }
}
