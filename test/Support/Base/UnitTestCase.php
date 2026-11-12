<?php

declare(strict_types=1);

namespace PackioTest\Support\Base;

use PackioTest\Support\Http\TestResponse;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

abstract class UnitTestCase extends TestCase
{
    protected function response(ResponseInterface $response): TestResponse
    {
        return new TestResponse($response);
    }

    protected function assertResponseStatus(ResponseInterface $response, int $status): void
    {
        Assert::assertSame($status, $response->getStatusCode());
    }

    protected function assertHeader(ResponseInterface $response, string $name, ?string $value = null): void
    {
        Assert::assertTrue($response->hasHeader($name));

        if (null !== $value) {
            Assert::assertSame($value, $response->getHeaderLine($name));
        }
    }
}
