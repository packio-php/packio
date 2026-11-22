# Testing

## Overview

The test suite is split by the level of behavior being verified:

- `Unit`: isolated classes, request handlers, middleware branches, DTO/request objects, page data builders
- `Integration`: adapter contracts and services that coordinate several collaborators
- `Feature`: real HTTP flows through the Mezzio application, container, routing, middleware pipeline, session, database and Inertia/JSON responses
- `Frontend`: Vitest unit and component tests for Svelte/Inertia browser-side behavior

Backend PHPUnit tests use the `PackioTest\\` namespace and live under `test/`. Frontend Vitest tests live beside the Svelte and TypeScript code under `resources/js` and use the frontend module structure instead of PHP namespaces. Legacy module-specific PHP namespaces such as `ApplicationTest\\`, `RegistryTest\\`, `SourceTest\\`, `PackageTest\\`, `PublicationTest\\` and `WorkerTest\\` are no longer used.

## Directory layout

```
test/
  Unit/
    Access/
    Admin/
    Application/
    Architecture/
    Audit/
    Common/
    Identity/
    Organization/
    Package/
    Publication/
    Registry/
    Release/
    Rest/
    Shared/
    Source/
    Support/
    Webhook/
    Worker/
  Integration/
    Admin/
    Application/
    Audit/
    Identity/
    Organization/
    Package/
    Publication/
    Registry/
    Release/
    Source/
    Support/
    Webhook/
    Worker/
  Feature/
    Admin/
    Application/
    Audit/
    Auth/
    Identity/
    Operations/
    Organization/
    Package/
    Profile/
    Registry/
    Rest/
    Security/
    Source/
    Webhook/
    Worker/
  Support/
    Base/
    Builder/
    Compose/
    Docs/
    Fake/
    Fixture/
    Http/
    Release/
    RoadRunner/
    Runner/
```

Use the production namespace shape after the suite name. For example:

| Production area                                                             | Unit test path                                                                         |
| --------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| `Packio\Application\Middleware\CsrfValidationMiddleware`                    | `test/Unit/Application/Middleware/CsrfValidationMiddlewareTest.php`                    |
| `Packio\Registry\Infrastructure\Authorization\RegistryProtocolAuthorizer`   | `test/Unit/Registry/Infrastructure/Authorization/RegistryProtocolAuthorizerTest.php`   |
| `Packio\Registry\Infrastructure\Middleware\RequireRegistryAccessMiddleware` | `test/Unit/Registry/Infrastructure/Middleware/RequireRegistryAccessMiddlewareTest.php` |
| `Packio\Source\Application\Service\SourceOAuthService`                      | `test/Unit/Source/Application/Service/SourceOAuthServiceTest.php`                      |
| `Packio\Source\Application\Service\SourceOAuthTokenResolver`                | `test/Unit/Source/Application/Service/SourceOAuthTokenResolverTest.php`                |
| `Packio\Source\Application\Service\SourceSecretResolver`                    | `test/Unit/Source/Application/Service/SourceSecretResolverTest.php`                    |
| `Packio\Source\Application\Service\SourceAuthorizationInvalidationService`  | `test/Unit/Source/Application/Service/SourceAuthorizationInvalidationServiceTest.php`  |
| `Packio\Source\Infrastructure\Mapper\ProviderAuthorizationDtoMapper`        | `test/Unit/Source/Infrastructure/Mapper/ProviderAuthorizationDtoMapperTest.php`        |
| `Common\Infrastructure\HttpClient\UpstreamHttpClient`                   | `test/Unit/Common/Infrastructure/HttpClient/UpstreamHttpClientTest.php`                |

Feature tests are grouped by user-facing workflow, not by class name.

## PHPUnit suites

`phpunit.xml.dist` defines these suites:

| Suite         | Directory                                       | Purpose                                   |
| ------------- | ----------------------------------------------- | ----------------------------------------- |
| `unit`        | `test/Unit`                                     | Fast isolated tests without real app boot |
| `integration` | `test/Integration`                              | Adapter/service contract tests            |
| `feature`     | `test/Feature`                                  | Real HTTP application flows               |

There is no active `legacy` suite.

## Commands

