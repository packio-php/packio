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

`.github/workflows/verify.yml` runs on disposable GitHub runners for pull requests, including forks, with read-only repository permissions and no deployment or publication credentials. Backend base and concurrency jobs each have their own PostgreSQL17 service. Frontend verification uses Node24.21.0 and the Playwright1.62.1 browser image matching `package-lock.json`. Missing Docker, browser or database infrastructure fails the required job. Production targets `prod`, `prod-worker`, and `prod-ssr` are built once, then verified by saved immutable IDs and candidate SHA. The same worker ID is passed to cold-worker and service supervision checks. The stdlib `python3 test/Support/Release/image-harness.test.py` regression mocks orchestration only; real images still undergo layer/startup checks. The runtime job also verifies the real production SSR and proxy browser contract using a matching non-root Playwright server bound to loopback, then removes only its own browser container; verification does not publish images. The runtime report and image export are retained together; registry digests and the publication bundle remain a separate release gate. The runtime job is the expensive one: it runs only for manual runs of the workflow and for pushed `v*` tags, not for pull requests or ordinary pushes to `master`. Run the workflow manually on the candidate before preparing a release.

Commit the root lock and all six `tools/*/composer.lock` files when preserving reviewed changes. Tool vendors remain ignored. On a fresh checkout, `composer install` installs the root and tools from those locks. Validate the root with `composer validate --strict`, and each tool with `composer --working-dir=tools/TOOL validate --no-check-publish`; run `check-platform-reqs` and raw `audit --locked` for both root and tools. Audit failures require resolution, including abandoned packages.

`composer test:runner` includes the bootstrap regression and the PostgreSQL runner contract. The bootstrap selects the repository root even when invoked from another working directory. Integration configuration loads the actual ConfigAggregator inside its own temporary directory, preserving the application cache; per-test artifact paths and shutdown cleanup belong only to the test run. PHPUnit stores result caches under ignored `test/data/cache/phpunit`. Tests do not remove runtime `data/cache/config-cache.php`.

`composer check` includes formatter coverage for `src`, `test`, `config`, `bin` and migrations, static and architecture checks, runner tests and the base suites. `composer test:concurrency` runs separately. `npm run check` executes the frontend unit/component suite once through `test:front:ci`, producing `data/coverage/vitest.junit.xml`, then SSR, browser, format and build checks. Run `python3 test/Support/Docs/links.test.py` to check local documentation destinations; it excludes anchors and external URLs. `python3 test/Support/Compose/dev-compose.test.py` checks the dev `compose.yaml` setup services; CI runs both after `composer check` and `composer openapi-check`.

## Product fixture from final images

`test/Support/Release/product.test.py` starts an isolated production Compose stack from three existing immutable image references. Run the stdlib harness regression first:

```bash
python3 test/Support/Release/product-harness.test.py
```

Supply the exact app, worker and SSR image IDs from the release image report and that report's candidate source SHA:

```bash
docker pull postgres:17-alpine
docker pull caddy:2.11.2-alpine
docker pull axllent/mailpit
docker pull composer:2.8
docker pull mcr.microsoft.com/playwright:v1.62.1-noble
python3 test/Support/Release/product.test.py \
  --app-image "$APP_IMAGE" --worker-image "$WORKER_IMAGE" --ssr-image "$SSR_IMAGE" \
  --expected-sha "$CANDIDATE_SHA" --report data/coverage/product-report.json
```

The product check verifies fresh database, cache and archive storage, migrations and runtime settings seed, the real worker and SSR, a disposable Generic Git HTTPS repository, and TLS trust for the app and registry hosts using a temporary test CA. It requires PostgreSQL 17, Mailpit, Caddy, a Composer 2.8 client, host Node 24.21.0 with the locked npm dependencies installed, and the matching Playwright 1.62.1 browser image. App and worker source metadata and SSR provenance must identify the supplied candidate. Fixture Git archives use UTC so their independently computed hashes are stable across host time zones. The harness never builds or retags candidate images. Application source bind mounts are rejected; only the disposable fixture, test configuration and CA may be mounted.

Each run has unique resource names and a 600 second deadline. Missing infrastructure and unavailable services fail with a safe report. Cleanup removes only that run's resources. Generated credentials, CA keys, cookies and mail contents stay in private temporary files and are excluded from the public report. The report contains candidate/image identities, fixture hashes, durations and scenario results.

