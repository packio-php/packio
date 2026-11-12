<?php

declare(strict_types=1);

namespace Common\Runtime;

use const PHP_SESSION_ACTIVE;

use function session_id;
use function session_status;
use function session_write_close;

final class PhpSessionRuntimeResetter implements RuntimeResetterInterface
{
    public function reset(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        session_id('');
        $_SESSION = [];
    }
}
