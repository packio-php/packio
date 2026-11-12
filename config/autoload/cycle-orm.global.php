<?php

declare(strict_types=1);

use Cycle\Database\Config\Postgres\TcpConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Packio\Admin\Infrastructure\Persistence\Cycle\Schema\ProjectSettingEntitySchema;
use Packio\Audit\Infrastructure\Persistence\Cycle\Schema\AuditEntryEntitySchema;
use Packio\Identity\Infrastructure\Persistence\Cycle\Schema\ApiAccessTokenEntitySchema;
use Packio\Identity\Infrastructure\Persistence\Cycle\Schema\EmailVerificationCodeEntitySchema;
use Packio\Identity\Infrastructure\Persistence\Cycle\Schema\PasswordResetTokenEntitySchema;
use Packio\Identity\Infrastructure\Persistence\Cycle\Schema\RegistryAccessTokenEntitySchema;
use Packio\Identity\Infrastructure\Persistence\Cycle\Schema\UserEntitySchema;
use Packio\Identity\Infrastructure\Persistence\Cycle\Schema\UserOAuthAccountEntitySchema;
use Packio\Organization\Infrastructure\Persistence\Cycle\Schema\OrganizationEntitySchema;
use Packio\Organization\Infrastructure\Persistence\Cycle\Schema\OrganizationInvitationEntitySchema;
use Packio\Organization\Infrastructure\Persistence\Cycle\Schema\OrganizationMemberEntitySchema;
use Packio\Package\Infrastructure\Persistence\Cycle\Schema\PackageEntitySchema;
use Packio\Release\Infrastructure\Persistence\Cycle\Schema\ReleaseEntitySchema;
use Packio\Source\Infrastructure\Persistence\Cycle\Schema\ProviderAuthorizationEntitySchema;
use Packio\Source\Infrastructure\Persistence\Cycle\Schema\SourceEntitySchema;
use Packio\Source\Infrastructure\Persistence\Cycle\Schema\SourceOAuthAccountEntitySchema;
use Packio\Source\Infrastructure\Persistence\Cycle\Schema\SourceProviderInstanceEntitySchema;
use Packio\Webhook\Infrastructure\Persistence\Cycle\Schema\WebhookEventEntitySchema;
use Packio\Worker\Infrastructure\Persistence\Cycle\Schema\JobEntitySchema;
use Packio\Worker\Infrastructure\Persistence\Cycle\Schema\JobLogEntitySchema;

$defaultDbConnection = \env('DEFAULT_DB_CONNECTION', 'postgres');

if ('postgres' !== $defaultDbConnection) {
    throw new RuntimeException('Packio supports PostgreSQL only for this release.');
}

return [
    'cycle' => [
        'db-config'  => [
            'databases'   => [
                'default'               => [
                    'connection' => $defaultDbConnection,
                ],
                'source_deletion_lease' => [
                    'connection' => 'postgres_source_deletion_lease',
                ],
            ],
            'connections' => [
                'postgres'                       => new PostgresDriverConfig(
                    connection: new TcpConnectionConfig(
                        database: \env('DATABASE_NAME', 'packio'),
                        host: \env('DATABASE_HOST', 'localhost'),
                        port: \env('DATABASE_PORT', 15432, 'int'),
                        user: \env('DATABASE_USER', 'postgresql'),
                        password: \env('DATABASE_PASS', 'password'),
                    ),
                    reconnect: true,
                    timezone: 'UTC',
                    queryCache: true,
                ),
                'postgres_source_deletion_lease' => new PostgresDriverConfig(
                    connection: new TcpConnectionConfig(
                        database: \env('DATABASE_NAME', 'packio'),
                        host: \env('DATABASE_HOST', 'localhost'),
                        port: \env('DATABASE_PORT', 15432, 'int'),
                        user: \env('DATABASE_USER', 'postgresql'),
                        password: \env('DATABASE_PASS', 'password'),
                    ),
                    reconnect: false,
                    timezone: 'UTC',
                    queryCache: false,
                ),
            ],
        ],
        'migrator'   => [
            'directory'      => 'data/db/cycle/migrations',
            'table'          => 'migrations',
            'seed_directory' => 'data/db/cycle/seeds',
        ],
        'entities'   => [],
        'generators' => [
            AuditEntryEntitySchema::class,
            ApiAccessTokenEntitySchema::class,
            EmailVerificationCodeEntitySchema::class,
            JobEntitySchema::class,
            JobLogEntitySchema::class,
            OrganizationEntitySchema::class,
            OrganizationInvitationEntitySchema::class,
            OrganizationMemberEntitySchema::class,
            PackageEntitySchema::class,
            PasswordResetTokenEntitySchema::class,
            ProjectSettingEntitySchema::class,
            ProviderAuthorizationEntitySchema::class,
            RegistryAccessTokenEntitySchema::class,
            ReleaseEntitySchema::class,
            SourceEntitySchema::class,
            SourceOAuthAccountEntitySchema::class,
            SourceProviderInstanceEntitySchema::class,
            UserEntitySchema::class,
            UserOAuthAccountEntitySchema::class,
            WebhookEventEntitySchema::class,
        ],
        'schema'     => [
            'compiled' => [
                'path' => 'data/cache/cycle/schema.php',
            ],
            'cache'    => [
                'enabled' => true,
            ],
        ],
    ],
];
