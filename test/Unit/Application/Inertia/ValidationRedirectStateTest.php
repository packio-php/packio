<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Inertia;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\ValidationRedirectState;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationRedirectState::class)]
final class ValidationRedirectStateTest extends TestCase
{
    #[Test]
    public function itPreservesOnlyTheExplicitlyProjectedOldInput(): void
    {
        $validationRedirectState   = new ValidationRedirectState();
        $serverRequest             = new ServerRequest()->withAttribute(SessionInterface::class, new InMemorySession());

        $validationRedirectState->errors($serverRequest, [
            'email' => ['Invalid email.'],
        ]);
        $validationRedirectState->old($serverRequest, [
            'email' => 'user@example.com',
        ]);

        self::assertSame([
            'email' => ['Invalid email.'],
        ], $validationRedirectState->consumeErrors($serverRequest));
        self::assertSame([
            'email' => 'user@example.com',
        ], $validationRedirectState->consumeOld($serverRequest));
    }
}
