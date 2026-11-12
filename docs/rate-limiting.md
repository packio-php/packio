# Rate Limiting

## Overview

Rate limiting is implemented via a declarative attribute `#[RateLimit(...)]` combined with a centralized middleware that reads rules from route options and limits from `config.security.rate_limits`.

This approach:
- Follows the existing attribute-driven style (`ValidationFailed`, `MapRequest`, etc.)
- Removes duplicate rate limiting logic from handlers
- Keeps limits centralized in configuration (single source of truth)
- Makes the rate limit rule visible next to the handler

Additionally, Composer registry authentication is limited by
`RegistryProtocolAuthorizer` using the same policy key:
`security.rate_limits['registry_auth']` (`max_attempts`/`decay_seconds`).
`RequireRegistryAccessMiddleware` maps the authorizer result to a response; a hit
limit or a missing policy answers 429 with `Retry-After`.

## Other limiters

- `ApiAuthenticationAttemptRateLimitMiddleware` is piped globally. It blocks a client IP
  once its failed API credential checks (recorded by Rest `ApiAuthenticationContextMiddleware`) exceed the limit, with the `api_authentication` policy
  (key `api_authentication:ip:<ip>`). Successful and credential-free requests are not counted.
- `CsrfValidationMiddleware` uses the rate limiter directly, only to avoid flooding
  the log with repeated CSRF failure entries. It does not block requests.
- REST handlers in `src/Module/Rest` use `#[RateLimit]` with the `api_write`,
  `api_destructive`, `api_token_create`, `api_queue_action` and `api_admin_write` policies.
  `ReceiveWebhookHandler` uses `webhook`.

## Policies

Defaults from `config/autoload/packio.global.php`:

| Policy | Max attempts (env) | Decay seconds (env) |
|--------|--------------------|---------------------|
| `login` | 5 (`RATE_LIMIT_LOGIN_MAX`) | 900 (`RATE_LIMIT_LOGIN_DECAY`) |
| `register` | 5 (`RATE_LIMIT_REGISTER_MAX`) | 3600 (`RATE_LIMIT_REGISTER_DECAY`) |
| `webhook` | 60 (`RATE_LIMIT_WEBHOOK_MAX`) | 60 (`RATE_LIMIT_WEBHOOK_DECAY`) |
| `registry_auth` | 30 (`RATE_LIMIT_REGISTRY_AUTH_MAX`) | 60 (`RATE_LIMIT_REGISTRY_AUTH_DECAY`) |
| `api_authentication` | 60 (`RATE_LIMIT_API_AUTHENTICATION_MAX`) | 60 (`RATE_LIMIT_API_AUTHENTICATION_DECAY`) |
| `api_token_create` | 10 (`RATE_LIMIT_API_TOKEN_CREATE_MAX`) | 3600 (`RATE_LIMIT_API_TOKEN_CREATE_DECAY`) |
| `api_write` | 120 (`RATE_LIMIT_API_WRITE_MAX`) | 60 (`RATE_LIMIT_API_WRITE_DECAY`) |
| `api_destructive` | 30 (`RATE_LIMIT_API_DESTRUCTIVE_MAX`) | 60 (`RATE_LIMIT_API_DESTRUCTIVE_DECAY`) |
| `api_queue_action` | 30 (`RATE_LIMIT_API_QUEUE_ACTION_MAX`) | 60 (`RATE_LIMIT_API_QUEUE_ACTION_DECAY`) |
| `api_admin_write` | 60 (`RATE_LIMIT_API_ADMIN_WRITE_MAX`) | 60 (`RATE_LIMIT_API_ADMIN_WRITE_DECAY`) |
| `email_verification_send` | 3 (`RATE_LIMIT_EMAIL_VERIFICATION_SEND_MAX`) | 900 (`RATE_LIMIT_EMAIL_VERIFICATION_SEND_DECAY`) |
| `email_verification_verify` | 5 (`RATE_LIMIT_EMAIL_VERIFICATION_VERIFY_MAX`) | 600 (`RATE_LIMIT_EMAIL_VERIFICATION_VERIFY_DECAY`) |
| `email_verification_resend` | 3 (`RATE_LIMIT_EMAIL_VERIFICATION_RESEND_MAX`) | 1800 (`RATE_LIMIT_EMAIL_VERIFICATION_RESEND_DECAY`) |
| `password_reset_request` | 3 (`RATE_LIMIT_PASSWORD_RESET_REQUEST_MAX`) | 900 (`RATE_LIMIT_PASSWORD_RESET_REQUEST_DECAY`) |
| `password_reset_submit` | 5 (`RATE_LIMIT_PASSWORD_RESET_SUBMIT_MAX`) | 900 (`RATE_LIMIT_PASSWORD_RESET_SUBMIT_DECAY`) |
| `password_change` | 5 (`RATE_LIMIT_PASSWORD_CHANGE_MAX`) | 900 (`RATE_LIMIT_PASSWORD_CHANGE_DECAY`) |
| `oauth_email` | 5 (`RATE_LIMIT_OAUTH_EMAIL_MAX`) | 900 (`RATE_LIMIT_OAUTH_EMAIL_DECAY`) |


