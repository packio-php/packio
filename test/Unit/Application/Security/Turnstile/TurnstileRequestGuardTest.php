<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security\Turnstile;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Security\Turnstile\TurnstileVerifierInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TurnstileRequestGuard::class)]
#[AllowMockObjectsWithoutExpectations]
final class TurnstileRequestGuardTest extends TestCase
{
    public function testItAllowsRequestsWhenTurnstileIsDisabled(): void
    {
        $turnstileVerifier = $this->createMock(TurnstileVerifierInterface::class);
        $turnstileVerifier->expects(self::never())->method('verify');

        $turnstileRequestGuard = new TurnstileRequestGuard(
            $turnstileVerifier,
            $this->createMock(ClientIpResolverInterface::class),
            [
                'enabled'    => false,
                'site_key'   => '',
                'secret_key' => '',
            ],
        );

        self::assertSame([
            'enabled'  => false,
            'site_key' => '',
        ], $turnstileRequestGuard->payload());
        self::assertTrue($turnstileRequestGuard->verify(new ServerRequest()));
    }

    public function testItRejectsFilledHoneypotWhenTurnstileIsDisabled(): void
    {
        $turnstileVerifier = $this->createMock(TurnstileVerifierInterface::class);
        $turnstileVerifier->expects(self::never())->method('verify');

        $turnstileRequestGuard = new TurnstileRequestGuard(
            $turnstileVerifier,
            $this->createStub(ClientIpResolverInterface::class),
            [
                'enabled' => false,
            ],
        );

        self::assertFalse($turnstileRequestGuard->verify(new ServerRequest()->withParsedBody([
            'h_0123456789abcdef' => 'bot',
        ])));
        self::assertTrue($turnstileRequestGuard->verify(new ServerRequest()->withParsedBody([
            'h_0123456789abcdef' => '',
        ])));
    }

    public function testItRejectsFilledHoneypotWhenTurnstileIsEnabled(): void
    {
        $turnstileVerifier = $this->createMock(TurnstileVerifierInterface::class);
        $turnstileVerifier->expects(self::never())->method('verify');

        $turnstileRequestGuard = new TurnstileRequestGuard(
            $turnstileVerifier,
            $this->createStub(ClientIpResolverInterface::class),
            [
                'enabled'    => true,
                'site_key'   => 'site',
                'secret_key' => 'secret',
            ],
        );

        self::assertFalse($turnstileRequestGuard->verify(new ServerRequest()->withParsedBody([
            'h_0123456789abcdef'                  => 'bot',
            TurnstileRequestGuard::TOKEN_FIELD    => 'token',
        ])));
    }

    public function testItRejectsEnabledRequestsWithoutToken(): void
    {
        $turnstileVerifier = $this->createMock(TurnstileVerifierInterface::class);
        $turnstileVerifier->expects(self::never())->method('verify');

        $turnstileRequestGuard = new TurnstileRequestGuard(
            $turnstileVerifier,
            $this->createMock(ClientIpResolverInterface::class),
            [
                'enabled'    => true,
                'site_key'   => 'site',
                'secret_key' => 'secret',
            ],
        );

        self::assertSame([
            'enabled'  => true,
            'site_key' => 'site',
        ], $turnstileRequestGuard->payload());
        self::assertFalse($turnstileRequestGuard->verify(new ServerRequest()->withParsedBody([])));
    }

    public function testItVerifiesTokenWithResolvedClientIp(): void
    {
        $serverRequest = new ServerRequest()->withParsedBody([
            TurnstileRequestGuard::TOKEN_FIELD => ' token ',
        ]);

        $clientIpResolver = $this->createMock(ClientIpResolverInterface::class);
        $clientIpResolver
            ->expects(self::once())
            ->method('resolve')
            ->with($serverRequest)
            ->willReturn('127.0.0.1')
        ;

        $turnstileVerifier = $this->createMock(TurnstileVerifierInterface::class);
        $turnstileVerifier
            ->expects(self::once())
            ->method('verify')
            ->with('token', '127.0.0.1')
            ->willReturn(true)
        ;

        $turnstileRequestGuard = new TurnstileRequestGuard(
            $turnstileVerifier,
            $clientIpResolver,
            [
                'enabled'    => true,
                'site_key'   => 'site',
                'secret_key' => 'secret',
            ],
        );

        self::assertTrue($turnstileRequestGuard->verify($serverRequest));
    }
}
