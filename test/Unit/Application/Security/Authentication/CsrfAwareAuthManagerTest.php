<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security\Authentication;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use LogicException;
use Mezzio\Session\SessionInterface;
use Packio\Application\Security\Authentication\CsrfAwareAuthManager;
use Packio\Application\Security\Csrf\CsrfTokenContract;
use Packio\Application\Security\Csrf\CsrfTokenManager;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

#[CoversClass(CsrfAwareAuthManager::class)]
final class CsrfAwareAuthManagerTest extends TestCase
{
    #[Test]
    public function itRotatesTheCsrfTokenExactlyOnceAfterLogin(): void
    {
        $csrfLifecycleSession = new CsrfLifecycleSession();
        $csrfLifecycleSession->set(CsrfTokenContract::SESSION_KEY, 'old-token');
        $csrfLifecycleSession->resetCsrfOperationCounts();
        $serverRequest        = $this->requestWithSession($csrfLifecycleSession);
        $emptyResponse        = new EmptyResponse(204);

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::once())
            ->method('login')
            ->with($serverRequest, $emptyResponse, [
                'user_id' => 'user-1',
            ], null)
            ->willReturn($emptyResponse)
        ;

        $response = $this->manager($authManager)->login($serverRequest, $emptyResponse, [
            'user_id' => 'user-1',
        ]);

        self::assertSame($emptyResponse, $response);
        self::assertSame(1, $csrfLifecycleSession->csrfSetCount);
        self::assertSame(0, $csrfLifecycleSession->csrfUnsetCount);
        self::assertNotSame('old-token', $csrfLifecycleSession->get(CsrfTokenContract::SESSION_KEY));
    }

    #[Test]
    public function itClearsTheCsrfTokenExactlyOnceAfterLogout(): void
    {
        $csrfLifecycleSession = new CsrfLifecycleSession();
        $csrfLifecycleSession->set(CsrfTokenContract::SESSION_KEY, 'token');
        $csrfLifecycleSession->resetCsrfOperationCounts();
        $serverRequest        = $this->requestWithSession($csrfLifecycleSession);
        $emptyResponse        = new EmptyResponse(204);

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::once())
            ->method('logout')
            ->with($serverRequest, $emptyResponse)
            ->willReturn($emptyResponse)
        ;

        $response = $this->manager($authManager)->logout($serverRequest, $emptyResponse);

        self::assertSame($emptyResponse, $response);
        self::assertSame(0, $csrfLifecycleSession->csrfSetCount);
        self::assertSame(1, $csrfLifecycleSession->csrfUnsetCount);
        self::assertFalse($csrfLifecycleSession->has(CsrfTokenContract::SESSION_KEY));
    }

    #[Test]
    public function itLeavesTheCsrfTokenUnchangedWhenLoginFails(): void
    {
        $csrfLifecycleSession = new CsrfLifecycleSession();
        $csrfLifecycleSession->set(CsrfTokenContract::SESSION_KEY, 'token');
        $csrfLifecycleSession->resetCsrfOperationCounts();
        $serverRequest        = $this->requestWithSession($csrfLifecycleSession);
        $emptyResponse        = new EmptyResponse();

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::once())->method('login')->willThrowException(new RuntimeException('Login failed.'));

        try {
            $this->manager($authManager)->login($serverRequest, $emptyResponse, [
                'user_id' => 'user-1',
            ]);
            self::fail('The inner login exception was not rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Login failed.', $exception->getMessage());
        }

        self::assertSame(0, $csrfLifecycleSession->csrfSetCount);
        self::assertSame(0, $csrfLifecycleSession->csrfUnsetCount);
        self::assertSame('token', $csrfLifecycleSession->get(CsrfTokenContract::SESSION_KEY));
    }

    #[Test]
    public function itLeavesTheCsrfTokenUnchangedWhenLogoutFails(): void
    {
        $csrfLifecycleSession = new CsrfLifecycleSession();
        $csrfLifecycleSession->set(CsrfTokenContract::SESSION_KEY, 'token');
        $csrfLifecycleSession->resetCsrfOperationCounts();
        $serverRequest        = $this->requestWithSession($csrfLifecycleSession);
        $emptyResponse        = new EmptyResponse();

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::once())->method('logout')->willThrowException(new RuntimeException('Logout failed.'));

        try {
            $this->manager($authManager)->logout($serverRequest, $emptyResponse);
            self::fail('The inner logout exception was not rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Logout failed.', $exception->getMessage());
        }

        self::assertSame(0, $csrfLifecycleSession->csrfSetCount);
        self::assertSame(0, $csrfLifecycleSession->csrfUnsetCount);
        self::assertSame('token', $csrfLifecycleSession->get(CsrfTokenContract::SESSION_KEY));
    }

    #[Test]
    public function itRequiresASessionBeforeLogin(): void
    {
        $serverRequest = new ServerRequest();
        $emptyResponse = new EmptyResponse();
        $authManager   = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::never())->method('login');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('CSRF lifecycle requires a session');

        $this->manager($authManager)->login($serverRequest, $emptyResponse, [
            'user_id' => 'user-1',
        ]);
    }

    #[Test]
    public function itRequiresASessionBeforeLogout(): void
    {
        $serverRequest = new ServerRequest();
        $emptyResponse = new EmptyResponse();
        $authManager   = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::never())->method('logout');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('CSRF lifecycle requires a session');

        $this->manager($authManager)->logout($serverRequest, $emptyResponse);
    }

    private function manager(AuthManagerInterface $authManager): CsrfAwareAuthManager
    {
        return new CsrfAwareAuthManager($authManager, new CsrfTokenManager());
    }

    private function requestWithSession(SessionInterface $session): ServerRequestInterface
    {
        return new ServerRequest()->withAttribute(SessionInterface::class, $session);
    }
}

final class CsrfLifecycleSession extends InMemorySession
{
    public int $csrfSetCount = 0;

    public int $csrfUnsetCount = 0;

    public function set(string $name, $value): void
    {
        parent::set($name, $value);

        if (CsrfTokenContract::SESSION_KEY === $name) {
            ++$this->csrfSetCount;
        }
    }

    public function unset(string $name): void
    {
        parent::unset($name);

        if (CsrfTokenContract::SESSION_KEY === $name) {
            ++$this->csrfUnsetCount;
        }
    }

    public function resetCsrfOperationCounts(): void
    {
        $this->csrfSetCount   = 0;
        $this->csrfUnsetCount = 0;
    }
}
