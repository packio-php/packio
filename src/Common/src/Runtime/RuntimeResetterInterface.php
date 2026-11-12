<?php

declare(strict_types=1);

namespace Common\Runtime;

interface RuntimeResetterInterface
{
    public function reset(): void;
}
