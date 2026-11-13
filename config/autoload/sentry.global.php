<?php

declare(strict_types=1);

use Sirix\Mezzio\Authentication\Exception\AlreadyAuthenticatedException;
use Sirix\Mezzio\Authentication\Exception\AuthenticationException;

$dsn         = (string) \env('SENTRY_DSN', '', 'string');
$environment = (string) \env('SENTRY_ENVIRONMENT', '', 'string');
$release     = (string) \env('SENTRY_RELEASE', '', 'string');
$releaseFile = (string) \env('APP_RELEASE_FILE', 'data/build/release.json', 'string');
$releaseFile = '' !== $releaseFile ? $releaseFile : 'data/build/release.json';
$releaseFile = '/' !== $releaseFile[0]
    ? \dirname(__DIR__, 2) . '/' . \ltrim($releaseFile, '/')
    : $releaseFile;
$releaseFromFile = null;
$production      = (bool) \env('APP_PRODUCTION', false, 'bool');

if (\is_file($releaseFile)) {
    $contents        = \file_get_contents($releaseFile);
    $payload         = false !== $contents ? \json_decode($contents, true) : null;
    $releaseFromFile = \is_array($payload) && isset($payload['version']) && \is_string($payload['version'])
        ? $payload['version']
        : null;
}

return [
    'sentry'     => [
        'dsn'                => '' !== $dsn ? $dsn : null,
        'environment'        => '' !== $environment ? $environment : ($production ? 'production' : 'local'),
        'release'            => '' !== $release ? $release : $releaseFromFile,
        'sample_rate'        => \env('SENTRY_SAMPLE_RATE', 1.0, 'float'),
        'traces_sample_rate' => \env('SENTRY_TRACES_SAMPLE_RATE', 0.0, 'float'),
        'send_default_pii'   => false,
    ],

    'sentry_psr' => [
        'isolate_http_scope'              => true,
        'isolate_console_scope'           => true,
        'set_current_hub'                 => true,
        'default_integrations'            => false,
        'flush_on_http_error'             => \env('SENTRY_FLUSH_ON_HTTP_ERROR', false, 'bool'),
        'flush_on_console_terminate'      => true,
        'flush_timeout'                   => \env('SENTRY_FLUSH_TIMEOUT', 2, 'int'),
        'capture_http_request_context'    => true,
        'capture_console_input'           => \env('SENTRY_CAPTURE_CONSOLE_INPUT', false, 'bool'),
        'log_console_command_start'       => false,
        // HttpErrorLoggingDelegator already logs uncaught HTTP errors redacted; the vendor record is unredacted.
        'log_http_captured_exceptions'    => false,
        // Same for console: ConsoleErrorLoggingDelegator logs failed commands redacted.
        'log_console_captured_exceptions' => false,
        'exception_filter'                => [
            'enabled'                 => true,
            'ignore_classes'          => [
                AuthenticationException::class,
                AlreadyAuthenticatedException::class,
            ],
            'ignore_http_statuses'    => [],
            'ignore_codes'            => [],
            'ignore_message_patterns' => [],
            'inspect_previous'        => true,
        ],
        'redaction'                       => [
            'replacement'             => '[Filtered]',
            'sensitive_key_pattern'   => '/password|passwd|secret|token|api[_-]?key|authorization|cookie/i',
            'max_depth'               => 8,
            'max_items_per_container' => 100,
            'max_total_nodes'         => 5000,
            'use_default_rules'       => false,
            'rules'                   => [],
            'regex_rules'             => [],
        ],
        'http_context'                    => [
            'enabled'               => true,
            'capture_headers'       => false,
            'capture_query_string'  => false,
            'allowed_headers'       => [
                'User-Agent',
                'X-Request-Id',
            ],
            'request_id_headers'    => [
                'X-Request-Id',
                'X-Correlation-Id',
            ],
            'request_id_attributes' => [
                'request_id',
                'requestId',
                'correlation_id',
                'correlationId',
            ],
            'allowed_attributes'    => [
                'route',
                'route_name',
                'request_id',
                'correlation_id',
            ],
        ],
    ],
];
