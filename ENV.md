# Environment variables

The supported deployment target is the standalone `compose.prod.yaml` stack on a
Linux amd64 host, with RoadRunner, PostgreSQL 17, PHP 8.5.x, and Node 24.21.0 for
the image build. The production example uses `packio.example.com`; replace the app
and registry domains with names backed by your DNS and TLS certificate. Copy
`.env.prod.example` to the ignored `.env.prod.local` and set its secrets before
starting the stack. The example's app service runs migrations and the runtime
settings seed automatically at startup; do not also run them manually.

Local development uses `compose.yaml` defaults: PostgreSQL is published on
`localhost:15432`, and app URLs use `packio.localhost`. Copy `.env.example` to
`.env` when changing those defaults.

## Database

| Variable | Default | Description |
|---|---|---|
| `DEFAULT_DB_CONNECTION` | `postgres` | PostgreSQL is the supported database for this release. Other values fail during configuration. |
| `DATABASE_HOST` | `localhost` | PostgreSQL hostname; use `postgres` between Compose services. |
| `DATABASE_PORT` | `15432` | PostgreSQL port exposed to the host by the local development Compose file; production service traffic uses `5432`. |
| `DATABASE_NAME` | `packio` | Database name. |
| `DATABASE_USER` | `postgresql` | Database user; production example uses `packio`. |
| `DATABASE_PASS` | `password` | Database password; replace the development default for any shared environment. |

## Application

| Variable | Default | Description |
|---|---|---|
| `APP_URL` | `http://packio.localhost:8080` in development | Public application URL. In production, use the HTTPS app domain. |
| `APP_DEBUG` | `true` | Enables debug behavior; production Compose sets this to `false`. |
| `APP_PRODUCTION` | `false` | Enables production configuration; production Compose sets this to `true`. |
| `APP_SECRET_ENCRYPTION_KEY` | empty | Required in production to encrypt stored provider credentials. Generate with `openssl rand -base64 32` and prefix the value with `base64:`. Preserve and back up this key with the database. |
| `APP_NAME` | `BRAND_PRODUCT_NAME`, then `Packio` | Application name |
| `APP_TIMEZONE` | `UTC` | Application timezone |
| `IDENTITY_REGISTRATION_OPEN` | `true` | Allow new user registration |
| `ORGANIZATION_RESERVED_SLUGS` | built-in list | Comma-separated organization slugs that cannot be used |
| `PASSWORD_RESET_TOKEN_TTL_SECONDS` | `3600` | Password reset token lifetime in seconds |
| `API_DOCS_ENABLED` | `true` | Serve the API documentation |
| `API_DOCS_PUBLIC` | `true` | Allow unauthenticated access to the API documentation |
| `API_TOKEN_PREFIX` | `packio_api_` | Prefix of generated API tokens |
| `VITE_DEV_SERVER_URL` | `http://localhost:5173` | Vite dev server URL used in development |
| `RUN_MIGRATIONS` | `true` in development Compose | Production Compose enables migrations and runtime-settings seed in the `app` service only. Workers and SSR have migrations disabled. |

Keep `APP_SECRET_ENCRYPTION_KEY` unchanged between deployments and identical in
HTTP and worker processes. Back it up in separate protected storage, linked to
the matching database/archive/config backup by a nonsecret identifier. Missing
or wrong keys make recovery fail; a replacement key cannot decrypt stored
credentials or pending authentication mail. Live rotation/re-encryption is not
supported. See [backup/restore](docs/operations/backup-restore.md) and
[upgrade/rollback](docs/operations/upgrade.md).

Rate-limit variables (`RATE_LIMIT_*`, `APP_RATE_LIMIT_DIR`, `TRUSTED_PROXY_CLIENT_IP_HEADER`)
are documented in [rate limiting](docs/rate-limiting.md). Container and worker
variables (`WORKER_*`, `RR_*`, `PACKIO_WORKER_NUM`, `HTTP_PORT`, `PACKIO_DOCKER_UID`,
`PACKIO_DOCKER_GID`) are documented in [Docker](docs/docker.md).

### Trusted proxies

| Variable | Default | Description |
|---|---|---|
| `TRUSTED_PROXIES` | `""` | Comma-separated proxy IPs/CIDRs. The `*` wildcard is rejected in production. |

