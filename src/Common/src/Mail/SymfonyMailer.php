<?php

declare(strict_types=1);

namespace Common\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface as SymfonyMailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

final readonly class SymfonyMailer implements MailerInterface
{
    private const string DEFAULT_FROM = 'noreply@packio.us';

    /**
     * @param array<string, mixed> $mailConfig
     */
    public function __construct(
        private SymfonyMailerInterface $symfonyMailer,
        private array $mailConfig,
        private ?LoggerInterface $logger = null
    ) {}

    public function send(string $to, string $subject, string $html): void
    {
        $from = (string) ($this->mailConfig['from'] ?? self::DEFAULT_FROM);

        $email = new Email()
            ->from($from)
            ->to($to)
            ->subject($subject)
            ->html($html)
        ;

        try {
            $this->symfonyMailer->send($email);
        } catch (Throwable $e) {
            $this->logger?->error('Failed to send email', [
                'exception_class' => $e::class,
            ]);

            throw $e;
        }
    }
}