| Command                       | What it runs                                                                                                                      |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `composer test`               | `unit`, `integration`, `feature`                                                                                                  |
| `composer test:base`          | `unit`, `integration`, `feature`, excluding `slow`; used by `composer check`                                                      |
| `composer test:unit`          | Unit suite, excluding `slow`                                                                                                      |
| `composer test:integration`   | Integration suite, excluding `slow`                                                                                               |
| `composer test:feature`       | Feature suite, excluding `slow`                                                                                                   |
| `composer test:concurrency`   | PostgreSQL concurrency and locking tests                                                                                          |
| `composer test:regression`    | Feature regression subset for security/auth/registry/webhook                                                                      |
| `composer test:coverage`      | Active suites with coverage output                                                                                                |
| `npm run test:front`          | Frontend Vitest unit/component tests                                                                                              |
| `npm run test:front:ci`       | Frontend Vitest tests with CI-compatible JUnit output                                                                             |
| `npm run test:front:watch`    | Frontend Vitest watch mode                                                                                                        |
| `npm run test:front:coverage` | Frontend Vitest coverage report                                                                                                   |
| `npm run test:ssr`            | Builds the SSR bundle and smoke-tests its runtime                                                                                 |
| `npm run test:e2e:browser`    | Playwright Inertia v3 browser contract tests; starts its fixture automatically unless `E2E_BASE_URL` targets a running Packio app. Install the browser once locally with `npx playwright install --with-deps chromium` |
| `npm run check:inertia-v3`    | Rejects legacy Svelte syntax and removed Inertia compatibility bridges                                                            |
| `composer test:runner`        | Test bootstrap and PostgreSQL runner contract tests                                                                               |
| `composer check`              | CI backend check: cs-check, rector, phpstan, deptrac, runner tests, base suites and flaky-group check                             |
| `composer openapi-check`      | Regenerates `public/openapi.json` and fails if it differs                                                                         |
| `composer cs-check`           | PHP CS Fixer dry run                                                                                                              |
| `composer cs-fix`             | Apply PHP CS Fixer changes                                                                                                        |

The Inertia v3 server protocol uses the Composer constraints in `composer.json`: `sirix/inertia-psr15 ^3.0`, `sirix/inertia-psr15-mezzio-flash ^1.0`, and `mezzio/mezzio-flash ^1.10`.

## Inertia flash delivery

The web pipeline orders `SessionMiddleware`, `FlashMessageMiddleware`, `CsrfValidationMiddleware`, `StripPackageMappingErrorInertiaDecorationMiddleware`, then `InertiaMiddleware`. Keep session and flash setup before Inertia so one-time notifications are delivered in top-level `page.flash`, never in `page.props.flash` or `page.props.toasts`.

`page.flash.success` and `page.flash.error` drive the layout message. `page.flash.toasts` is delivered through Inertia's native `flash` event; a request may carry multiple toasts. Application handlers use `InertiaNotificationMessenger`, not session keys or Mezzio flash services directly.

Validation remains independent: `page.props.errors` and `page.props.old` are redirect state, not flash data. Old input is filtered before persistence and must never include passwords, API/OAuth tokens, Turnstile/CAPTCHA responses, honeypots, or other secret fields.

Before pushing a testing-system change, run:

```bash
composer check
composer openapi-check
npm run check
```

All Composer PHPUnit commands use `bin/with-postgres-test`. Locally, the runner starts only the isolated `postgres-test` Compose service, waits for PostgreSQL before PHPUnit starts, and stops the service after the command exits. `composer check` therefore requires Docker locally. In CI, the runner validates the PostgreSQL service supplied by the job through the `PACKIO_TEST_DATABASE_*` variables.

The local test database uses these defaults:

| Variable                    | Default       | Purpose                           |
| --------------------------- | ------------- | --------------------------------- |
| `PACKIO_TEST_DATABASE_PORT` | `15433`       | Host port forwarded to PostgreSQL |
| `PACKIO_TEST_DATABASE_NAME` | `packio_test` | Dedicated test database           |
| `PACKIO_TEST_DATABASE_USER` | `packio_test` | Dedicated test user               |
| `PACKIO_TEST_DATABASE_PASS` | `packio_test` | Dedicated test password           |

Inspect the local test service with `docker compose --profile test logs postgres-test`. For independent runs, provide a dedicated Compose file with service `postgres-test` through `COMPOSE_FILE`, a unique `COMPOSE_PROJECT_NAME`, and a free `PACKIO_TEST_DATABASE_PORT`. The runner starts and stops only that service. The standard `postgres-test` has no volume; where a custom Compose file or the old `postgres-test-data` volume from earlier checkouts leaves one, remove volumes only from the explicitly selected disposable project; never select every test volume on the host by a shared label.

For changes that touch feature routing, auth, registry or webhooks, also run:

```bash
composer test:regression
```