### Mail

| Variable | Default | Description |
|---|---|---|
| `MAIL_DSN` | `""` | Full mailer DSN; when set it overrides the scheme, host, port and credential variables |
| `MAIL_SCHEME` | `smtp` | Mailer scheme |
| `MAIL_HOST` | `localhost` | SMTP host |
| `MAIL_PORT` | `1025` | SMTP port |
| `MAIL_USERNAME` | `""` | SMTP username |
| `MAIL_PASSWORD` | `""` | SMTP password |
| `MAIL_FROM` | `""` | Sender address; empty uses `MAIL_FROM_ADDRESS` |
| `MAIL_FROM_ADDRESS` | `noreply@packio.dev` | Sender address used when `MAIL_FROM` is empty |
| `MAIL_FROM_NAME` | `""` | Sender display name |

### Branding

| Variable | Default | Description |
|---|---|---|
| `BRAND_PRODUCT_NAME` | `Packio` | Product name |
| `BRAND_TAGLINE` | `a lightweight private package registry for Composer` | Product tagline |
| `BRAND_DOMAIN` | `packio.us` | Product domain |
| `BRAND_REGISTRY_BASE_DOMAIN` | `BRAND_DOMAIN` | Base domain for organization registry hosts |
| `BRAND_SHORT_NAME` | `packio` | Short name |
| `BRAND_COMPOSER_REPOSITORY_ALIAS` | `packio` | Composer repository alias |
| `BRAND_REGISTRY_TOKEN_PREFIX` | `packio_rt_` | Prefix of registry tokens |
| `BRAND_REGISTRY_AUTH_REALM` | `Packio` | HTTP auth realm of the registry |
| `BRAND_HTTP_USER_AGENT` | `Packio/0.1` | User agent of outbound HTTP requests |
| `BRAND_THEME_STORAGE_KEY` | `packio-theme` | Browser storage key for the theme |

### Turnstile

| Variable | Default | Description |
|---|---|---|
| `TURNSTILE_ENABLED` | `false` | Enable Cloudflare Turnstile on public forms |
| `TURNSTILE_SITE_KEY` | `""` | Turnstile site key |
| `TURNSTILE_SECRET_KEY` | `""` | Turnstile secret key |
| `TURNSTILE_HOSTNAME` | `""` | Expected hostname in the verification response |
| `TURNSTILE_VALIDATE_HOSTNAME` | `true` | Check the hostname in the verification response |
| `TURNSTILE_VERIFY_URL` | `https://challenges.cloudflare.com/turnstile/v0/siteverify` | Turnstile verification endpoint |

## Logging

```json
{
    "LOG_CHANNEL": "daily",
    "LOG_LEVEL": "debug",
    "LOG_DIR": "data/logs",
    "LOG_PATH": "",
    "LOG_DAILY_DAYS": 14,
    "LOG_FILE_PERMISSION": "0664"
}
```

### Application settings

| Variable | Default | Description |
|---|---|---|
| `APP_RELEASE_FILE` | `data/build/release.json` | Generated release metadata file used by the application version payload |
| `INERTIA_SSR_ENABLED` | `true` | Enable best-effort Inertia SSR for full-page responses |
| `INERTIA_SSR_URL` | `http://127.0.0.1:13714/render` | Inertia SSR render endpoint used by the PHP app |
| `INERTIA_SSR_TIMEOUT` | `1.0` | Maximum SSR render request time in seconds before falling back to CSR |
| `INERTIA_SSR_CONNECT_TIMEOUT` | `1.0` | Maximum SSR connection time in seconds before falling back to CSR |
| `VALINOR_CACHE_DIR` | `data/cache/valinor` when `APP_PRODUCTION=true`, otherwise `""` | Valinor mapper cache directory; empty disables cache |
| `VALINOR_CACHE_WATCH` | `false` | Watch DTO files and refresh Valinor mapper cache when cache is enabled |
| `OBJECT_MAPPER_CACHE_DIR` | `data/cache/object-mapper` | Base owner-only (`0700`) cache directory for generated object mappers; the active `0.10.0` cache is isolated in its `v8` subdirectory, which must be writable by the deployment and runtime user. Generated files remain `0600`. |
| `OBJECT_MAPPER_GENERATE_ON_DEMAND` | `true` outside production, otherwise `false` | Generate mapper code on cache miss; keep disabled in production. After publishing, run `APP_PRODUCTION=true OBJECT_MAPPER_GENERATE_ON_DEMAND=0 php vendor/bin/laminas object-mapper:warmup` before HTTP/worker restart; it prepares the format-versioned cache without deleting files that an older running release may still need. |
| `OBJECT_MAPPER_REUSE_PREPARED_MAPPINGS` | `true` in production, otherwise `false` | Reuse validated mapper metadata and generated mapper instances within a PHP process. Long-running HTTP workers warm their process-local entries before accepting traffic; restart them after mapper-related deployments. |

