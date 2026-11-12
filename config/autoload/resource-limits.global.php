<?php

declare(strict_types=1);

return [
    'resource_limits' => [
        'webhook_bytes'       => \env('WEBHOOK_MAX_BYTES', 1_048_576, 'int'),
        'request_bytes'       => \env('REQUEST_MAX_BYTES', 33_554_432, 'int'),
        'upstream_bytes'      => \env('UPSTREAM_MAX_BYTES', 16_777_216, 'int'),
        'git_memory_bytes'    => \env('GIT_MEMORY_MAX_BYTES', 536_870_912, 'int'),
        'git_seconds'         => \env('GIT_TIMEOUT_SECONDS', 120, 'int'),
        'git_workspace_bytes' => \env('GIT_WORKSPACE_MAX_BYTES', 1_073_741_824, 'int'),
    ],
];
