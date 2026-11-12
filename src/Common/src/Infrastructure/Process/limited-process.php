<?php

// Dedicated subprocess: limits must never be applied to the long-running PHP worker.
declare(strict_types=1);

if (! isset($argv[1], $argv[2], $argv[3], $argv[4])) {
    exit(125);
}

if (! \function_exists('posix_setsid') || ! \function_exists('posix_setrlimit') || ! \function_exists('pcntl_exec')) {
    \fwrite(STDERR, 'Git operations require PHP POSIX and PCNTL extensions.');

    exit(125);
}

if (-1 === \posix_setsid()
    || ! \posix_setrlimit(POSIX_RLIMIT_CORE, 0, 0)
    || ! \posix_setrlimit(POSIX_RLIMIT_FSIZE, (int) $argv[1], (int) $argv[1])
    || ! \posix_setrlimit(POSIX_RLIMIT_AS, (int) $argv[3], (int) $argv[3])
    || ! \posix_setrlimit(POSIX_RLIMIT_CPU, (int) $argv[2], (int) $argv[2])) {
    \fwrite(STDERR, 'Failed to establish subprocess resource limits.');

    exit(125);
}

// proc_open omits empty environment values. Restore them before executing Git.
foreach (\array_slice($argv, 5) as $name) {
    \putenv($name . '=');
}

// argv[4] is assembled from trusted code and escaped arguments by the caller.
\pcntl_exec('/bin/sh', ['-c', 'exec ' . $argv[4]]);
\fwrite(STDERR, 'Failed to execute subprocess.');

exit(125);
