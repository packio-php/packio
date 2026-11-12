<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security\Turnstile;

use GuzzleHttp\ClientInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Security\Turnstile\TurnstileVerifier;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TurnstileVerifier::class)]
#[AllowMockObjectsWithoutExpectations]
final class TurnstileVerifierTest extends TestCase
{
    #[Test]
    public function itReturnsFalseWhenVerifierNotConfigured(): void
    {
        $httpClient        = $this->createMock(ClientInterface::class);
        $turnstileVerifier = new TurnstileVerifier($httpClient, null, '', 'https://verify.invalid');

        self::assertFalse($turnstileVerifier->verify('token', '127.0.0.1'));
    }

    #[Test]
    public function itReturnsFalseWhenTokenIsInvalid(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willReturn(
            new JsonResponse([
                'success'     => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        );

        $turnstileVerifier = new TurnstileVerifier($httpClient, null, 'secret', 'https://verify.invalid');

        self::assertFalse($turnstileVerifier->verify('token'));
    }

    #[Test]
    public function itReturnsFalseWhenHostnameMismatchAndValidationEnabled(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willReturn(
            new JsonResponse([
                'success'  => true,
                'hostname' => 'evil.test',
            ], 200),
        );

        $turnstileVerifier = new TurnstileVerifier(
            $httpClient,
            null,
            'secret',
            'https://verify.invalid',
            'packio.test',
            true,
        );

        self::assertFalse($turnstileVerifier->verify('token'));
    }

    #[Test]
    public function itReturnsTrueOnValidResponse(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willReturn(
            new JsonResponse([
                'success'  => true,
                'hostname' => 'packio.test',
            ], 200),
        );

        $turnstileVerifier = new TurnstileVerifier(
            $httpClient,
            null,
            'secret',
            'https://verify.invalid',
            'packio.test',
            true,
        );

        self::assertTrue($turnstileVerifier->verify('token', '127.0.0.1'));
    }

    #[Test]
    public function itReturnsFalseWhenRequestFails(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willThrowException(new RuntimeException('network failure'));

        $turnstileVerifier = new TurnstileVerifier($httpClient, null, 'secret', 'https://verify.invalid');

        self::assertFalse($turnstileVerifier->verify('token'));
    }
}
