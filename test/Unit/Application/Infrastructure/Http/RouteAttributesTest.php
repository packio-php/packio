<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Http;

use Laminas\Diactoros\ServerRequest;
use LogicException;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteAttributes::class)]
final class RouteAttributesTest extends TestCase
{
    #[Test]
    public function requireStringReturnsAttributeValue(): void
    {
        $serverRequest = new ServerRequest()
            ->withAttribute('invitationId', 'invitation-1')
        ;

        self::assertSame('invitation-1', RouteAttributes::requireString($serverRequest, 'invitationId'));
    }

    #[Test]
    public function requireStringThrowsWhenAttributeIsMissing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Route attribute "invitationId" is missing or malformed.');

        RouteAttributes::requireString(new ServerRequest(), 'invitationId');
    }

    #[Test]
    public function requireStringThrowsWhenAttributeIsEmpty(): void
    {
        $serverRequest = new ServerRequest()
            ->withAttribute('invitationId', '')
        ;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Route attribute "invitationId" is missing or malformed.');

        RouteAttributes::requireString($serverRequest, 'invitationId');
    }

    #[Test]
    public function requireStringThrowsWhenAttributeIsMalformed(): void
    {
        $serverRequest = new ServerRequest()
            ->withAttribute('invitationId', 42)
        ;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Route attribute "invitationId" is missing or malformed.');

        RouteAttributes::requireString($serverRequest, 'invitationId');
    }

    #[Test]
    #[DataProvider('invalidUuidAttributes')]
    public function uuidReturnsEmptyStringForInvalidAttribute(mixed $value): void
    {
        $serverRequest = new ServerRequest()
            ->withAttribute('sourceId', $value)
        ;

        self::assertSame('', RouteAttributes::uuid($serverRequest, 'sourceId'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidUuidAttributes(): iterable
    {
        yield 'malformed' => ['not-a-uuid'];

        yield 'empty' => [''];

        yield 'not a string' => [42];
    }

    #[Test]
    public function uuidReturnsEmptyStringWhenAttributeIsMissing(): void
    {
        self::assertSame('', RouteAttributes::uuid(new ServerRequest(), 'sourceId'));
    }

    #[Test]
    #[DataProvider('validUuidAttributes')]
    public function uuidReturnsCanonicalLowercaseForm(string $value): void
    {
        $serverRequest = new ServerRequest()
            ->withAttribute('sourceId', $value)
        ;

        self::assertSame('0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d', RouteAttributes::uuid($serverRequest, 'sourceId'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validUuidAttributes(): iterable
    {
        yield 'uppercase' => ['0B9A4C3E-1D2F-4A5B-8C6D-7E8F9A0B1C2D'];

        yield 'braces' => ['{0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d}'];

        yield 'urn' => ['urn:uuid:0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d'];
    }
}
