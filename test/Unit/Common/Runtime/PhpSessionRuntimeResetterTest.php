<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Runtime;

use const PHP_SESSION_ACTIVE;

use Common\Runtime\PhpSessionRuntimeResetter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function session_destroy;
use function session_id;
use function session_start;
use function session_status;
use function session_write_close;

#[CoversClass(PhpSessionRuntimeResetter::class)]
final class PhpSessionRuntimeResetterTest extends TestCase
{
    #[Test]
    #[RunInSeparateProcess]
    public function itClosesAnActiveSessionAndClearsItsRuntimeStateBeforeTheNextRequest(): void
    {
        $sessionId = 'active-runtime-session';
        session_id($sessionId);
        session_start();
        $_SESSION = [
            '__csrf' => 'previous-request-token',
        ];

        try {
            self::assertSame(PHP_SESSION_ACTIVE, session_status());

            new PhpSessionRuntimeResetter()->reset();

            self::assertSame('', session_id());
            self::assertSame([], $_SESSION);
            self::assertNotSame(PHP_SESSION_ACTIVE, session_status());
        } finally {
            session_id($sessionId);
            session_start();
            session_destroy();
            session_write_close();
            session_id('');
            $_SESSION = [];
        }
    }
}
