<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Authentication;

use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Identity\Infrastructure\Authentication\SignInRecordingAuthManager;
use Packio\Identity\Infrastructure\Support\SignInRecorder;
use PackioTest\Support\Fake\RecordingUserSignInRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

#[CoversClass(SignInRecordingAuthManager::class)]
final class SignInRecordingAuthManagerTest extends TestCase
{
    private RecordingUserSignInRepository $signIns;

    protected function setUp(): void
    {
        $this->signIns = new RecordingUserSignInRepository();
    }

    #[Test]
    public function itStripsTheMethodAndRecordsAfterLogin(): void
    {
        $request  = new ServerRequest();
        $outer    = new RedirectResponse('/outer');
        $response = new RedirectResponse('/inner');
        $inner    = $this->createMock(AuthManagerInterface::class);
        $inner->expects(self::once())
            ->method('login')
            ->with($request, $outer, [
                'user_id'                 => 'user-1',
                'credentials_fingerprint' => 'fp',
            ], 123)
            ->willReturnCallback(function() use ($response): RedirectResponse {
                self::assertSame([], $this->signIns->saved, 'must record only after the inner login');

                return $response;
            })
        ;

        $result = $this->manager($inner)->login($request, $outer, [
            'user_id'                               => 'user-1',
            'credentials_fingerprint'               => 'fp',
            SignInRecordingAuthManager::PAYLOAD_KEY => 'github',
        ], 123);

        self::assertSame($response, $result);
        self::assertCount(1, $this->signIns->saved);
        self::assertSame('user-1', $this->signIns->saved[0]->userUuid());
        self::assertSame('github', $this->signIns->saved[0]->method());
    }

    #[Test]
    public function itDoesNotRecordWithoutTheMethodKey(): void
    {
        $request  = new ServerRequest();
        $response = new RedirectResponse('/outer');
        $inner    = $this->createMock(AuthManagerInterface::class);
        $inner->expects(self::once())->method('login')->with($request, $response, [
            'user_id' => 'user-1',
        ], null)->willReturn($response);

        $this->manager($inner)->login($request, $response, [
            'user_id' => 'user-1',
        ]);

        self::assertSame([], $this->signIns->saved);
    }

    #[Test]
    public function itRecordsOnlyAfterInnerLoginSucceeds(): void
    {
        $inner = $this->createStub(AuthManagerInterface::class);
        $inner->method('login')->willThrowException(new RuntimeException('login failed'));

        try {
            $this->manager($inner)->login(new ServerRequest(), new RedirectResponse('/'), [
                'user_id'                               => 'user-1',
                SignInRecordingAuthManager::PAYLOAD_KEY => 'password',
            ]);
            self::fail('Expected exception.');
        } catch (RuntimeException) {
            self::assertSame([], $this->signIns->saved);
        }
    }

    #[Test]
    public function itDelegatesTheOtherMethods(): void
    {
        $request  = new ServerRequest();
        $response = new RedirectResponse('/');
        $token    = $this->createStub(TokenInterface::class);
        $actor    = $this->createStub(ActorInterface::class);
        $context  = $this->createStub(AuthContextInterface::class);

        $inner = $this->createMock(AuthManagerInterface::class);
        $inner->expects(self::once())->method('logout')->with($request, $response)->willReturn($response);
        $inner->expects(self::once())->method('check')->with($request)->willReturn(true);
        $inner->expects(self::once())->method('guest')->with($request)->willReturn(false);
        $inner->expects(self::once())->method('token')->with($request)->willReturn($token);
        $inner->expects(self::once())->method('actor')->with($request)->willReturn($actor);
        $inner->expects(self::once())->method('context')->with($request)->willReturn($context);

        $manager = $this->manager($inner);

        self::assertSame($response, $manager->logout($request, $response));
        self::assertTrue($manager->check($request));
        self::assertFalse($manager->guest($request));
        self::assertSame($token, $manager->token($request));
        self::assertSame($actor, $manager->actor($request));
        self::assertSame($context, $manager->context($request));
    }

    private function manager(AuthManagerInterface $inner): SignInRecordingAuthManager
    {
        return new SignInRecordingAuthManager(
            $inner,
            new SignInRecorder($this->signIns, $this->createStub(ClientIpResolverInterface::class), $this->createStub(LoggerInterface::class)),
        );
    }
}
