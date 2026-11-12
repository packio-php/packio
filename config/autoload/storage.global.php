<?php

declare(strict_types=1);

return [
    'storage' => [
        'archive' => [
            'driver'                            => \env('PACKAGE_ARCHIVE_STORAGE_DRIVER', 'local'),
            'max_archive_bytes'                 => \env('PACKAGE_ARCHIVE_MAX_BYTES', 52_428_800, 'int'),
            'fallback_to_source_on_unavailable' => \env('PACKAGE_ARCHIVE_FALLBACK_TO_SOURCE_ON_UNAVAILABLE', false, 'bool'),
            'local'                             => [
                'path'   => \env('PACKAGE_ARCHIVE_LOCAL_PATH', 'data/package-archives'),
                'prefix' => \env('PACKAGE_ARCHIVE_LOCAL_PREFIX', 'private'),
            ],
            's3'                                => [
                'bucket'                    => \env('PACKAGE_ARCHIVE_S3_BUCKET', ''),
                'region'                    => \env('PACKAGE_ARCHIVE_S3_REGION', 'fsn1'),
                'endpoint'                  => \env('PACKAGE_ARCHIVE_S3_ENDPOINT', ''),
                'access_key'                => \env('PACKAGE_ARCHIVE_S3_ACCESS_KEY', ''),
                'secret_key'                => \env('PACKAGE_ARCHIVE_S3_SECRET_KEY', ''),
                'path_style_endpoint'       => \env('PACKAGE_ARCHIVE_S3_PATH_STYLE_ENDPOINT', false, 'bool'),
                'use_ssl'                   => \env('PACKAGE_ARCHIVE_S3_USE_SSL', true, 'bool'),
                'prefix'                    => \env('PACKAGE_ARCHIVE_S3_PREFIX', 'private'),
                'multipart_threshold_bytes' => \env('PACKAGE_ARCHIVE_S3_MULTIPART_THRESHOLD_BYTES', 16_777_216, 'int'),
                'temporary_url_ttl_seconds' => \env('PACKAGE_ARCHIVE_S3_TEMPORARY_URL_TTL_SECONDS', 300, 'int'),
            ],
        ],
    ],
];
