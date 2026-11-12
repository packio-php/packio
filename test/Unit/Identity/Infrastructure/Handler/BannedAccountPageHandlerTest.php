<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Handler;

use Packio\Identity\Infrastructure\Handler\BannedAccountPageHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;

#[CoversClass(BannedAccountPageHandler::class)]
final class BannedAccountPageHandlerTest extends TestCase
{
    #[Test]
    public function routeRequiresAnAuthenticatedActor(): void
    {
        $reflectionClass = new ReflectionClass(BannedAccountPageHandler::class);

        self::assertCount(1, $reflectionClass->getAttributes(Authenticated::class));
    }
}