## Architecture

```
Handler attribute #[RateLimit(...)]
    -> getDefaults()
    -> route options['rate_limit']
    -> getMiddleware()
    -> RateLimitMiddleware::class
    -> middleware reads the rule
    -> middleware resolves policy from config
    -> middleware builds subject key
    -> RateLimiterInterface::attempt(...)
    -> success => next handler
    -> blocked => response per rule settings
```

## Usage

### Basic example

```php
use Packio\Application\Routing\Attribute\RateLimit;

#[Post('/auth/verification/send', name: 'auth.verification.send')]
#[RateLimit(
    key: 'email_verification_send',
    by: ['ip'],
    response: 'json',
    message: 'Too many requests. Please try again later.',
)]
final readonly class EmailVerificationSendHandler implements RequestHandlerInterface
{
    // ...
}
```

### Full attribute API

```php
#[RateLimit(
    key: 'login',               // Policy key in config.security.rate_limits (required)
    by: ['ip', 'body:email'],   // Subject key building strategy (default: ['ip'])
    response: 'redirect',       // Response mode: 'json', 'redirect', 'empty' (default: 'json')
    message: null,              // Custom error message (default: null system default)
    status: 429,                // HTTP status code for non-redirect responses (default: 429, must be 400-599)
    redirectTo: '/auth/login',  // Redirect target when response='redirect'
    level: 'error',             // Flash message level (default: 'error')
    notify: 'flash',            // Notification mode: 'flash', 'toast', 'none' (default: 'flash')
    methods: ['POST'],          // HTTP methods this rule applies to (empty = all methods)
)]
```

### `by` strategies

The `by` parameter controls how the rate limiter key is built. Supported strategies:

| Strategy | Example | Resulting key part |
|----------|---------|-------------------|
| `'ip'` | `['ip']` | `ip:127.0.0.1` |
| `'body:<field>'` | `['body:email']` | `body:email:user@example.com` |
| `'user'` | `['user']` | `user:<current_user_id>` or `user:guest` |
| `'route:<attribute>'` | `['route:organization']` | `route:organization:acme` |

Multiple strategies can be combined:

```php
#[RateLimit(
    key: 'login',
    by: ['ip', 'body:email'],
)]
```

This produces a key like: `login:ip:127.0.0.1:body:email:user@example.com`

### Client IP behind a proxy

IP subjects use the shared client IP resolver. Set `TRUSTED_PROXIES` to the
explicit proxy IPs/CIDRs seen in `REMOTE_ADDR` (comma-separated). An untrusted
peer always uses its own address, ignoring forwarded headers; an invalid or
missing peer address resolves to `null`.