### HTTP CORS

CORS is globally piped before routing and uses explicit configured origins. The
runtime setting `http.cors.enabled` can force CORS handling on or off without
clearing config cache; origin/header policy remains environment/config driven.

The default policy is for stateless REST API clients. `X-CSRF-Token` is not a
CORS request or exposed response header: the cookie-authenticated web UI is
same-origin and sends that header only to its own origin. A credentialed
cross-origin web client requires a separate threat-model change with exact
origins and CSRF contract tests; do not enable it by adding this header here.

| Variable | Default | Description |
|---|---|---|
| `CORS_ENABLED` | `true` | Fallback value for the `http.cors.enabled` runtime setting |
| `CORS_ALLOWED_ORIGINS` | `APP_URL` | Comma-separated allowed origins. Use explicit origins such as `https://packio.us` or `http://localhost:5173`; avoid `*` for authenticated browser clients |
| `CORS_ALLOWED_HEADERS` | `Accept,Authorization,Content-Type,X-API-TOKEN,X-Inertia,X-Inertia-Version,X-Requested-With` | Comma-separated request headers allowed in browser preflight requests |
| `CORS_EXPOSED_HEADERS` | `Content-Disposition,Location,Retry-After,WWW-Authenticate,X-Inertia,X-Inertia-Location,X-Inertia-Version` | Comma-separated response headers exposed to browser clients |
| `CORS_ALLOWED_MAX_AGE` | `600` | Preflight cache lifetime in seconds |
| `CORS_CREDENTIALS_ALLOWED` | `false` | Send `Access-Control-Allow-Credentials: true`; requires explicit origins and cannot be combined with `*` |

### Sentry

| Variable | Default | Description |
|---|---|---|
| `SENTRY_DSN` | `""` | Hosted Sentry project DSN. Empty disables event delivery. |
| `SENTRY_ENVIRONMENT` | `""` | Sentry environment. Empty falls back to `production` when `APP_PRODUCTION=true`, otherwise `local`. |
| `SENTRY_RELEASE` | `""` | Release id. Empty uses `data/build/release.json` when present. |
| `SENTRY_SAMPLE_RATE` | `1.0` | Error event sample rate; `1.0` sends all captured backend errors. |
| `SENTRY_TRACES_SAMPLE_RATE` | `0.0` | Performance tracing sample rate; disabled by default. |
| `SENTRY_FLUSH_ON_HTTP_ERROR` | `false` | Flush synchronously after captured HTTP exceptions. |
| `SENTRY_FLUSH_TIMEOUT` | `2` | Flush timeout in seconds for CLI and manual worker reporting. |
| `SENTRY_CAPTURE_CONSOLE_INPUT` | `false` | Attach redacted CLI args/options to Sentry command context. Keep disabled unless command payloads have been reviewed. |

### Source providers

| Variable | Default | Description |
|---|---|---|
| `SOURCE_PROVIDER_GITHUB_ENABLED` | `true` | Enable GitHub as a source provider |
| `SOURCE_PROVIDER_GITLAB_ENABLED` | `true` | Enable GitLab as a source provider |
| `SOURCE_PROVIDER_GITLAB_SELF_HOSTED_ENABLED` | `false` | Enable self-hosted GitLab as a source provider; `.env.example` sets it to `true` |
| `SOURCE_PROVIDER_GENERIC_GIT_ENABLED` | `true` | Enable Generic Git as a source provider |
| `SOURCE_PROVIDER_BITBUCKET_ENABLED` | `true` | Enable Bitbucket as a source provider |
| `SOURCE_PROVIDER_CODEBERG_ENABLED` | `true` | Enable Codeberg as a source provider |