The registration and source scenario uses the real SSR pages, hydrated forms and session/CSRF flow. A new user receives the verification code from a queued authentication mail job through Mailpit, verifies the address and signs in. Admin access is denied until the existing `user:make-admin` command grants the role. Organization and source creation use supported application flows; source synchronization must finish and persist the private `packio-fixture/private-package:1.0.0` archive. The check also verifies that a stopped worker prevents mail delivery, a failing provider produces a failed sync job, and a recreated worker with an empty mapper cache can complete another sync.

The harness owns its non-root Firefox container and keeps browser traces, mail bodies, session cookies and credentials out of reports. The browser uses a disposable CA and signed SAN certificate with normal certificate validation. A native Firefox certificate policy is mounted read-only and selected through the matching Playwright image's `PLAYWRIGHT_FIREFOX_POLICIES_JSON` setting. Before registration, a separate browser without that trust must reject the fixture certificate; authenticated flows then use the trusted browser. `start_fixture`, `prepare_dataset`, `verify_private_install` and `stop_fixture` in `product_fixture.py` share the same bounded run and private context for later recovery rehearsal. Registry tokens are issued and revoked through the supported session and CSRF protected HTTP actions. Each Composer case uses a separate disposable Composer 2.8 container, empty cache and private home, a temporary CA, and a mode 0600 `auth.json` keyed by the exact registry hostname including its port.

The private installation must resolve `packio-fixture/private-package:1.0.0` from Packio, execute `Probe::value()` and compare the installed package files with the independently prepared fixture hashes. Direct authenticated metadata and archive downloads verify the same package and archive. A second fresh installation must succeed. Anonymous clients, tokens for another organization and revoked tokens must fail both metadata and archive requests with the existing registry status contract; fresh Composer installations must also fail. Separate controls revoke a token before installation and use the wrong auth hostname, so cached packages or direct upstream access cannot produce a passing result. Tokens, authorization headers and client output stay out of the public report.

The public runtime job requires the complete E1–E3 product check against the same image IDs and candidate SHA verified earlier in the job. Its product step has no infrastructure skip or allowed failure; the early stdlib regression runs before Node/npm setup, and the browser parser self-check runs after actual browser prerequisites are available. The PHPUnit feature suite and Playwright Inertia protocol fixtures remain separate checks.

## Distribution notices

[THIRD_PARTY_NOTICES.md](../THIRD_PARTY_NOTICES.md) records the bundled assets and
their licenses. After installing the locked frontend dependencies, refresh the
Swagger assets and their upstream license and notice files:

```bash
npm run openapi-assets
python3 test/Support/Release/notices.test.py --checkout-only
python3 test/Support/Release/notices.test.py \
  --app-image "$APP_IMAGE" --worker-image "$WORKER_IMAGE" --ssr-image "$SSR_IMAGE" \
  --source-archive "$SOURCE_ARCHIVE"
```

The artifact check reads the actual saved image layers and source tar archive.
It compares notices with the checkout, verifies the installed Swagger version
and asset bytes against the lock, and checks the font and Swagger assets in the
app, worker and source snapshot. The SSR image carries the notices for its
bundle. Archive paths start at the repository root; a source snapshot retains
the project license and third-party notices and is not a production build input
without Git metadata.

The [Swagger supplement](../public/api-doc/assets/THIRD_PARTY_NOTICES.md) retains
the complete dependency and embedded-code terms for the verified bundle bytes.
Its inventory comes from the exact upstream source and locked emitted modules;
the source maps shipped in the npm package do not describe the current bundles.
Changing Swagger requires verifying the new emitted inventory and terms, then
updating the supplement and the checker's version/hash-bound inventory together.

The source snapshot also carries the exact corresponding source for PHP 8.5.11
(including bundled libmbfl), and the complete source and Debian patches for all
installed operating-system source-package versions. Fetch the pinned payloads
into an ignored build directory:

```bash
python3 bin/prepare-notice-sources.py --output-dir data/build/third-party-sources
```

The helper verifies each size and SHA-256 before publishing a file, reuses only
matching regular files and refuses conflicting existing files. The release
source snapshot must contain this directory at the archive root as
`third-party-sources/`, together with `third-party-sources.json`, this document,
the helper, and the exact `.docker/Dockerfile.roadrunner` recipe. The inventory
check streams every source payload's size and hash and checks the supplied
images' actual package versions, runtime binaries and retained license files.
Source metadata alone is insufficient.