## Frontend tests

Frontend tests live beside the Svelte and TypeScript code under `resources/js` using `*.test.ts` files. Shared test harness code lives under `resources/js/test`.

Use frontend tests for browser-side behavior that backend feature tests cannot protect:

- Inertia router payloads and navigation options
- CSRF propagation in `apiClient` and the native Inertia HTTP pipeline
- Svelte component state, timers, clipboard, local/session storage and modal behavior
- Auth form gating, source connection flows, organization settings and registry token actions

Prefer user-visible queries by role, label or text. Keep backend routing, database and Inertia payload generation covered by PHP feature tests.

## Choosing the right test level

### Use Unit when

- The class can be constructed directly.
- The behavior is a branch, mapping, validation rule or response decision.
- You can describe the expected result without booting the application.
- The test uses fake PSR requests, fake sessions, DTOs, builders or mocks.

Examples:

- Middleware decisions: CSRF, rate limiting, request context, registry access
- Page data builders
- OAuth/session handlers
- Source OAuth ownership, token resolution, authorization invalidation, and auth-health mapping
- Worker command and factory behavior
- Registry resolver and handler edge cases

### Use Integration when

- The behavior is a contract between an application service and an adapter.
- The code coordinates provider-specific collaborators.
- You need realistic HTTP transport fakes or provider payloads.
- The important assertion is "this adapter speaks the expected protocol".

Examples:

- GitHub/GitLab provider resource fetching
- Webhook registrar contracts
- Source webhook auto-registration
- Publication synchronizers

### Use Feature when

- The behavior is user-visible HTTP behavior.
- Routing, middleware order, auth, session, database state or Inertia payloads matter.
- You need to catch regressions across multiple modules.

Examples:

- Login/register/password reset/email verification flows
- Organization membership and RBAC flows
- Registry composer endpoints
- Webhook controller behavior
- Source, package, worker and admin pages/actions

Do not put direct handler tests in `Feature`. If a test manually creates a handler and mocks its dependencies, it belongs in `Unit` even if the handler returns HTTP responses.

## Base test cases

### `UnitTestCase`

Use `PackioTest\Support\Base\UnitTestCase` when a unit test benefits from shared response helpers:

```php
final class ExampleMiddlewareTest extends UnitTestCase
{
    #[Test]
    public function itReturnsForbidden(): void
    {
        $response = $middleware->process($request, $handler);

        $this->response($response)
            ->assertStatus(403)
            ->assertJsonPath('error', 'Forbidden')
        ;
    }
}
```

The helper wraps PSR-7 responses in `TestResponse`, so status/header/json assertions stay consistent with feature tests.

### `IntegrationTestCase`

Use `PackioTest\Support\Base\IntegrationTestCase` when the test needs the application container or database lifecycle. Each PHPUnit process creates one isolated PostgreSQL schema and runs migrations once. Every test still gets a fresh container, cache, session and rate-limit paths; its database state is reset with `TRUNCATE … RESTART IDENTITY CASCADE` during teardown. The merged base configuration and PostgreSQL cleanup statement are reused within a process, while per-test overrides remain copy-on-write. The owning process drops the schema and removes test artifacts at shutdown. The test PostgreSQL service runs without `fsync` on tmpfs and test connections use `synchronous_commit=off`; data is not kept after the container stops.

Most integration tests should still prefer explicit fakes over real network or filesystem dependencies.

### `FeatureTestCase`

Use `PackioTest\Support\Base\FeatureTestCase` for real HTTP flows:

```php
final class ExampleFeatureTest extends FeatureTestCase
{
    #[Test]
    public function itReturnsJsonPayload(): void
    {
        $this->get('/packages.json', [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('metadata-url', '/p2/%package%')
        ;
    }
}
```

`FeatureTestCase` boots the Mezzio application once per test, wires `config/pipeline.php` and `config/routes.php`, persists response cookies between requests and can load/attach CSRF tokens.

Useful helpers:

| Helper                                         | Purpose                                             |
| ---------------------------------------------- | --------------------------------------------------- |
| `get($uri, $headers = [])`                     | Send GET request                                    |
| `post($uri, $data = [], $headers = [])`        | Send form POST request                              |
| `postJson($uri, $payload = [], $headers = [])` | Send JSON POST request                              |
| `withCsrf()`                                   | Load and attach the canonical header and same-origin test headers for unsafe cookie-authenticated web requests |
| `withHost($host)`                              | Simulate subdomain/registry hosts                   |
| `clearCookies()`                               | Reset cookie state inside a test                    |
| `getService($id)`                              | Pull a service from the test container              |