### Private package archive storage

Private release archives are stored behind Packio's registry authorization boundary.
The default local driver stores files outside the public web root.

| Variable | Default | Description |
|---|---|---|
| `PACKAGE_ARCHIVE_STORAGE_DRIVER` | `local` | Archive storage driver: `local` or `s3` |
| `PACKAGE_ARCHIVE_MAX_BYTES` | `52428800` | Maximum archive size accepted from upstream while filling storage |
| `PACKAGE_ARCHIVE_FALLBACK_TO_SOURCE_ON_UNAVAILABLE` | `false` | Allow private dist downloads to fetch directly from the source provider when archive storage is unavailable; this weakens the storage-backed artifact guarantee and requires source credentials during download |
| `PACKAGE_ARCHIVE_LOCAL_PATH` | `data/package-archives` | Local archive storage root; must not be inside `public/` |
| `PACKAGE_ARCHIVE_LOCAL_PREFIX` | `private` | Local object key prefix without leading or trailing slash |
| `PACKAGE_ARCHIVE_S3_BUCKET` | `""` | Private S3-compatible bucket name |
| `PACKAGE_ARCHIVE_S3_REGION` | `fsn1` | S3-compatible region, e.g. Hetzner location `fsn1` |
| `PACKAGE_ARCHIVE_S3_ENDPOINT` | `""` | S3-compatible endpoint URL, e.g. `https://fsn1.your-objectstorage.com` |
| `PACKAGE_ARCHIVE_S3_ACCESS_KEY` | `""` | S3 access key for the archive bucket |
| `PACKAGE_ARCHIVE_S3_SECRET_KEY` | `""` | S3 secret key for the archive bucket |
| `PACKAGE_ARCHIVE_S3_PATH_STYLE_ENDPOINT` | `false` | Use path-style S3 requests instead of virtual-hosted style |
| `PACKAGE_ARCHIVE_S3_USE_SSL` | `true` | Use SSL for S3 requests |
| `PACKAGE_ARCHIVE_S3_PREFIX` | `private` | S3 object key prefix without leading or trailing slash |
| `PACKAGE_ARCHIVE_S3_MULTIPART_THRESHOLD_BYTES` | `16777216` | Minimum object size before AWS SDK multipart upload handling |
| `PACKAGE_ARCHIVE_S3_TEMPORARY_URL_TTL_SECONDS` | `300` | Reserved TTL for future private temporary URLs |

Optional real S3 contract tests are disabled by default. Set these only when running
`php vendor/bin/phpunit --testsuite integration --group s3` against a disposable
bucket/prefix:

| Variable | Default | Description |
|---|---|---|
| `PACKIO_TEST_S3` | `0` | Set to `1` to enable real S3 archive storage contract tests |
| `PACKIO_TEST_S3_BUCKET` | `""` | Test bucket name |
| `PACKIO_TEST_S3_REGION` | `fsn1` | Test S3-compatible region |
| `PACKIO_TEST_S3_ENDPOINT` | `""` | Test S3-compatible endpoint URL |
| `PACKIO_TEST_S3_ACCESS_KEY` | `""` | Test S3 access key |
| `PACKIO_TEST_S3_SECRET_KEY` | `""` | Test S3 secret key |
| `PACKIO_TEST_S3_PATH_STYLE_ENDPOINT` | `0` | Set to `1` for path-style endpoints |
| `PACKIO_TEST_S3_USE_SSL` | `1` | Set to `0` for local non-TLS S3-compatible endpoints |
| `PACKIO_TEST_S3_PREFIX` | `test` | Disposable test object prefix |

### Source webhooks

| Variable | Default | Description |
|---|---|---|
| `SOURCE_WEBHOOKS_GITHUB_SIGNATURE_HEADER` | `X-Hub-Signature-256` | GitHub webhook signature header |
| `SOURCE_WEBHOOKS_GITHUB_EVENTS` | `"push,create,release"` | Comma-separated GitHub webhook events that trigger source sync |
| `SOURCE_WEBHOOKS_GITLAB_EVENTS` | `"Push Hook,Tag Push Hook"` | Comma-separated GitLab webhook events that trigger source sync |
| `SOURCE_WEBHOOKS_BITBUCKET_EVENTS` | `"repo:push"` | Comma-separated Bitbucket webhook events that trigger source sync |
| `SOURCE_WEBHOOKS_CODEBERG_EVENTS` | `"push,create,delete"` | Comma-separated Codeberg/Forgejo webhook events that trigger source sync |

