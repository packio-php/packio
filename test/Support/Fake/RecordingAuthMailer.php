<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Common\Mail\MailerInterface;
use RuntimeException;

final class RecordingAuthMailer implements MailerInterface
{
    /** @var list<array{to: string, subject: string, html: string}> */
    public array $messages = [];
    public bool $fail      = false;

    public function send(string $to, string $subject, string $html): void
    {
        if ($this->fail) {
            throw new RuntimeException('SMTP secret-token https://smtp-user:private-password@mail.example.test');
        }
        $this->messages[] = [
            'to'      => $to,
            'subject' => $subject,
            'html'    => $html,
        ];
    }
}
