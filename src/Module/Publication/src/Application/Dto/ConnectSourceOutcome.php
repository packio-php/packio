<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Dto;

final readonly class ConnectSourceOutcome
{
    public function __construct(public string $repository, public string $code, public string $message) {}
}
