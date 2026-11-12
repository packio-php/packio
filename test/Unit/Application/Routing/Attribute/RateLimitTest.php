<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Routing\Attribute;

use InvalidArgumentException;
use Packio\Application\Middleware\RateLimitMiddleware;
use Packio\Application\Routing\Attribute\RateLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimit::class)]
final class RateLimitTest extends TestCase
{
    #[Test]
    public function returnsMiddleware(): void
    {
        $rateLimit = new RateLimit(key: 'login');
        self::assertSame([RateLimitMiddleware::class], $rateLimit->getMiddleware());
    }

    #[Test]
    public function returnsDefaultsWithAllRequiredFields(): void
    {
        $rateLimit = new RateLimit(
            key: 'test_key',
            by: ['ip'],
        );

        $defaults = $rateLimit->getDefaults();
        self::assertArrayHasKey('rate_limit', $defaults);
        self::assertCount(1, $defaults['rate_limit']);

        $rule = $defaults['rate_limit'][0];
        self::assertSame('test_key', $rule['key']);
        self::assertSame(['ip'], $rule['by']);
        self::assertSame('json', $rule['response']);
        self::assertNull($rule['message']);
        self::assertSame(429, $rule['status']);
        self::assertNull($rule['redirect_to']);
        self::assertSame('error', $rule['level']);
        self::assertSame('flash', $rule['notify']);
        self::assertSame([], $rule['methods']);
    }

    #[Test]
    public function normalizesByToArrayWhenString(): void
    {
        $rateLimit = new RateLimit(key: 'test', by: 'ip');
        self::assertSame(['ip'], $rateLimit->by);
    }

    #[Test]
    public function defaultsByToIpWhenEmpty(): void
    {
        $rateLimit = new RateLimit(key: 'test', by: []);
        self::assertSame(['ip'], $rateLimit->by);
    }

    #[Test]
    public function supportsMultipleByStrategies(): void
    {
        $rateLimit = new RateLimit(key: 'test', by: ['ip', 'body:email']);
        self::assertSame(['ip', 'body:email'], $rateLimit->by);
    }

    #[Test]
    public function normalizesMethodsToUppercase(): void
    {
        $rateLimit = new RateLimit(key: 'test', methods: ['post', 'GET']);
        self::assertSame(['GET', 'POST'], $rateLimit->methods);
    }

    #[Test]
    public function throwsOnEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$key must be a non-empty string');
        new RateLimit(key: '');
    }

    #[Test]
    public function throwsOnInvalidResponseType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$response must be one of');
        new RateLimit(key: 'test', response: 'invalid');
    }

    #[Test]
    public function throwsOnInvalidStatusCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$status must be between 400 and 599');
        new RateLimit(key: 'test', status: 399);
    }

    #[Test]
    public function throwsOnInvalidByStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid "by" strategy');
        new RateLimit(key: 'test', by: ['invalid_strategy']);
    }

    #[Test]
    public function acceptsAllResponseTypes(): void
    {
        foreach (['json', 'redirect', 'empty'] as $type) {
            $attribute = new RateLimit(key: 'test', response: $type);
            self::assertSame($type, $attribute->response);
        }
    }

    #[Test]
    public function acceptsValidStatusCodes(): void
    {
        foreach ([400, 429, 500, 503, 599] as $status) {
            $attribute = new RateLimit(key: 'test', status: $status);
            self::assertSame($status, $attribute->status);
        }
    }

    #[Test]
    public function storesCustomResponseOptions(): void
    {
        $rateLimit = new RateLimit(
            key: 'test',
            message: 'Custom message',
            status: 503,
            redirectTo: '/custom-redirect',
            level: 'warning',
            notify: 'toast',
        );

        $defaults = $rateLimit->getDefaults();
        $rule     = $defaults['rate_limit'][0];

        self::assertSame('Custom message', $rule['message']);
        self::assertSame(503, $rule['status']);
        self::assertSame('/custom-redirect', $rule['redirect_to']);
        self::assertSame('warning', $rule['level']);
        self::assertSame('toast', $rule['notify']);
    }

    #[Test]
    public function deduplicatesByStrategies(): void
    {
        $rateLimit = new RateLimit(key: 'test', by: ['ip', 'ip', 'body:email']);
        self::assertSame(['ip', 'body:email'], $rateLimit->by);
    }
}