### OAuth

OAuth provider configuration. Enable one or more providers to allow users to sign in
and/or connect source repositories via OAuth.

#### GitHub

| Variable | Default | Description |
|---|---|---|
| `OAUTH_GITHUB_ENABLED` | `false` | Enable GitHub OAuth |
| `OAUTH_GITHUB_CLIENT_ID` | `""` | GitHub OAuth App client ID |
| `OAUTH_GITHUB_CLIENT_SECRET` | `""` | GitHub OAuth App client secret |
| `OAUTH_GITHUB_REDIRECT_URL` | `""` | GitHub OAuth redirect URI (must match OAuth App settings) |
| `OAUTH_GITHUB_SCOPES` | `"read:user,user:email"` | Comma-separated scopes for identity (login) OAuth |
| `OAUTH_GITHUB_SOURCE_SCOPES` | `"read:user,user:email,repo,read:org"` | Comma-separated scopes for source-connect OAuth |
| `OAUTH_GITHUB_SOURCE_ENABLED` | `false` | Enable GitHub source OAuth without removing stored source client secrets |
| `OAUTH_GITHUB_SOURCE_CLIENT_ID` | `""` | Separate GitHub OAuth App client ID for source connections |
| `OAUTH_GITHUB_SOURCE_CLIENT_SECRET` | `""` | Separate GitHub OAuth App client secret for source connections |
| `OAUTH_GITHUB_SOURCE_REDIRECT_URL` | `""` | Source OAuth redirect URI. Empty uses `{APP_URL}/auth/oauth/github/source-callback` |
| `OAUTH_GITHUB_AUTHORIZATION_URL` | `https://github.com/login/oauth/authorize` | Override authorization endpoint (e.g. GitHub Enterprise Server) |
| `OAUTH_GITHUB_TOKEN_URL` | `https://github.com/login/oauth/access_token` | Override token endpoint |
| `OAUTH_GITHUB_USER_URL` | `https://api.github.com/user` | Override user API endpoint |

#### GitLab

| Variable | Default | Description |
|---|---|---|
| `OAUTH_GITLAB_ENABLED` | `false` | Enable GitLab OAuth |
| `OAUTH_GITLAB_CLIENT_ID` | `""` | GitLab OAuth App client ID |
| `OAUTH_GITLAB_CLIENT_SECRET` | `""` | GitLab OAuth App client secret |
| `OAUTH_GITLAB_REDIRECT_URL` | `""` | GitLab OAuth redirect URI (must match OAuth App settings) |
| `OAUTH_GITLAB_SCOPES` | `"read_user,openid,profile"` | Comma-separated scopes for identity (login) OAuth |
| `OAUTH_GITLAB_SOURCE_SCOPES` | `"read_user,api,read_repository"` | Comma-separated scopes for source-connect OAuth. The GitLab OAuth application must allow `api`, `read_user`, and `read_repository`. |
| `OAUTH_GITLAB_SOURCE_ENABLED` | `false` | Enable GitLab source OAuth without removing stored source client secrets |
| `OAUTH_GITLAB_SOURCE_CLIENT_ID` | `""` | Separate GitLab OAuth application client ID for source connections |
| `OAUTH_GITLAB_SOURCE_CLIENT_SECRET` | `""` | Separate GitLab OAuth application client secret for source connections |
| `OAUTH_GITLAB_SOURCE_REDIRECT_URL` | `""` | Source OAuth redirect URI. Empty uses `{APP_URL}/auth/oauth/gitlab/source-callback` |
| `OAUTH_GITLAB_AUTHORIZATION_URL` | `""` | Override authorization endpoint; empty values fall back to `{base_url}/oauth/authorize` |
| `OAUTH_GITLAB_TOKEN_URL` | `""` | Override token endpoint; empty values fall back to `{base_url}/oauth/token` |
| `OAUTH_GITLAB_USER_URL` | `""` | Override user API endpoint; empty values fall back to `{base_url}/api/v4/user` |
| `OAUTH_GITLAB_BASE_URL` | `https://gitlab.com` | Base URL for GitLab (used for self-hosted instances) |
| `OAUTH_GITLAB_SELF_HOSTED_SOURCE_ENABLED` | `false` | Enable self-hosted GitLab source OAuth |