## Response assertions

`PackioTest\Support\Http\TestResponse` is the shared assertion wrapper for HTTP responses.

Common methods:

| Method                                            | Purpose                                                 |
| ------------------------------------------------- | ------------------------------------------------------- |
| `assertStatus(200)`                               | Exact status assertion                                  |
| `assertOk()`                                      | Any 2xx response                                        |
| `assertRedirect('/target')`                       | Any 3xx response, optionally containing target location |
| `assertHeader('Name', 'value')`                   | Header presence/value                                   |
| `assertJsonPath('props.user.id', '123')`          | Dot-path JSON assertion                                 |
| `assertInertiaComponent('Auth/Login')`            | Inertia component assertion                             |
| `assertInertiaProp('errors.email.0', 'Required')` | Inertia prop assertion                                  |
| `json()`                                          | Decode response body as array                           |
| `body()`                                          | Raw response body                                       |
| `response()`                                      | Underlying PSR-7 response                               |

Prefer `TestResponse` over repeating `getStatusCode()`, `json_decode()` and `getHeaderLine()` in new tests.

## Test support

### Builders

Builders in `test/Support/Builder` create DTO-like arrays or objects with sensible defaults and override points. Use them when a test needs meaningful domain data but the exact fields are not the subject of the test.

Available builders:

- `ReleaseBuilder`
- `JobBuilder`

### Fakes

Fakes live under `test/Support/Fake` and should be preferred over interaction-heavy mocks when the assertion is state/result based.

Available fakes:

| Fake                        | Purpose                                               |
| --------------------------- | ----------------------------------------------------- |
| `InMemorySession`           | Session storage for handler/unit tests                |
| `FakeRateLimiter`           | Queueable rate limiter result and captured attempts   |
| `InMemoryRateLimiter`       | Stateful in-memory rate limiter                       |
| `InMemoryPackageArchiveStorage` | Package archive storage kept in memory            |
| `RecordingAuthMailer`       | Auth mailer that records sent messages                |
| `SecretEncryptorStub`       | Secret encryptor stub                                 |

See `test/Support/Fake` for the rest (collaborator fakes and fixtures).

Use PHPUnit mocks when the interaction itself is the behavior being verified, such as "this collaborator is never called" or "this token is attached to this response".

### Webhook helpers

`PackioTest\Support\Http\WebhookTestHelpers` is a trait that builds signed provider webhook requests and decodes webhook responses. Use it in webhook feature/integration tests instead of duplicating signature logic.

## Feature test data

Feature tests use the isolated PostgreSQL schema managed by `IntegrationTestCase`; migrations run once per PHPUnit process and state is cleared after every test. Prefer creating state through public APIs/facades where possible:

```php
/** @var IdentityApiFacadeInterface $identity */
$identity = $this->getService(IdentityApiFacadeInterface::class);

$user = $identity->createUser(
    'user-' . bin2hex(random_bytes(6)) . '@example.test',
    'User',
    'Password1!',
);
```

When a test needs very specific persistence state, keep direct repository setup local to the test and explain it through the test name rather than comments.

## CSRF and sessions

Use `withCsrf()` only for unsafe cookie-authenticated web requests that pass
through the real HTTP pipeline. It loads the token from the Inertia
`csrf.token` prop and attaches the canonical `X-CSRF-Token` request header,
plus same-origin browser headers required by the CSRF policy. It does not add a
body token or mask negative tests that intentionally omit a token or source
header.

```php
$this->withCsrf()->post('/auth/login', [
    'email' => 'user@example.test',
    'password' => 'Password1!',
])->assertRedirect('/organizations');
```

`FeatureTestCase` stores cookies between requests, so flows such as login -> page load -> form submit can be expressed in one test without manual cookie handling.

Do not use `withCsrf()` for REST API Bearer or `X-API-TOKEN` tests, Composer
registry tests, or signed inbound webhook tests. Each uses its own
authentication or signature policy and does not receive a browser-session CSRF
exemption merely because of its path. Use synthetic placeholder values in test
fixtures and assert that these values never enter captured logs or error
context.

## Subdomain and registry tests

Registry tests often need a subdomain host:

```php
$this->withHost($organization->slug . '.packio.us');

$this->get('/packages.json')->assertOk();
```

Use feature tests for public/private registry behavior that depends on host, auth headers, package visibility and generated Composer metadata. Use unit tests for parsing/auth-policy branches inside registry middleware and handlers.

