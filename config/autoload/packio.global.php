<?php

declare(strict_types=1);

use Packio\Organization\Application\Config\ReservedOrganizationSlugs;
use Packio\Source\Domain\ValueObject\SourceProvider;

$rateLimitRetention = \env('RATE_LIMIT_RETENTION_SECONDS', null, 'string');
if (null !== $rateLimitRetention) {
    $rateLimitRetention = \filter_var($rateLimitRetention, FILTER_VALIDATE_INT);
    if (false === $rateLimitRetention || $rateLimitRetention < 1) {
        throw new InvalidArgumentException('RATE_LIMIT_RETENTION_SECONDS must be a positive integer.');
    }
}

$productionMode = \env('APP_PRODUCTION', false, 'bool');
$trustedProxies = \env('TRUSTED_PROXIES', '');
if ($productionMode && \in_array('*', \array_map(\trim(...), \explode(',', (string) $trustedProxies)), true)) {
    throw new RuntimeException('TRUSTED_PROXIES wildcard is not allowed in production; configure explicit proxy IPs/CIDRs.');
}

$sourceOAuthRedirectUrl = static fn (string $provider): string => \rtrim(
    (string) \env('APP_URL', 'https://packio.us'),
    '/',
) . '/auth/oauth/' . $provider . '/source-callback';