#### Bitbucket

| Variable | Default | Description |
|---|---|---|
| `OAUTH_BITBUCKET_ENABLED` | `false` | Enable Bitbucket OAuth |
| `OAUTH_BITBUCKET_CLIENT_ID` | `""` | Bitbucket OAuth consumer client ID |
| `OAUTH_BITBUCKET_CLIENT_SECRET` | `""` | Bitbucket OAuth consumer client secret |
| `OAUTH_BITBUCKET_REDIRECT_URL` | `""` | Bitbucket OAuth redirect URI (must match OAuth consumer settings) |
| `OAUTH_BITBUCKET_SCOPES` | `"account,email"` | Comma-separated scopes for identity (login) OAuth |
| `OAUTH_BITBUCKET_SOURCE_SCOPES` | `"account,email,repository,webhook"` | Comma-separated scopes for source-connect OAuth |
| `OAUTH_BITBUCKET_SOURCE_ENABLED` | `false` | Enable Bitbucket source OAuth without removing stored source client secrets |
| `OAUTH_BITBUCKET_SOURCE_CLIENT_ID` | `""` | Separate Bitbucket OAuth client ID for source connections |
| `OAUTH_BITBUCKET_SOURCE_CLIENT_SECRET` | `""` | Separate Bitbucket OAuth client secret for source connections |
| `OAUTH_BITBUCKET_SOURCE_REDIRECT_URL` | `""` | Source OAuth redirect URI. Empty uses `{APP_URL}/auth/oauth/bitbucket/source-callback` |
| `OAUTH_BITBUCKET_AUTHORIZATION_URL` | `https://bitbucket.org/site/oauth2/authorize` | Override authorization endpoint |
| `OAUTH_BITBUCKET_TOKEN_URL` | `https://bitbucket.org/site/oauth2/access_token` | Override token endpoint |
| `OAUTH_BITBUCKET_USER_URL` | `https://api.bitbucket.org/2.0/user` | Override user API endpoint |
| `OAUTH_BITBUCKET_EMAILS_URL` | `https://api.bitbucket.org/2.0/user/emails` | Override user emails API endpoint |

#### Codeberg

Codeberg is backed by Forgejo. Forgejo OAuth2 scopes are not fine-grained
permission controls, so Packio does not request default Codeberg scopes.

| Variable | Default | Description |
|---|---|---|
| `OAUTH_CODEBERG_ENABLED` | `false` | Enable Codeberg OAuth |
| `OAUTH_CODEBERG_CLIENT_ID` | `""` | Codeberg OAuth application client ID |
| `OAUTH_CODEBERG_CLIENT_SECRET` | `""` | Codeberg OAuth application client secret |
| `OAUTH_CODEBERG_REDIRECT_URL` | `""` | Codeberg OAuth redirect URI (must match OAuth application settings) |
| `OAUTH_CODEBERG_SCOPES` | `""` | Comma-separated identity OAuth scopes; empty by default |
| `OAUTH_CODEBERG_SOURCE_SCOPES` | `""` | Comma-separated source-connect OAuth scopes; empty by default |
| `OAUTH_CODEBERG_SOURCE_ENABLED` | `false` | Enable Codeberg source OAuth without removing stored source client secrets |
| `OAUTH_CODEBERG_SOURCE_CLIENT_ID` | `""` | Separate Codeberg OAuth application client ID for source connections |
| `OAUTH_CODEBERG_SOURCE_CLIENT_SECRET` | `""` | Separate Codeberg OAuth application client secret for source connections |
| `OAUTH_CODEBERG_SOURCE_REDIRECT_URL` | `""` | Source OAuth redirect URI. Empty uses `{APP_URL}/auth/oauth/codeberg/source-callback` |
| `OAUTH_CODEBERG_AUTHORIZATION_URL` | `""` | Override authorization endpoint; empty values fall back to `{base_url}/login/oauth/authorize` |
| `OAUTH_CODEBERG_TOKEN_URL` | `""` | Override token endpoint; empty values fall back to `{base_url}/login/oauth/access_token` |
| `OAUTH_CODEBERG_USER_URL` | `""` | Override user API endpoint; empty values fall back to `{base_url}/api/v1/user` |
| `OAUTH_CODEBERG_BASE_URL` | `https://codeberg.org` | Base URL for the hosted Codeberg instance |

