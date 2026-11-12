<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Routing;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Packio\Application\Routing\CsrfRouteOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;

#[CoversClass(CsrfRouteOptions::class)]
final class CsrfRouteOptionsTest extends TestCase
{
    #[Test]
    public function returnsTheExplicitBooleanExemption(): void
    {
        self::assertTrue(CsrfRouteOptions::isExempt($this->request([
            CsrfRouteOptions::EXEMPT => true,
        ])));
        self::assertFalse(CsrfRouteOptions::isExempt($this->request([])));
    }

    #[Test]
    public function rejectsUnmatchedAndMalformedPolicies(): void
    {
        $this->expectException(UnexpectedValueException::class);
        CsrfRouteOptions::isExempt(new ServerRequest());
    }

    #[Test]
    public function rejectsNonBooleanExemptionValues(): void
    {
        $this->expectException(UnexpectedValueException::class);
        CsrfRouteOptions::isExempt($this->request([
            CsrfRouteOptions::EXEMPT => 'yes',
        ]));
    }

    /** @param array<string, mixed> $options */
    private function request(array $options): ServerRequestInterface
    {
        $route = new Route('/api/v1/example', new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new EmptyResponse();
            }
        });
        $route->setOptions($options);

        return new ServerRequest()->withAttribute(RouteResult::class, RouteResult::fromRoute($route));
    }
}
