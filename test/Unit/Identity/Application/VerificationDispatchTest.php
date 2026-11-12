<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Application;

use Packio\Identity\Application\Command\VerificationDispatch;
use Packio\Identity\Domain\EmailVerificationReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(VerificationDispatch::class)]
final class VerificationDispatchTest extends TestCase
{
    #[Test]
    public function dispatchFailureIsLoggedOnceAndNotRethrown(): void
    {
        $events = $this->createStub(EventDispatcherInterface::class);
        $events->method('dispatch')->willThrowException(new RuntimeException('Mail unavailable'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Verification email dispatch failed.', self::isArray());

        new VerificationDispatch($events, $logger)->request('018f25e5-fd27-7000-8000-000000000001', EmailVerificationReason::Login);
    }
}