## External providers

Tests must not perform real network calls in the normal suite. Provider tests should inject a Guzzle `MockHandler` (with request history) behind `UpstreamHttpClient` to assert:

- requested endpoint path
- headers and auth tokens
- request payload
- mapped provider response data
- error/empty response behavior

When adding a new provider, add an integration contract test next to existing provider tests under `test/Integration/Source/Infrastructure/Provider`.

## Adding a new test

1. Pick the suite by behavior level: unit, integration or feature.
2. Place the file under the matching namespace path.
3. Use `PackioTest\\...` namespace.
4. Prefer existing builders/fakes/helpers before adding new test-only code.
5. Use `FeatureTestCase` for real HTTP flows and `TestResponse` for response assertions.
6. Add regression coverage near the behavior being changed.
7. Run the smallest relevant command first, then `composer test` before handing off.

## Files

| File                                        | Purpose                                               |
| ------------------------------------------- | ----------------------------------------------------- |
| `phpunit.xml.dist`                          | PHPUnit suites and source configuration               |
| `composer.json`                             | Test, style and quality scripts                       |
| `test/Support/bootstrap.php`                | Test bootstrap and environment defaults               |
| `test/Support/Base/UnitTestCase.php`        | Unit response helpers                                 |
| `test/Support/Base/IntegrationTestCase.php` | Container, PostgreSQL schemas, migrations and cleanup |
| `test/Support/Base/FeatureTestCase.php`     | HTTP client over real Mezzio app                      |
| `test/Support/Http/TestResponse.php`        | Shared HTTP response assertions                       |
| `test/Support/Builder`                      | Test data builders                                    |
| `test/Support/Fake`                         | Test fakes                                            |

## Reproducible public checks

`.github/workflows/verify.yml` runs on disposable GitHub runners for pull requests, including forks, with read-only repository permissions and no deployment or publication credentials. Backend base and concurrency jobs each have their own PostgreSQL17 service. Frontend verification uses Node24.21.0 and the Playwright1.65.1 browser image matching `package-lock.json`. Missing Docker, browser or database infrastructure fails the required job. Production targets `prod`, `prod-worker`, and `prod-ssr` are built once, then verified by saved immutable IDs and candidate SHA. The same worker ID is passed to cold-worker and service supervision checks. The runtime job then runs the product check described below on those images; verification publishes nothing on pull requests and branch pushes; on a release tag the `publish` job pushes the already verified images after every other job has passed. The runtime report and image export are retained together. The runtime job is the expensive one: it runs only for manual runs of the workflow and for pushed `v*` tags, not for pull requests or ordinary pushes to `main` and `develop`.

Commit the root lock and all six `tools/*/composer.lock` files when preserving reviewed changes. Tool vendors remain ignored. On a fresh checkout, `composer install` installs the root and tools from those locks. Validate the root with `composer validate --strict`, and each tool with `composer --working-dir=tools/TOOL validate --no-check-publish`; run `check-platform-reqs` and raw `audit --locked` for both root and tools. Audit failures require resolution, including abandoned packages.

`composer test:runner` includes the bootstrap regression and the PostgreSQL runner contract. The bootstrap selects the repository root even when invoked from another working directory. Integration configuration loads the actual ConfigAggregator inside its own temporary directory, preserving the application cache; per-test artifact paths and shutdown cleanup belong only to the test run. PHPUnit stores result caches under ignored `test/data/cache/phpunit`. Tests do not remove runtime `data/cache/config-cache.php`.

`composer check` includes formatter coverage for `src`, `test`, `config`, `bin` and migrations, static and architecture checks, runner tests and the base suites. `composer test:concurrency` runs separately. `npm run check` executes the frontend unit/component suite once through `test:front:ci`, producing `data/coverage/vitest.junit.xml`, then SSR, browser, format and build checks. Run `sh test/Support/Release/image-tags.test.sh` to check the release tag to image tag mapping used by the `publish` job. Run `sh test/Support/Docs/links.test.sh` to check local documentation destinations; it excludes anchors and external URLs. `sh test/Support/Compose/dev-compose.test.sh` checks the dev `compose.yaml` setup services; CI runs these after `composer check` and `composer openapi-check`.

## Product check on final images

`test/Support/Release/product.test.sh` starts an isolated production Compose stack from three existing images and checks the product through it:

```bash
npm ci
sh test/Support/Release/product.test.sh "$APP_IMAGE" "$WORKER_IMAGE" "$SSR_IMAGE"
```

