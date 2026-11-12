<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Logging;

use Common\Logging\SafeExceptionContext;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function json_encode;

final class SafeExceptionContextTest extends TestCase
{
    #[DataProvider('secrets')]
    public function testSecretsAreRedactedInEveryCause(string $message): void
    {
        $cause     = new RuntimeException($message);
        $exception = new RuntimeException('Outer error', 0, $cause);
        $context   = SafeExceptionContext::forRequest($exception, new ServerRequest()->withAttribute('request_id', 'request-1'));
        self::assertSame('request-1', $context['correlation_id']);
        self::assertSame(RuntimeException::class, $context['exception']['previous']['class']);
        self::assertStringNotContainsString('private-secret', json_encode($context, JSON_THROW_ON_ERROR));
        self::assertStringContainsString('REDACTED', $context['exception']['previous']['message']);
        foreach ($context['exception']['trace'] as $frame) {
            self::assertArrayNotHasKey('args', $frame);
            self::assertArrayNotHasKey('object', $frame);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function secrets(): iterable
    {
        yield 'SSH userinfo' => ['ssh://user:private-secret@example.test/repositories'];

        yield 'multiword password' => ['{"password":"a private-secret value"}'];

        yield 'credentials' => ['credentials=private-secret'];

        yield 'URL userinfo' => ['https://user:private-secret@example.test/repositories'];

        yield 'query' => ['https://example.test?access_token=private-secret&client_secret=private-secret'];

        yield 'JSON' => ['{"refresh_token":"private-secret","password":"private-secret"}'];

        yield 'bearer' => ['Authorization: Bearer private-secret'];

        yield 'basic' => ['Authorization: Basic private-secret'];

        yield 'private token' => ['PRIVATE-TOKEN: private-secret'];
    }

    #[DataProvider('paths')]
    public function testRedactPathHidesTokenLikeSegmentsOnly(string $path, string $expected): void
    {
        self::assertSame($expected, SafeExceptionContext::redactPath($path));
    }

    /** @return iterable<string, array{string, string}> */
    public static function paths(): iterable
    {
        yield 'reset token' => ['/auth/reset-password/AbCdEfGhIjKlMnOpQrSt/UvWxYz0123456789abcd', '/auth/reset-password/[REDACTED]/[REDACTED]'];

        yield 'uuid and slug' => ['/organization/acme/sources/0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d', '/organization/acme/sources/0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d'];

        yield 'uppercase uuid' => ['/organization/acme/sources/0B9A4C3E-1D2F-4A5B-8C6D-7E8F9A0B1C2D', '/organization/acme/sources/0B9A4C3E-1D2F-4A5B-8C6D-7E8F9A0B1C2D'];

        yield '19 char segment kept' => ['/x/aaaaaaaaaaaaaaaaaaa', '/x/aaaaaaaaaaaaaaaaaaa'];

        yield '20 char segment redacted' => ['/x/aaaaaaaaaaaaaaaaaaaa', '/x/[REDACTED]'];

        yield 'plain' => ['/admin/users', '/admin/users'];
    }
}
