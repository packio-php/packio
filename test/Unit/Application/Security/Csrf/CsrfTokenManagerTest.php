<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security\Csrf;

use Packio\Application\Security\Csrf\CsrfTokenContract;
use Packio\Application\Security\Csrf\CsrfTokenManager;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function strlen;

#[CoversClass(CsrfTokenManager::class)]
final class CsrfTokenManagerTest extends TestCase
{
    #[Test]
    public function itGeneratesAndReusesASecureSessionToken(): void
    {
        $inMemorySession           = new InMemorySession();
        $csrfTokenManager          = new CsrfTokenManager();
        $firstToken                = $csrfTokenManager->getOrCreate($inMemorySession);
        $secondToken               = $csrfTokenManager->getOrCreate($inMemorySession);

        self::assertSame(CsrfTokenContract::TOKEN_LENGTH, strlen($firstToken));
        self::assertMatchesRegularExpression('/^[a-f0-9]+$/', $firstToken);
        self::assertSame($firstToken, $secondToken);
        self::assertSame($firstToken, $inMemorySession->get(CsrfTokenContract::SESSION_KEY));
    }

    #[Test]
    public function itValidatesTheStoredTokenWithoutConsumingIt(): void
    {
        $inMemorySession          = new InMemorySession();
        $csrfTokenManager         = new CsrfTokenManager();
        $token                    = $csrfTokenManager->getOrCreate($inMemorySession);

        self::assertTrue($csrfTokenManager->validate($inMemorySession, $token));
        self::assertTrue($csrfTokenManager->validate($inMemorySession, $token));
        self::assertSame($token, $inMemorySession->get(CsrfTokenContract::SESSION_KEY));
    }

    #[Test]
    public function itRejectsMissingMalformedAndMismatchedTokens(): void
    {
        $inMemorySession          = new InMemorySession();
        $csrfTokenManager         = new CsrfTokenManager();
        $token                    = $csrfTokenManager->getOrCreate($inMemorySession);

        self::assertFalse($csrfTokenManager->validate($inMemorySession, null));
        self::assertFalse($csrfTokenManager->validate($inMemorySession, [
            'token' => $token,
        ]));
        self::assertFalse($csrfTokenManager->validate($inMemorySession, 123));
        self::assertFalse($csrfTokenManager->validate($inMemorySession, 'short'));
        self::assertFalse($csrfTokenManager->validate($inMemorySession, str_repeat('a', CsrfTokenContract::TOKEN_LENGTH - 1)));
        self::assertFalse($csrfTokenManager->validate($inMemorySession, str_repeat('a', CsrfTokenContract::TOKEN_LENGTH + 1)));
        self::assertFalse($csrfTokenManager->validate($inMemorySession, str_repeat('z', CsrfTokenContract::TOKEN_LENGTH)));
        self::assertFalse($csrfTokenManager->validate($inMemorySession, str_repeat('b', CsrfTokenContract::TOKEN_LENGTH)));
    }

    #[Test]
    public function itRejectsAnInvalidStoredSessionValue(): void
    {
        $inMemorySession = new InMemorySession();
        $inMemorySession->set(CsrfTokenContract::SESSION_KEY, ['invalid']);

        self::assertFalse(new CsrfTokenManager()->validate($inMemorySession, str_repeat('a', CsrfTokenContract::TOKEN_LENGTH)));
    }

    #[Test]
    public function itRotatesTheSessionToken(): void
    {
        $inMemorySession          = new InMemorySession();
        $csrfTokenManager         = new CsrfTokenManager();
        $oldToken                 = $csrfTokenManager->getOrCreate($inMemorySession);
        $newToken                 = $csrfTokenManager->rotate($inMemorySession);

        self::assertNotSame($oldToken, $newToken);
        self::assertFalse($csrfTokenManager->validate($inMemorySession, $oldToken));
        self::assertTrue($csrfTokenManager->validate($inMemorySession, $newToken));
    }

    #[Test]
    public function itClearsOnlyTheCsrfToken(): void
    {
        $inMemorySession          = new InMemorySession();
        $csrfTokenManager         = new CsrfTokenManager();
        $csrfTokenManager->getOrCreate($inMemorySession);
        $inMemorySession->set('other', 'preserved');

        $csrfTokenManager->clear($inMemorySession);

        self::assertFalse($inMemorySession->has(CsrfTokenContract::SESSION_KEY));
        self::assertSame('preserved', $inMemorySession->get('other'));
    }

    #[Test]
    public function itKeepsTokensIsolatedBetweenSessions(): void
    {
        $csrfTokenManager = new CsrfTokenManager();
        $firstSession     = new InMemorySession();
        $secondSession    = new InMemorySession();
        $firstToken       = $csrfTokenManager->getOrCreate($firstSession);
        $secondToken      = $csrfTokenManager->getOrCreate($secondSession);

        self::assertNotSame($firstToken, $secondToken);
        self::assertFalse($csrfTokenManager->validate($firstSession, $secondToken));
        self::assertFalse($csrfTokenManager->validate($secondSession, $firstToken));
    }
}