return [
    'packio'        => [
        'name'            => \env('APP_NAME', \env('BRAND_PRODUCT_NAME', 'Packio')),
        'debug'           => \env('APP_DEBUG', true, 'bool'),
        'timezone'        => \env('APP_TIMEZONE', 'UTC'),
        'production_mode' => $productionMode,
        'release'         => [
            'file' => \env('APP_RELEASE_FILE', 'data/build/release.json'),
        ],
    ],

    'branding'      => [
        'product_name'              => \env('BRAND_PRODUCT_NAME', 'Packio'),
        'tagline'                   => \env('BRAND_TAGLINE', 'a lightweight private package registry for Composer'),
        'domain'                    => \env('BRAND_DOMAIN', 'packio.us'),
        'registry_base_domain'      => \env('BRAND_REGISTRY_BASE_DOMAIN', \env('BRAND_DOMAIN', 'packio.us')),
        'short_name'                => \env('BRAND_SHORT_NAME', 'packio'),
        'composer_repository_alias' => \env('BRAND_COMPOSER_REPOSITORY_ALIAS', 'packio'),
        'registry_token_prefix'     => \env('BRAND_REGISTRY_TOKEN_PREFIX', 'packio_rt_'),
        'registry_auth_realm'       => \env('BRAND_REGISTRY_AUTH_REALM', 'Packio'),
        'http_user_agent'           => \env('BRAND_HTTP_USER_AGENT', 'Packio/0.1'),
        'theme_storage_key'         => \env('BRAND_THEME_STORAGE_KEY', 'packio-theme'),
        'app_url'                   => \env('APP_URL', 'https://packio.us'),
    ],

    'mail'          => [
        'dsn'  => (static function(): string {
            $dsn = \trim((string) \env('MAIL_DSN', ''));
            if ('' !== $dsn) {
                return $dsn;
            }
            $scheme   = \trim((string) \env('MAIL_SCHEME', 'smtp'));
            $host     = \trim((string) \env('MAIL_HOST', 'localhost'));
            $port     = (int) \env('MAIL_PORT', 1025, 'int');
            $username = \trim((string) \env('MAIL_USERNAME', ''));
            $password = (string) \env('MAIL_PASSWORD', '');

            $authority = '' !== $username
                ? \rawurlencode($username)
                . ('' !== $password ? ':' . \rawurlencode($password) : '')
                . '@' . $host
                : $host;

            return \sprintf('%s://%s:%d', $scheme, $authority, $port);
        })(),
        'from' => (static function(): string {
            $fromAddress = \trim((string) \env('MAIL_FROM', ''));
            if ('' === $fromAddress) {
                $fromAddress = \trim((string) \env('MAIL_FROM_ADDRESS', 'noreply@packio.dev'));
            }
            $fromName = \trim((string) \env('MAIL_FROM_NAME', ''));

            return '' !== $fromName
                ? \sprintf('%s <%s>', $fromName, $fromAddress)
                : $fromAddress;
        })(),
    ],

    'turnstile'     => [
        'enabled'           => \env('TURNSTILE_ENABLED', false, 'bool'),
        'site_key'          => \env('TURNSTILE_SITE_KEY', ''),
        'secret_key'        => \env('TURNSTILE_SECRET_KEY', ''),
        'verify_url'        => \env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'hostname'          => \env('TURNSTILE_HOSTNAME', ''),
        'validate_hostname' => \env('TURNSTILE_VALIDATE_HOSTNAME', true, 'bool'),
    ],

    'sources'       => [
        'providers' => [
            SourceProvider::Github->value           => [
                'enabled' => \env(SourceProvider::Github->enabledEnvName(), true, 'bool'),
            ],
            SourceProvider::Gitlab->value           => [
                'enabled' => \env(SourceProvider::Gitlab->enabledEnvName(), true, 'bool'),
            ],
            SourceProvider::GitlabSelfHosted->value => [
                'enabled' => \env(SourceProvider::GitlabSelfHosted->enabledEnvName(), false, 'bool'),
            ],
            SourceProvider::GenericGit->value       => [
                'enabled' => \env(SourceProvider::GenericGit->enabledEnvName(), true, 'bool'),
            ],
            SourceProvider::Bitbucket->value        => [
                'enabled' => \env(SourceProvider::Bitbucket->enabledEnvName(), true, 'bool'),
            ],
            SourceProvider::Codeberg->value         => [
                'enabled' => \env(SourceProvider::Codeberg->enabledEnvName(), true, 'bool'),
            ],
        ],
        'webhooks'  => [
            'github'    => [
                'signature_header' => \env('SOURCE_WEBHOOKS_GITHUB_SIGNATURE_HEADER', 'X-Hub-Signature-256'),
                'events'           => \env('SOURCE_WEBHOOKS_GITHUB_EVENTS', ['push', 'create', 'release'], 'array'),
            ],
            'gitlab'    => [
                'events' => \env('SOURCE_WEBHOOKS_GITLAB_EVENTS', ['Push Hook', 'Tag Push Hook'], 'array'),
            ],
            'bitbucket' => [
                'events' => \env('SOURCE_WEBHOOKS_BITBUCKET_EVENTS', ['repo:push'], 'array'),
            ],
            'codeberg'  => [
                'events' => \env('SOURCE_WEBHOOKS_CODEBERG_EVENTS', ['push', 'create', 'delete'], 'array'),
            ],
        ],
    ],

    'identity'      => [
        'registration_open' => \env('IDENTITY_REGISTRATION_OPEN', true, 'bool'),
    ],

    'organizations' => [
        'reserved_slugs' => \env('ORGANIZATION_RESERVED_SLUGS', ReservedOrganizationSlugs::DEFAULT_SLUGS, 'array'),
    ],

    'oauth'         => [
        'github'    => [
            'enabled'           => \env('OAUTH_GITHUB_ENABLED', false, 'bool'),
            'client_id'         => \env('OAUTH_GITHUB_CLIENT_ID', ''),
            'client_secret'     => \env('OAUTH_GITHUB_CLIENT_SECRET', ''),
            'redirect_url'      => \env('OAUTH_GITHUB_REDIRECT_URL', ''),
            'scopes'            => \env('OAUTH_GITHUB_SCOPES', ['read:user', 'user:email'], 'array'),
            'source'            => [
                'enabled'              => \env('OAUTH_GITHUB_SOURCE_ENABLED', false, 'bool'),
                'client_id'            => \env('OAUTH_GITHUB_SOURCE_CLIENT_ID', ''),
                'client_secret'        => \env('OAUTH_GITHUB_SOURCE_CLIENT_SECRET', ''),
                'redirect_url'         => \env('OAUTH_GITHUB_SOURCE_REDIRECT_URL', ''),
                'default_redirect_url' => $sourceOAuthRedirectUrl('github'),
                'scopes'               => \env('OAUTH_GITHUB_SOURCE_SCOPES', ['read:user', 'user:email', 'repo', 'read:org'], 'array'),
            ],
            'authorization_url' => \env('OAUTH_GITHUB_AUTHORIZATION_URL', 'https://github.com/login/oauth/authorize'),
            'token_url'         => \env('OAUTH_GITHUB_TOKEN_URL', 'https://github.com/login/oauth/access_token'),
            'user_url'          => \env('OAUTH_GITHUB_USER_URL', 'https://api.github.com/user'),
        ],
        'gitlab'    => [
            'enabled'           => \env('OAUTH_GITLAB_ENABLED', false, 'bool'),
            'client_id'         => \env('OAUTH_GITLAB_CLIENT_ID', ''),
            'client_secret'     => \env('OAUTH_GITLAB_CLIENT_SECRET', ''),
            'redirect_url'      => \env('OAUTH_GITLAB_REDIRECT_URL', ''),
            'scopes'            => \env('OAUTH_GITLAB_SCOPES', ['read_user', 'openid', 'profile'], 'array'),
            'source'            => [
                'enabled'              => \env('OAUTH_GITLAB_SOURCE_ENABLED', false, 'bool'),
                'client_id'            => \env('OAUTH_GITLAB_SOURCE_CLIENT_ID', ''),
                'client_secret'        => \env('OAUTH_GITLAB_SOURCE_CLIENT_SECRET', ''),
                'redirect_url'         => \env('OAUTH_GITLAB_SOURCE_REDIRECT_URL', ''),
                'default_redirect_url' => $sourceOAuthRedirectUrl('gitlab'),
                'scopes'               => \env('OAUTH_GITLAB_SOURCE_SCOPES', ['read_user', 'api', 'read_repository'], 'array'),
            ],
            'authorization_url' => \env('OAUTH_GITLAB_AUTHORIZATION_URL', ''),
            'token_url'         => \env('OAUTH_GITLAB_TOKEN_URL', ''),
            'user_url'          => \env('OAUTH_GITLAB_USER_URL', ''),
            'base_url'          => \env('OAUTH_GITLAB_BASE_URL', 'https://gitlab.com'),
            'self_hosted'       => [
                'source' => [
                    'enabled' => \env('OAUTH_GITLAB_SELF_HOSTED_SOURCE_ENABLED', false, 'bool'),
                ],
            ],
        ],
        'bitbucket' => [
            'enabled'           => \env('OAUTH_BITBUCKET_ENABLED', false, 'bool'),
            'client_id'         => \env('OAUTH_BITBUCKET_CLIENT_ID', ''),
            'client_secret'     => \env('OAUTH_BITBUCKET_CLIENT_SECRET', ''),
            'redirect_url'      => \env('OAUTH_BITBUCKET_REDIRECT_URL', ''),
            'scopes'            => \env('OAUTH_BITBUCKET_SCOPES', ['account', 'email'], 'array'),
            'source'            => [
                'enabled'              => \env('OAUTH_BITBUCKET_SOURCE_ENABLED', false, 'bool'),
                'client_id'            => \env('OAUTH_BITBUCKET_SOURCE_CLIENT_ID', ''),
                'client_secret'        => \env('OAUTH_BITBUCKET_SOURCE_CLIENT_SECRET', ''),
                'redirect_url'         => \env('OAUTH_BITBUCKET_SOURCE_REDIRECT_URL', ''),
                'default_redirect_url' => $sourceOAuthRedirectUrl('bitbucket'),
                'scopes'               => \env('OAUTH_BITBUCKET_SOURCE_SCOPES', ['account', 'email', 'repository', 'webhook'], 'array'),
            ],
            'authorization_url' => \env('OAUTH_BITBUCKET_AUTHORIZATION_URL', 'https://bitbucket.org/site/oauth2/authorize'),
            'token_url'         => \env('OAUTH_BITBUCKET_TOKEN_URL', 'https://bitbucket.org/site/oauth2/access_token'),
            'user_url'          => \env('OAUTH_BITBUCKET_USER_URL', 'https://api.bitbucket.org/2.0/user'),
            'emails_url'        => \env('OAUTH_BITBUCKET_EMAILS_URL', 'https://api.bitbucket.org/2.0/user/emails'),
        ],
        'codeberg'  => [
            'enabled'           => \env('OAUTH_CODEBERG_ENABLED', false, 'bool'),
            'client_id'         => \env('OAUTH_CODEBERG_CLIENT_ID', ''),
            'client_secret'     => \env('OAUTH_CODEBERG_CLIENT_SECRET', ''),
            'redirect_url'      => \env('OAUTH_CODEBERG_REDIRECT_URL', ''),
            'scopes'            => \env('OAUTH_CODEBERG_SCOPES', [], 'array'),
            'source'            => [
                'enabled'              => \env('OAUTH_CODEBERG_SOURCE_ENABLED', false, 'bool'),
                'client_id'            => \env('OAUTH_CODEBERG_SOURCE_CLIENT_ID', ''),
                'client_secret'        => \env('OAUTH_CODEBERG_SOURCE_CLIENT_SECRET', ''),
                'redirect_url'         => \env('OAUTH_CODEBERG_SOURCE_REDIRECT_URL', ''),
                'default_redirect_url' => $sourceOAuthRedirectUrl('codeberg'),
                'scopes'               => \env('OAUTH_CODEBERG_SOURCE_SCOPES', [], 'array'),
            ],
            'authorization_url' => \env('OAUTH_CODEBERG_AUTHORIZATION_URL', ''),
            'token_url'         => \env('OAUTH_CODEBERG_TOKEN_URL', ''),
            'user_url'          => \env('OAUTH_CODEBERG_USER_URL', ''),
            'base_url'          => \env('OAUTH_CODEBERG_BASE_URL', 'https://codeberg.org'),
        ],
    ],

    'security'      => [
        'secret_encryption_key' => \env('APP_SECRET_ENCRYPTION_KEY', ''),

        'password_reset'        => [
            'token_ttl_seconds' => \env('PASSWORD_RESET_TOKEN_TTL_SECONDS', 3600, 'int'),
        ],

        'client_ip'             => [
            'trusted_proxies' => $trustedProxies,
            'header'          => \env('TRUSTED_PROXY_CLIENT_IP_HEADER', 'X-Forwarded-For'),
        ],

        'rate_limits'           => [
            // Unset retention is derived after local config and route overrides.
            'retention_seconds'         => $rateLimitRetention,
            'storage_dir'               => \env('APP_RATE_LIMIT_DIR', 'data/rate-limits'),

            'login'                     => [
                'max_attempts'  => \env('RATE_LIMIT_LOGIN_MAX', 5, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_LOGIN_DECAY', 900, 'int'),
            ],
            'register'                  => [
                'max_attempts'  => \env('RATE_LIMIT_REGISTER_MAX', 5, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_REGISTER_DECAY', 3600, 'int'),
            ],
            'webhook'                   => [
                'max_attempts'  => \env('RATE_LIMIT_WEBHOOK_MAX', 60, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_WEBHOOK_DECAY', 60, 'int'),
            ],
            'registry_auth'             => [
                'max_attempts'  => \env('RATE_LIMIT_REGISTRY_AUTH_MAX', 30, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_REGISTRY_AUTH_DECAY', 60, 'int'),
            ],
            'api_authentication'        => [
                'max_attempts'  => \env('RATE_LIMIT_API_AUTHENTICATION_MAX', 60, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_API_AUTHENTICATION_DECAY', 60, 'int'),
            ],
            'api_token_create'          => [
                'max_attempts'  => \env('RATE_LIMIT_API_TOKEN_CREATE_MAX', 10, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_API_TOKEN_CREATE_DECAY', 3600, 'int'),
            ],
            'api_write'                 => [
                'max_attempts'  => \env('RATE_LIMIT_API_WRITE_MAX', 120, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_API_WRITE_DECAY', 60, 'int'),
            ],
            'api_destructive'           => [
                'max_attempts'  => \env('RATE_LIMIT_API_DESTRUCTIVE_MAX', 30, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_API_DESTRUCTIVE_DECAY', 60, 'int'),
            ],
            'api_queue_action'          => [
                'max_attempts'  => \env('RATE_LIMIT_API_QUEUE_ACTION_MAX', 30, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_API_QUEUE_ACTION_DECAY', 60, 'int'),
            ],
            'api_admin_write'           => [
                'max_attempts'  => \env('RATE_LIMIT_API_ADMIN_WRITE_MAX', 60, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_API_ADMIN_WRITE_DECAY', 60, 'int'),
            ],
            'email_verification_send'   => [
                'max_attempts'  => \env('RATE_LIMIT_EMAIL_VERIFICATION_SEND_MAX', 3, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_EMAIL_VERIFICATION_SEND_DECAY', 900, 'int'),
            ],
            'email_verification_verify' => [
                'max_attempts'  => \env('RATE_LIMIT_EMAIL_VERIFICATION_VERIFY_MAX', 5, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_EMAIL_VERIFICATION_VERIFY_DECAY', 600, 'int'),
            ],
            'email_verification_resend' => [
                'max_attempts'  => \env('RATE_LIMIT_EMAIL_VERIFICATION_RESEND_MAX', 3, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_EMAIL_VERIFICATION_RESEND_DECAY', 1800, 'int'),
            ],
            'password_reset_request'    => [
                'max_attempts'  => \env('RATE_LIMIT_PASSWORD_RESET_REQUEST_MAX', 3, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_PASSWORD_RESET_REQUEST_DECAY', 900, 'int'),
            ],
            'password_reset_submit'     => [
                'max_attempts'  => \env('RATE_LIMIT_PASSWORD_RESET_SUBMIT_MAX', 5, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_PASSWORD_RESET_SUBMIT_DECAY', 900, 'int'),
            ],
            'password_change'           => [
                'max_attempts'  => \env('RATE_LIMIT_PASSWORD_CHANGE_MAX', 5, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_PASSWORD_CHANGE_DECAY', 900, 'int'),
            ],
            'oauth_email'               => [
                'max_attempts'  => \env('RATE_LIMIT_OAUTH_EMAIL_MAX', 5, 'int'),
                'decay_seconds' => \env('RATE_LIMIT_OAUTH_EMAIL_DECAY', 900, 'int'),
            ],
        ],
    ],
];