For a trusted peer, only `X-Forwarded-For` is used. Repeated header values form
one chain in arrival order. The resolver validates the entire chain, then walks
from right to left, skips trusted proxy hops, and selects the first untrusted
address. A spoofed left prefix therefore cannot replace the address appended by
your trusted proxy. `CF-Connecting-IP` and `X-Real-IP` are ignored in the default XFF mode (see
[Dedicated real-IP header](#dedicated-real-ip-header-behind-a-trusted-proxy)); the edge must
supply verified client identity through XFF and sanitize incoming headers.

Empty, malformed, all-trusted chains, more than 32 hops, or more than 4096 bytes
across all XFF values fall back to the peer address. This changes attribution
from older versions that preferred dedicated IP headers or the first valid XFF
entry. Check your proxy topology when upgrading; keep explicit trust ranges.

`APP_PRODUCTION=true` rejects any `*` token in `TRUSTED_PROXIES` during config
loading. Development fixtures may use `*`, but it trusts every hop and thus
falls back to the peer address; it cannot identify individual clients through
XFF and is unsuitable for production rate limiting.

For the supported single-host Caddy topology, configure `TRUSTED_PROXIES` with
the exact Caddy peer measured from the app's established upstream socket. A
Docker bridge gateway may be that peer; do not guess a fixed gateway value. See
the [production proxy and peer measurement procedure](docker.md#host-caddy-reverse-proxy).

### Response modes

| Mode | Behavior |
|------|----------|
| `json` | Returns a JSON response with `Retry-After` header |
| `redirect` | Redirects to `redirectTo` with optional flash message and `Retry-After` header |
| `empty` | Returns a true empty response with only status code and `Retry-After` header |

### Login throttling

`POST /auth/login` uses the IP-based `login` policy (default: five attempts
per 900 seconds). When exhausted, it stops before authentication and returns
`302` with `Location: /auth/login` and `Retry-After`. It queues the existing
error flash message: “Too many login attempts. Please try again later.”
The next login page displays it through the normal Inertia flash flow.

`Retry-After` contains the configured decay interval, not an exact remaining
countdown. The throttle does not store submitted fields as old input or include
`redirect_to` in the redirect URL; retaining email and the destination is not
guaranteed across the redirect.

## Configuration

Add policies to `config/autoload/packio.global.php` under `security.rate_limits`:

```php
'rate_limits' => [
    'retention_seconds' => $rateLimitRetention, // validated from RATE_LIMIT_RETENTION_SECONDS
    'storage_dir' => \env('APP_RATE_LIMIT_DIR', 'data/rate-limits'),

    'login' => [
        'max_attempts' => \env('RATE_LIMIT_LOGIN_MAX', 5, 'int'),
        'decay_seconds' => \env('RATE_LIMIT_LOGIN_DECAY', 900, 'int'),
    ],
    // ... etc
],
```

## Adding a new rate-limited endpoint

1. Add a policy in `config.security.rate_limits`
2. Add `#[RateLimit(key: 'your_policy_key', ...)]` to the handler class
3. Optionally add environment variables for the policy values

## Files

| File | Purpose |
|------|---------|
| `src/Application/src/Routing/Attribute/RateLimit.php` | The `#[RateLimit]` attribute |
| `src/Application/src/Middleware/RateLimitMiddleware.php` | Middleware that processes rate limit rules |
| `config/autoload/packio.global.php` | Rate limit policy configuration |
| `src/Application/config/dependencies.config.php` | DI registration for middleware and `config.security.rate_limits` injection |
| `src/Module/Registry/src/Infrastructure/Authorization/RegistryProtocolAuthorizer.php` | Registry API auth rate limiting using `registry_auth` policy |
| `src/Module/Registry/src/Infrastructure/Middleware/RequireRegistryAccessMiddleware.php` | Maps authorization results to responses (429 with `Retry-After` when rate limited) |
| `src/Application/src/Security/RateLimiter/RateLimiterInterface.php` | Rate limiter interface |
| `src/Application/src/Security/RateLimiter/FileRateLimiter.php` | File-based rate limiter implementation |


## Storage lifecycle and cleanup

The supported backend is a single-host local filesystem. Stored files remain
JSON timestamp arrays named by the SHA-256 hash of the limiter key. All writers,
checks, clears and cleanup share one stable `.storage.lock`, acquired exclusively
before opening a data file. Keep this lock file in place, including between
maintenance runs. Storage or lock errors fail closed; cleanup exits unsuccessfully
instead of reporting a successful partial operation.

`security.rate_limits.retention_seconds` / `RATE_LIMIT_RETENTION_SECONDS` defaults
to the largest **effective** configured policy window after local
configuration overrides, including the direct CSRF logging window of 300 seconds.
The container validates those windows before accepting traffic. Explicit retention
must be a positive integer covering them all. Individual `attempt` and `isExceeded`
calls also reject nonpositive windows or windows above retention by denying the
attempt / reporting an exceeded limit. Configure retention to cover custom callers
as well. Changing retention does not rewrite or attach expiry metadata to existing
files; their historical window cannot be inferred from timestamp arrays.

### Migrate the lock protocol before enabling maintenance

1. Stop mutating traffic and **all old writers**, including old HTTP workers,
   worker processes, cron/maintenance commands and overlapping deployments. Old
   processes use a different lock protocol and must never overlap cleanup.
2. Confirm the maximum historical window used by every writer, including prior
   ENV/local/inline overrides and direct limiter callers. Set retention to at least
   both that maximum and the current effective maximum. Keep a record of that
   operator decision. If the historical maximum is unknown, **do not activate
   cleanup** until a conservative window chosen by the operator has elapsed with
   all old writers stopped. An unknown historical TTL is not a verified cleanup precondition.
3. Start **all new writers** with consistent retention and the stable lock
   protocol before running cleanup. Use a private directory accessible only by
   the application/maintenance user; do not replace it, the stable lock or cursor
   while processes are running. Clear application config cache using the normal
   deployment procedure when changing ENV/local configuration.
4. First run a dry run with the confirmed retention, then run live maintenance
   explicitly. No schedule or automatic activation is installed by this change.

Do not lower retention while timestamps from a larger historical window can
still be active. Drain/stop all writers and wait out the previous maximum before
reducing it. Lowering only a policy window while keeping retention at the old
maximum preserves existing active files; this is the tested migration case.
An arbitrary unsafe retention reduction cannot be detected from this file format.

### Run bounded maintenance

```bash
# Example only: 3600 is safe only if it covers your confirmed historical maximum.
php vendor/bin/laminas rate-limit:cleanup --retention-seconds 3600 --limit 1000 --dry-run
php vendor/bin/laminas rate-limit:cleanup --retention-seconds 3600 --limit 1000
```

Retention is required, must be positive and at least the configured retention;
`--limit` defaults to 1000 and accepts 1..1000. The command reports
`scanned=N deleted=N skipped=N`; in dry-run, `deleted` is the number that would be
deleted. Dry-run changes no limiter data, stable lock or cursor. Only regular
`^[a-f0-9]{64}\.json$` files whose every timestamp has expired relative to retention
are deleted. Other basenames are ignored. Malformed candidate contents and
symlink candidates are skipped without following them.
Active files stay intact even when their filesystem modification time is old.

Candidates are sorted. A live run saves its last candidate in `.cleanup.cursor`
and the next run starts after it, wrapping at the end; repeated bounded runs reach
expired files beyond the first 1000 active entries. Dry-run reads the same cursor
without advancing it. If storage already exists but its stable lock is missing,
dry-run fails without creating it; investigate the migration/writer setup before
retrying. A missing storage directory is an empty no-op. The lock is released
after each candidate so writers can
proceed between files. Directory enumeration is O(n); the scan limit bounds
candidate processing, not enumeration memory/time. The one storage lock serializes
filesystem operations; partitioning or sharding requires measured need and is
outside this backend's current contract.


## Dedicated real-IP header behind a trusted proxy

The default `TRUSTED_PROXY_CLIENT_IP_HEADER=X-Forwarded-For` keeps the existing right-to-left trusted-chain algorithm. For a proxy that overwrites `X-Real-IP`, set `TRUSTED_PROXY_CLIENT_IP_HEADER=X-Real-IP` and restrict `TRUSTED_PROXIES` to the immediate proxy addresses. The network must prevent clients from bypassing that proxy.

Real-IP mode accepts exactly one valid IPv4/IPv6 value only from a trusted immediate peer. Missing, malformed, comma-separated or repeated values fall back to the peer address, without falling back to XFF or CF-Connecting-IP. Headers from untrusted peers are ignored. Unsupported header names are rejected.

For the native Packio topology, Traefik, the trusted proxy in front of Packio, overwrites X-Real-IP using its Cloudflare real-IP middleware; Cloudflare CIDRs do not need to be added to Packio trust. Configure this only after verifying that middleware is attached to every Packio router.