The PHP archive contains generated `configure`, all PHP/mbstring/libmbfl source
and build definitions. To rebuild with a modified libmbfl, extract the archive
and edit `ext/mbstring/libmbfl/`. Use the build dependencies, configure options,
`make` and `make install` commands in the recipe's `php-build` stage. In a
separate copy of that stage, supply your source in `/usr/src/php` in place of
`docker-php-source extract`. For an unmodified source rebuild, place the pinned
PHP archive in an isolated build context and add
`COPY php-8.5.11.tar.xz /usr/src/php.tar.xz` immediately after the stage's `FROM`;
then its existing extraction and compilation commands consume that exact
source. The resulting `mbstring.so` contains the rebuilt libmbfl and can be
used in a derivative runtime in place of the original extension. The existing
stage also supplies PHP's other enabled extension build configuration; no
Packio application build or Git history is needed for this PHP rebuild.

### Verify deterministic PHP and protobuf builds

The `php-build` stage pins the PHP 8.5.11 builder image and verifies the PHP
source archive before extracting it. `SOURCE_DATE_EPOCH=1790083926` comes from
that immutable archive's root timestamp, so PHP's embedded build date does not
depend on the current date or Git commit. The same stage downloads protobuf
5.36.2 from the URL in `third-party-sources.json`, checks its size and SHA-256,
and compiles it from the fixed `/usr/src/protobuf-5.36.2` directory. Its two
license files are installed at the runtime paths already checked by the source
and image inventory.

To verify a change to these build inputs, run two uncached builds from
separate temporary contexts and retain each full build log and artifact under
one unique evidence directory. The `php-build` target needs only its Dockerfile,
so each context contains a private copy of that file. The pinned base digest
and the `PACKIO_BUILD_INPUTS_BEGIN`/`PACKIO_BUILD_INPUTS_END` section in each
log bind the installed package versions, architectures and Debian source
packages. Run the shell blocks in one Bash session; they share the private
`run_dir` and cleanup trap.

```bash
set -euo pipefail
mkdir -p data/coverage
run_dir=$(mktemp -d data/coverage/php-build-repro.XXXXXX)
context_a="$run_dir/context-a"
context_b="$run_dir/context-b"
mkdir -p "$context_a" "$context_b"
cp .docker/Dockerfile.roadrunner "$context_a/Dockerfile"
cp .docker/Dockerfile.roadrunner "$context_b/Dockerfile"
image_a=
image_b=
container_a=
container_b=
cleanup() {
  [ -z "$container_a" ] || docker rm "$container_a" >/dev/null 2>&1 || true
  [ -z "$container_b" ] || docker rm "$container_b" >/dev/null 2>&1 || true
  [ -z "$image_a" ] || docker image rm "$image_a" >/dev/null 2>&1 || true
  if [ -n "$image_b" ] && [ "$image_b" != "$image_a" ]; then
    docker image rm "$image_b" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT
docker buildx build --pull --no-cache --load --progress=plain \
  --iidfile "$run_dir/build-a.iid" --file "$context_a/Dockerfile" \
  --target php-build "$context_a" >"$run_dir/build-a.log" 2>&1
image_a=$(cat "$run_dir/build-a.iid")
docker buildx build --pull --no-cache --load --progress=plain \
  --iidfile "$run_dir/build-b.iid" --file "$context_b/Dockerfile" \
  --target php-build "$context_b" >"$run_dir/build-b.log" 2>&1
image_b=$(cat "$run_dir/build-b.iid")
```

Normalize BuildKit's changing step and timestamp prefix, extract the marked
package rows, and require two complete, identical inventories. The normalized
files are retained alongside both logs:

```bash
python3 - "$run_dir/build-a.log" "$run_dir/build-b.log" \
  "$run_dir/build-a-inputs.tsv" "$run_dir/build-b-inputs.tsv" <<'PY'
import pathlib
import re
import sys

def package_rows(path):
    rows = []
    inside = False
    begins = ends = 0
    for raw in pathlib.Path(path).read_text().splitlines():
        line = re.sub(r"^#[0-9]+\s+[0-9]+(?:\.[0-9]+)?\s?", "", raw)
        if line == "PACKIO_BUILD_INPUTS_BEGIN":
            begins += 1
            inside = True
        elif line == "PACKIO_BUILD_INPUTS_END":
            ends += 1
            inside = False
        elif inside and line.startswith("+"):
            continue
        elif inside and line:
            fields = line.split("\t")
            if len(fields) != 5 or any(not field for field in fields):
                raise SystemExit(f"invalid package row in {path}: {line!r}")
            rows.append(line)
    if begins != 1 or ends != 1 or inside or not rows:
        raise SystemExit(f"missing or incomplete package inventory in {path}")
    return rows

first, second = package_rows(sys.argv[1]), package_rows(sys.argv[2])
for rows, output in ((first, sys.argv[3]), (second, sys.argv[4])):
    pathlib.Path(output).write_text("\n".join(rows) + "\n")
if first != second:
    raise SystemExit("builder package inventories differ")
print(f"matched {len(first)} builder package rows")
PY
```

