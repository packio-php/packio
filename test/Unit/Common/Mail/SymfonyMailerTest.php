<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Mail;

use Common\Mail\SymfonyMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mailer\MailerInterface;

final class SymfonyMailerTest extends TestCase
{
    public function testTransportSecretsAndRecipientAreNotLoggedOnFailure(): void
    {
        $transport = $this->createMock(MailerInterface::class);
        $transport->expects(self::once())->method('send')->willThrowException(new RuntimeException('smtp-password reset-token user@example.test'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Failed to send email', [
            'exception_class' => RuntimeException::class,
        ]);
        $this->expectException(RuntimeException::class);
        new SymfonyMailer($transport, [], $logger)->send('user@example.test', 'Private subject', 'reset-token');
    }
}
