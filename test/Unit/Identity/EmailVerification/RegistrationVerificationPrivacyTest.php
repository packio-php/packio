<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\EmailVerification;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Security\Turnstile\TurnstileVerifierInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Infrastructure\Handler\EmailVerificationResendHandler;
use Packio\Identity\Infrastructure\Handler\EmailVerificationSendHandler;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

use function json_decode;
use function time;

final class RegistrationVerificationPrivacyTest extends TestCase
{
    #[DataProvider('deliveryCases')]
    public function testDeliveryResponsesAndCooldownDoNotRevealAccountOrMailFailure(bool $decoy, bool $failure, bool $json): void
    {
        $inMemorySession                 = new InMemorySession();
        $pendingEmailVerificationSession = new PendingEmailVerificationSession();
        $pendingEmailVerificationSession->startRegistration($inMemorySession, $decoy ? null : 'new-user');
        $inMemorySession->set('registration_resend_at', time() - 121);
        $testInertia   = new TestInertia();
        $serverRequest = new ServerRequest()->withAttribute('session', $inMemorySession)->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia);
        $facade        = $this->createMock(IdentityApiFacadeInterface::class);
        $facade->expects(self::never())->method('emailVerificationRemainingResendDelay');
        $invocationStubber = $facade->expects(self::once())->method('requestEmailVerification');
        if ($failure) {
            $invocationStubber->willThrowException(new RuntimeException('Queue unavailable'));
        }
        $handler = $json
            ? new EmailVerificationSendHandler($facade, $pendingEmailVerificationSession)
            : new EmailVerificationResendHandler($facade, new InertiaNotificationMessenger(), new TurnstileRequestGuard($this->createStub(TurnstileVerifierInterface::class), $this->createStub(ClientIpResolverInterface::class), [
                'enabled' => false,
            ]), $pendingEmailVerificationSession);
        $response = $handler->handle($serverRequest);
        if ($json) {
            self::assertSame(200, $response->getStatusCode());
            self::assertSame([
                'success' => true,
                'message' => 'Verification code sent.',
            ], json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            self::assertSame('/auth/verification', $response->getHeaderLine('Location'));
            self::assertSame([
                'success' => 'Verification code sent.',
            ], $testInertia->flash);
        }
        self::assertContains($pendingEmailVerificationSession->registrationDelay($inMemorySession), [119, 120]);
    }

    /** @return iterable<array{bool, bool, bool}> */
    public static function deliveryCases(): iterable
    {
        foreach ([false, true] as $json) {
            yield [true, false, $json];

            yield [false, false, $json];

            yield [false, true, $json];
        }
    }
}