## Outbound provider connections

Provider HTTP requests and Git operations accept HTTPS only. Public destinations use
port 443; loopback, private, link-local, reserved, multicast and IPv6 transition
addresses are rejected. Every HTTP redirect is checked again, and each connection
uses the validated IP addresses rather than resolving the name again. Credentials
are removed on cross-origin HTTP redirects. POST redirects and all Git redirects
are rejected; configure the final Git clone URL when a server redirects it.

An installation administrator can enable a specific internal server or port in
`config/autoload/outbound.local.php` (never through organization/source settings):

```php
<?php
return [
    'outbound_http' => [
        'endpoints' => [
            'https://git.company.example:8443' => ['10.20.0.15'],
        ],
    ],
];
```

Each key is an exact lowercase HTTPS origin, without a path or trailing slash;
omit `:443` for the default port. Each value lists literal destination IPs. These
addresses are pinned without a DNS lookup, so the administrator must update the
configuration when the server moves. TLS certificate verification remains enabled.
After changing configuration, run `composer clear-application-cache` and restart
long-running workers. Deploy this configuration to both web and worker runtimes.

The provider clients use direct connections and ignore inherited proxy settings
so proxies cannot resolve a validated hostname to a different address. Unrelated
clients (for example internal SSR rendering or S3 storage) keep their existing
configuration. Network-level egress rules should additionally restrict web/worker
containers to intended external services and explicitly approved internal servers;
application URL checks do not configure a host firewall.

## Request and subprocess resource limits

| Variable | Default | Purpose |
| --- | --- | --- |
| `WEBHOOK_MAX_BYTES` | `1048576` | Webhook body limit before JSON/form parsing |
| `REQUEST_MAX_BYTES` | `33554432` | General application request body limit |
| `UPSTREAM_MAX_BYTES` | `16777216` | Provider response limit during download and combined Git stdout/stderr limit |
| `GIT_TIMEOUT_SECONDS` | `120` | Wall-clock deadline and CPU limit for each Git command |
| `GIT_WORKSPACE_MAX_BYTES` | `1073741824` | Temporary clone/archive workspace budget |

All limits must be positive. `PACKAGE_ARCHIVE_MAX_BYTES` continues to limit archive
responses and now also bounds ZIP creation. Set the web server/RoadRunner request
limit consistently with the application limit; the lower limit wins.

Git subprocesses require PHP POSIX and PCNTL. A dedicated child establishes its
own process group and kernel file-size/CPU limits before executing Git. The parent
reads both output pipes, monitors the aggregate workspace, and kills the process
group on a timeout or limit breach. The file-size limit is enforced by the kernel;
the aggregate workspace check is sampled and can briefly overshoot between checks.
Use a quota-limited temporary volume when a strict aggregate disk quota is needed.
No unbounded repository checkout is created. Temporary directories are removed on
success and failure. Restart workers after changing configuration.

Git subprocesses also have a kernel address-space limit (`GIT_MEMORY_MAX_BYTES`, default 536870912 bytes / 512 MiB). This applies to Git and its child processes, without changing the PHP worker memory limit.

### Authentication mail delivery

Registration, verification resends and password-reset requests enqueue encrypted `identity.auth_mail` jobs in the reserved `system:identity` scope. Keep the regular `worker:run` process running for delivery. Organization job lists cannot access this scope. HTTP responses do not wait for SMTP; password-reset account lookup and token generation run in the worker. Verification codes and reset tokens start their normal validity period when the worker generates them.

Web and worker processes must share `APP_SECRET_ENCRYPTION_KEY` (already required in production). Job payloads contain encrypted requests, not email bodies, passwords, verification codes or reset secrets. Requests waiting over one hour are discarded; users can request delivery again. Delivery failures use the worker's existing retry/backoff (maximum three attempts), with generic job errors. Completed/failed jobs follow the existing worker cleanup policy. Do not rotate away a decryption key while pending authentication jobs still require it.
