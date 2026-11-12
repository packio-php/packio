<?php

declare(strict_types=1);

use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Sirix\Monolog\Enum\ConfigKey;
use Sirix\Monolog\Enum\FormatterType;
use Sirix\Monolog\Enum\HandlerType;
use Sirix\Monolog\Enum\ProcessorType;

$logDirectory        = \rtrim((string) \env('LOG_DIR', 'data/logs', 'string'), '/\\') ?: 'data/logs';
$level               = Level::fromName(\env('LOG_LEVEL', 'debug', 'string'));
$configuredDailyPath = \env('LOG_PATH', '', 'string');
$dailyPath           = '' !== $configuredDailyPath
    ? $configuredDailyPath
    : $logDirectory . DIRECTORY_SEPARATOR . 'app.log';
$dailyDays      = \env('LOG_DAILY_DAYS', 14, 'int');
$filePermission = \octdec((string) \env('LOG_FILE_PERMISSION', '0664', 'string'));
$channel        = \env('LOG_CHANNEL', 'daily', 'string');

return [
    ConfigKey::Root->value => [
        ConfigKey::LoggerServices->value => [
            Logger::class          => $channel,
            LoggerInterface::class => $channel,
            'logger'               => $channel,
        ],
        ConfigKey::Formatters->value     => [
            'json' => [
                ConfigKey::Type->value    => FormatterType::Json,
                ConfigKey::Options->value => [
                    'batch_mode'     => JsonFormatter::BATCH_MODE_JSON,
                    'append_newline' => true,
                ],
            ],
            'line' => [
                ConfigKey::Type->value    => FormatterType::Line,
                ConfigKey::Options->value => [],
            ],
        ],
        ConfigKey::Handlers->value       => [
            'daily'  => [
                ConfigKey::Type->value       => HandlerType::RotatingFile,
                ConfigKey::Formatter->value  => 'json',
                ConfigKey::Options->value    => [
                    'filename'        => $dailyPath,
                    'max_files'       => $dailyDays,
                    'level'           => $level,
                    'file_permission' => $filePermission,
                ],
                ConfigKey::Processors->value => [],
            ],
            'stderr' => [
                ConfigKey::Type->value       => HandlerType::Stream,
                ConfigKey::Formatter->value  => 'json',
                ConfigKey::Options->value    => [
                    'stream' => 'php://stderr',
                    'level'  => $level,
                ],
                ConfigKey::Processors->value => [],
            ],
            'stdout' => [
                ConfigKey::Type->value       => HandlerType::Stream,
                ConfigKey::Formatter->value  => 'json',
                ConfigKey::Options->value    => [
                    'stream' => 'php://stdout',
                    'level'  => $level,
                ],
                ConfigKey::Processors->value => [],
            ],
        ],
        ConfigKey::Processors->value     => [
            'uid'           => [
                ConfigKey::Type->value    => ProcessorType::Uid,
                ConfigKey::Options->value => [
                    'length' => 32,
                ],
            ],
            'introspection' => [
                ConfigKey::Type->value    => ProcessorType::Introspection,
                ConfigKey::Options->value => [
                    'level'                   => Level::Error,
                    'skip_classes_partials'   => [],
                    'skip_stack_frames_count' => 0,
                ],
            ],
            'memoryUsage'   => [
                ConfigKey::Type->value    => ProcessorType::MemoryUsage,
                ConfigKey::Options->value => [],
            ],
            'memoryPeak'    => [
                ConfigKey::Type->value    => ProcessorType::MemoryPeakUsage,
                ConfigKey::Options->value => [],
            ],
        ],
        ConfigKey::Channels->value       => [
            'daily'  => [
                ConfigKey::Name->value       => 'PackioApp',
                ConfigKey::Handlers->value   => ['daily'],
                ConfigKey::Processors->value => [
                    'uid',
                    'introspection',
                    'memoryUsage',
                    'memoryPeak',
                ],
            ],
            'stderr' => [
                ConfigKey::Name->value       => 'PackioApp',
                ConfigKey::Handlers->value   => ['stderr'],
                ConfigKey::Processors->value => [
                    'uid',
                    'introspection',
                    'memoryUsage',
                    'memoryPeak',
                ],
            ],
            'stdout' => [
                ConfigKey::Name->value       => 'PackioApp',
                ConfigKey::Handlers->value   => ['stdout'],
                ConfigKey::Processors->value => [
                    'uid',
                    'introspection',
                    'memoryUsage',
                    'memoryPeak',
                ],
            ],
        ],
    ],
];
