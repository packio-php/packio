<?php

declare(strict_types=1);

namespace Common\Mail;

interface MailerInterface
{
    public function send(string $to, string $subject, string $html): void;
}