It needs Docker, `git`, `openssl`, `jq` and Internet access (Docker pulls PostgreSQL 17, Mailpit, Caddy, Composer 2.8 and the Playwright 1.65.1 image when they are missing; the last step loads Cloudflare Turnstile). It never builds the candidate images. Each run uses its own Compose project and temporary directory with a disposable CA, random credentials and a tagged Git repository of `packio-fixture/private-package`, and removes them on exit. The run takes about five minutes.

The script drives the stack and runs the scenarios in `test/Support/Release/product.spec.ts` one group at a time, in a Playwright container on the stack's network:

1. With the worker stopped, a registration produces no authentication mail.
2. With the worker running, a new user registers on the server-rendered pages, takes the code from Mailpit and verifies the address. The admin area is closed to that user until `user:make-admin` grants the role.
3. The user creates an organization and two Generic Git sources. Synchronizing a missing repository must end as a failed job; the real one must store the archive of version `1.0.0`.
4. After the worker container is recreated, a re-sync started from the source page must complete.
5. Registry tokens are issued and revoked through the session and CSRF protected pages. Metadata and the archive are served to the organization's token, and refused to anonymous clients (401), a token of another organization (403) and a revoked token (401).
6. Composer 2.8 installs the package with an empty cache; the installed files must equal the fixture and `Probe::value()` must run. Installs without a token, with another organization's token, with a revoked token and with the token stored under the wrong hostname must fail with the same statuses.
7. The edge is [the documented Caddy example](examples/reverse-proxy/Caddyfile). Requests through it must carry the security headers and a `Secure` session cookie; forged `X-Forwarded-*`, `Forwarded`, `CF-Connecting-IP` and `X-Real-IP` values must not change registry URLs, pass the CSRF origin check or reset login throttling; a client that bypasses the edge must not pass as HTTPS.
8. With Turnstile enabled on Cloudflare's public test keys, the login page must hydrate, load the real widget and navigate without violating the report-only Content Security Policy.

The runtime job runs this check on the image IDs it verified earlier in the job, with no skip or allowed failure. The PHPUnit feature suite and the Playwright Inertia protocol fixtures remain separate checks.

## Distribution notices

[THIRD_PARTY_NOTICES.md](../THIRD_PARTY_NOTICES.md) records the bundled assets and
their licenses. After installing the locked frontend dependencies, refresh the
Swagger assets and their upstream license and notice files, then check the
built images:

```bash
npm run openapi-assets
sh test/Support/Release/notices.test.sh "$APP_IMAGE" "$WORKER_IMAGE" "$SSR_IMAGE"
```

The check compares the license and notice files in each image with the
checkout. It requires the Debian copyright files in every image, the protobuf
licenses in the app and worker images and the Node.js license in the SSR image,
and it requires the Swagger supplement to name the locked `swagger-ui-dist`
version.

The [Swagger supplement](../public/api-doc/assets/THIRD_PARTY_NOTICES.md) retains
the complete dependency and embedded-code terms for the verified bundle bytes.
Its inventory comes from the exact upstream source and locked emitted modules;
the source maps shipped in the npm package do not describe the current bundles.
Changing Swagger requires verifying the new emitted inventory and terms, then
updating the supplement.

The images do not ship third-party sources. PHP 8.5.13 (including bundled
libmbfl) is built from the official `php-8.5.13.tar.xz` and protobuf from the
PECL archive; the `php-build` stage of `.docker/Dockerfile.roadrunner` verifies
both by SHA-256. The Debian packages are unmodified: `dpkg-query -W` in an image
lists them, and `apt-get source <package>=<version>` or snapshot.debian.org
provides the matching source package.

The upstream PHP tarball contains generated `configure`, all PHP/mbstring/libmbfl source
and build definitions. To rebuild with a modified libmbfl, extract the archive
and edit `ext/mbstring/libmbfl/`. Use the build dependencies, configure options,
`make` and `make install` commands in the recipe's `php-build` stage. In a
separate copy of that stage, supply your source in `/usr/src/php` in place of
`docker-php-source extract`. For an unmodified source rebuild, place the pinned
PHP archive in an isolated build context and add
`COPY php-8.5.13.tar.xz /usr/src/php.tar.xz` immediately after the stage's `FROM`;
then its existing extraction and compilation commands consume that exact
source. The resulting `mbstring.so` contains the rebuilt libmbfl and can be
used in a derivative runtime in place of the original extension. The existing
stage also supplies PHP's other enabled extension build configuration; no
Packio application build or Git history is needed for this PHP rebuild.
