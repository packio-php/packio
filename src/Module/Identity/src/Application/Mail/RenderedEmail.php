<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Mail;

final readonly class RenderedEmail
{
    public function __construct(public string $subject, public string $html) {}
}