If they differ, the builds did not use identical resolved toolchain inputs and
cannot establish reproducibility. For matching inputs, copy the complete
runtime binaries and retained license files from each build stage and compare
them byte for byte, including debug sections and GNU build IDs:

```bash
image_a=$(cat "$run_dir/build-a.iid")
image_b=$(cat "$run_dir/build-b.iid")
container_a=$(docker create --network none "$image_a")
container_b=$(docker create --network none "$image_b")
docker cp "$container_a:/usr/local/bin/php" "$run_dir/php-a"
docker cp "$container_b:/usr/local/bin/php" "$run_dir/php-b"
docker cp "$container_a:/usr/local/lib/php/extensions/no-debug-non-zts-20250925/protobuf.so" \
  "$run_dir/protobuf-a.so"
docker cp "$container_b:/usr/local/lib/php/extensions/no-debug-non-zts-20250925/protobuf.so" \
  "$run_dir/protobuf-b.so"
docker cp "$container_a:/usr/local/lib/php/doc/protobuf/LICENSE" "$run_dir/LICENSE-a"
docker cp "$container_b:/usr/local/lib/php/doc/protobuf/LICENSE" "$run_dir/LICENSE-b"
docker cp "$container_a:/usr/local/lib/php/doc/protobuf/third_party/utf8_range/LICENSE" \
  "$run_dir/utf8-range-LICENSE-a"
docker cp "$container_b:/usr/local/lib/php/doc/protobuf/third_party/utf8_range/LICENSE" \
  "$run_dir/utf8-range-LICENSE-b"
printf '%s\n' 'PHP 8.5.11' 'protobuf 5.36.2' > "$run_dir/source-versions.txt"
cmp "$run_dir/php-a" "$run_dir/php-b"
cmp "$run_dir/protobuf-a.so" "$run_dir/protobuf-b.so"
cmp "$run_dir/LICENSE-a" "$run_dir/LICENSE-b"
cmp "$run_dir/utf8-range-LICENSE-a" "$run_dir/utf8-range-LICENSE-b"
stat -c '%n %s bytes' "$run_dir"/{php-a,php-b,protobuf-a.so,protobuf-b.so}
sha256sum "$run_dir"/{php-a,php-b,protobuf-a.so,protobuf-b.so}
docker rm "$container_a" "$container_b"
if [ "$image_a" = "$image_b" ]; then
  docker image rm "$image_a"
else
  docker image rm "$image_a" "$image_b"
fi
image_a=
image_b=
container_a=
container_b=
trap - EXIT
```

`docker create --network none` does not start either build container. The
version record comes from the pinned PHP source and the verified protobuf
package metadata; the retained license files are compared with `cmp`. Keep
both build logs, the matched package-input sections, binary sizes and hashes,
and the version and license records in `run_dir`. A mismatch is a failed
reproducibility check; do not update the
runtime hashes to make it pass. Refresh the source or runtime pins only after
the exact source, base, package inventory and configure inputs have been
reviewed and two independent uncached builds produce identical complete
binaries. Candidate-specific OCI metadata remains bound to each image build;
it does not change the stable runtime binary bytes.

Reconstruct any Debian source package from its `.dsc` and adjacent source
payloads with `dpkg-source`; the Debian patch series and build instructions
remain inside that source package. For example:

```bash
dpkg-source -x data/build/third-party-sources/readline_8.2-1.3.dsc \
  data/build/readline-8.2-1.3
```

Keep the complete corresponding-source snapshot available alongside distributed
images. This is the source-material path; it does not create a written source
offer or publish the images or source.

For local R1 worktree verification, the image `--verify-only` and product
commands also accept the pair `--source-identity FILE --build-record FILE`.
This explicit mode requires a frozen dirty snapshot bound to the base HEAD,
full patch, runtime file hashes, three immutable image IDs and their provenance.
It preserves all image and product checks. The default commands require clean
release metadata; worktree results do not certify a clean release candidate.
