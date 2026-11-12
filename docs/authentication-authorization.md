# Authentication and Authorization

## Overview

Packio uses two separate access models:

- Web application access is session based. Users authenticate through email/password or OAuth, the authentication layer resolves a user actor, and handlers declare access requirements with attributes.
- Composer registry access is token based. Composer requests authenticate with registry tokens, or skip authentication only when anonymous access is explicitly allowed for the requested public package.

Authentication answers "who is this request?". Authorization answers "may this actor do this action in this organization?". Keep those concerns separate when adding new flows.

## Web authentication flow

```
SessionMiddleware
    -> OptionalRouteAuthenticationMiddleware
    -> DatabaseUserActorProvider
    -> UserActor
    -> ResolveCurrentUserMiddleware
    -> RequestContextAttributes::CURRENT_USER
    -> handler attributes and handler logic
```

The web application uses `sirix/mezzio-authentication` with cookie transport and session storage. The main configuration lives in `config/autoload/authentication.global.php`.

Important details:

- The auth cookie is named `packio_authentication`.
- Auth tokens are stored in session under the `_authentication.tokens.` prefix.
- The token payload contains `user_id` and `credentials_fingerprint`.
- `DatabaseUserActorProvider` loads the user from the database and returns `UserActor`. It rejects a session token whose `credentials_fingerprint` no longer matches the user, so a password change ends other sessions. `ChangePasswordHandler` and OAuth login issue a fresh payload.
- `OptionalRouteAuthenticationMiddleware` picks the route's auth profile. On API-token routes it skips the session profile; `#[ApiAuthenticated]` runs its own optional authentication.
- `UserActor` exposes normalized roles and may carry the already mapped `UserDto`.
- `ResolveCurrentUserMiddleware` stores the resolved `UserDto` and user id in request context.
- If the auth layer says the request is authenticated but the user cannot be resolved anymore, `ResolveCurrentUserMiddleware` logs the request out and redirects to `/auth/login`.

### Login

`LoginHandler` checks credentials through `SignInWorkflow::handle()`. On success it calls `AuthManagerInterface::login()` with the current request, redirect response and payload:

```php
[
    'user_id'                 => $signInResult->userId,
    'credentials_fingerprint' => $this->identityQueryService->credentialsFingerprint($signInResult->userId),
]
```

`AuthManagerInterface` creates the token in the configured transport storage and attaches it to the redirect response.

Unverified users are not logged in. They are redirected to `/auth/verification`, and the pending user id is stored in session for the verification flow.

Banned users are logged in and redirected to `/account/banned` (see [Banned accounts](#banned-accounts)).

### Logout

`LogoutHandler` requires `#[Authenticated]` and delegates token removal to `AuthManagerInterface::logout()`. The injected interface is decorated by `CsrfAwareAuthManager`, which clears the CSRF session value only after a successful logout. The logout response does not publish a replacement token; the next page load creates a new one when needed.

### OAuth login

OAuth is part of authentication only when it creates or resolves a Packio user session. OAuth provider tokens stored for source access are not web-session credentials and must not be treated as proof that the request is authenticated.

OAuth login handlers use `#[GuestOnly]`. Source OAuth connect handlers use `#[Authenticated]` because they attach provider credentials to an already authenticated Packio user and organization context.

## CSRF for cookie-authenticated web requests

Packio uses a session-bound synchronizer token for the same-origin web UI.
The server stores one unpredictable token in the session and shares it only as
the Inertia `csrf.token` prop. The browser returns it only in
`X-CSRF-Token` on `POST`, `PUT`, `PATCH`, and `DELETE`. The token remains
valid for successive or concurrent requests in the same session; it is not
consumed or rotated after each mutation.

The middleware permits `GET`, `HEAD`, and `OPTIONS` without a token. These
methods must not change state. For every other non-exempt route, it first
requires an exact source-origin match and then a valid session token:

- `Origin` is compared first; when it is absent, `Referer` is the fallback.
- The scheme, host, and effective port must exactly match the request origin.
  Subdomain or suffix matching is not accepted, so organization subdomains are
  separate origins.
- Missing, malformed, or mismatched source headers or tokens return the
  established empty `419` response without reflecting the submitted value.
  The client reloads the current page and does not automatically replay a
  mutation.

Only explicit route metadata can exempt an unsafe route. `#[ApiAuthenticated]`
marks stateless API-token routes as exempt, and `#[CsrfExempt]` is used for the
public inbound webhook endpoint, which has its own signature/provider
validation. A URL prefix, a broad HTTP-method rule, or a cookie-authenticated
webhook reprocess route is not an exemption.

`CsrfAwareAuthManager` rotates the token after a successful web login or
session-authentication transition and clears it after logout or session
invalidation. It must never be
put in a URL, cookie, JSON response field, logs, error-reporting context, or a
response-refresh header. `SameSite=Lax`, `HttpOnly`, and production `Secure`
cookie flags remain additional protections; they do not replace CSRF
validation.

## Route-level access attributes

Handlers should declare their access requirements close to the route:

```php
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Authenticated]
#[Can('packages.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get('/organization/:organization/packages', name: 'packages.index')]
final readonly class PackagesHandler implements RequestHandlerInterface
{
    // ...
}
```

Use these attributes consistently:

| Attribute | Purpose |
|-----------|---------|
| `#[Authenticated]` | The route requires a logged-in web user |
| `#[GuestOnly]` | The route is only for unauthenticated users, for example login/register/password reset pages |
| `#[Can(...)]` | The route requires an RBAC permission, optionally evaluated with request context |

Most protected web routes should use both `#[Authenticated]` and `#[Can(...)]`. The common exception is a route where being logged in is enough and no organization/resource permission applies, such as profile management.

## Request context

The pipeline resolves request context before dispatch (auth-related steps only; see `config/pipeline.php` for the full list):

```
RouteMiddleware
    -> SessionMiddleware
    -> CsrfValidationMiddleware
    -> ApiAuthenticationAttemptRateLimitMiddleware
    -> OptionalRouteAuthenticationMiddleware
    -> RefreshAuthenticatedSessionMiddleware
    -> ResolveCurrentUserMiddleware
    -> RequireUnbannedUserMiddleware
    -> RequireEmailVerificationMiddleware
    -> ResolveRegistryOrganizationAttributeMiddleware
    -> ResolveCurrentOrganizationMiddleware
    -> ResolveCurrentMembershipMiddleware
    -> ResolveOrganizationActorContextMiddleware
    -> HandleAuthorizationExceptionMiddleware
    -> DispatchMiddleware
```

Use `RequestContextAttributes` instead of reading raw request attributes directly.

## Banned accounts

An administrator can indefinitely ban a user account with a recorded reason. The
account remains authenticated so the next web request can show the restriction
rather than silently logging the user out. `RequireUnbannedUserMiddleware` runs
immediately after current-user resolution and before email verification,
organization context, RBAC, and handler dispatch.

A banned user may only request `GET /account/banned` and `POST /auth/logout`.
All other web requests redirect to `/account/banned`, including requests from an
already-open session. The ban page renders the saved reason as text and offers
only logout. Once an administrator unbans the account, the same session resumes
normal access on its next request; roles, memberships, and credentials are not
changed by either action.

REST API and Composer registry credentials are evaluated independently of the
web gate. See the REST API contract for the API error; a private registry request
whose token owner is banned receives `403`. Anonymous access to a genuinely
public package is unchanged.

| Context value | Meaning |
|---------------|---------|
| `CURRENT_USER` | Current `UserDto`, or `null` |
| `CURRENT_USER_ID` | Current user id, or `null` |
| `CURRENT_ORGANIZATION_SLUG` | Organization slug from the route or registry host |
| `CURRENT_ORGANIZATION` | Resolved `OrganizationDto`, or `null` |
| `CURRENT_MEMBERSHIP` | Current user's membership in the current organization, or `null` |
| `ORGANIZATION_ACTOR_CONTEXT` | Domain `ActorContext` for organization write operations, or `null` |
| `REGISTRY_ACTOR` | Registry token actor for Composer registry requests |

Handlers should call:

- `RequestContextAttributes::currentUser($request)` when a missing user is valid.
- `RequestContextAttributes::requireCurrentOrganization($request)` when the route must already have a valid organization.
- `RequestContextAttributes::currentMembership($request)` for read-only page payloads and policy hints.
- `RequestContextAttributes::currentOrganizationActorContext($request)` for write operations that need owner/member identity inside application services.

## RBAC roles and permissions

RBAC permissions are configured in `config/autoload/access.global.php` and registered through the Access module.

Current roles:

| Role | Purpose |
|------|---------|
| `user` | Normal authenticated user. Organization permissions are mostly guarded by membership or owner rules. |
| `admin` | Admin user. Grants `admin.*` plus `packages.view` through `OrganizationMemberRule`. |

Current organization rule types:

| Rule | Allows when |
|------|-------------|
| `OrganizationMemberRule` | The request has a current organization and a membership for that organization |
| `OrganizationOwnerRule` | `OrganizationPolicy::canManage()` returns true for current organization and membership |
| `OrganizationExistsRule` | A current organization exists |

Permission names are action oriented:

| Permission family | Typical rule |
|-------------------|--------------|
| `dashboard.view` | user role |
| `profile.manage` | user role |
| `organizations.view` | organization member |
| `organizations.manage` | organization owner |
| `packages.view` | organization member |
| `packages.manage` | organization owner |
| `tokens.view` | organization member |
| `tokens.manage` | organization owner |
| `sources.view` | organization member |
| `sources.manage` | organization owner |
| `organizations.members.view` | organization member |
| `organizations.members.manage` | organization owner |
| `organizations.invitations.manage` | organization owner |
| `webhooks.manage` | organization owner |
| `registry.setup.private` | user role |
| `packages.read.private` (`RegistryProtocolPermissions::PACKAGES_READ_PRIVATE`) | user role |
| `admin.*` | admin role |

When using organization-scoped permissions, pass both organization and membership context to `#[Can]`:

```php
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
```

Do not hard-code owner checks in handlers when an existing RBAC permission can express the route-level requirement. Use direct policy checks for page payload flags and domain-specific decisions after the request is already authorized.

## Domain policies and actor context

RBAC protects route entry. Domain policies protect business decisions and UI capabilities.

Examples:

- `OrganizationPolicy::canManage()` checks whether the current membership is owner of the current organization.
- `PackagePolicy::belongsToOrganization()` and `PackagePolicy::canManage()` keep package operations inside the current organization boundary.
- Admin routes use `#[Can('admin.access')]`, which the `admin` role grants through `admin.*`.

Write operations that need organization ownership should pass `ActorContext` into the application layer instead of passing loose user and organization ids. `ResolveOrganizationActorContextMiddleware` creates this context only when the current organization and current membership match.

Typical pattern:

```php
$organization = RequestContextAttributes::requireCurrentOrganization($request);
$context = RequestContextAttributes::currentOrganizationActorContext($request);

if (! $context instanceof ActorContext) {
    return new RedirectResponse('/organizations');
}

$this->service->performInContext($context, ...);
```

Application services may still enforce ownership internally. For example, owner-only commands should reject a non-owner `ActorContext` even if the route already used `#[Can('...manage')]`.

## Authorization failures

`HandleAuthorizationExceptionMiddleware` catches `Sirix\Mezzio\Rbac\Exception\AuthorizationException` and delegates response creation to `AuthorizationExceptionResponseFactory`.

Behavior:

- JSON or `/api/*` requests receive a `403` JSON response with the public error message.
- Inertia/browser requests for known organization permissions redirect to a safe page with a flash message.
- Owner-only failures usually redirect back to the current organization page with `Owner access is required.`
- Member/view failures usually redirect to `/organizations` with `Access denied.`
- Unknown authorization exceptions are rethrown and handled by the normal error stack.

Keep this centralized. Do not duplicate permission-specific redirect logic in handlers unless the handler is dealing with a domain result rather than an RBAC exception.

## Email verification

`RequireEmailVerificationMiddleware` runs after current user resolution and before organization context resolution.

Authenticated users with unverified email are redirected to `/auth/verification`, except for these exempt paths:

- `/auth/verification`
- `/auth/logout`
- `/auth/login`
- `/auth/register`

This means protected handlers can generally assume that a resolved authenticated web user is email verified, unless they are part of the verification flow itself.

## Composer registry authentication

Composer registry endpoints do not use the web session as their primary credential. They use `RequireRegistryAccessMiddleware` and `RegistryProtocolAuthorizer`.

Supported token transports:

| Header | Token extraction |
|--------|------------------|
| `Authorization: Bearer <token>` | The bearer value is used as the registry token |
| `Authorization: Basic <base64>` | `token:<registry-token>` is supported for Composer HTTP basic auth |

Registry authorization flow:

```
ResolveRegistryOrganizationAttributeMiddleware
    -> ResolveCurrentOrganizationMiddleware
    -> RequireRegistryAccessMiddleware
    -> RegistryProtocolAuthorizer
    -> IdentityApiFacadeInterface::authenticateRegistryAccessToken()
    -> RegistryTokenPermissionEvaluator
    -> handler
```

Important rules:

- `repo.package.metadata` and `repo.package.dist` can be served anonymously only when the current organization has anonymous access enabled and the requested package is not private.
- Anonymous-capable public package requests are checked before token authentication. A stale or invalid `Authorization` header does not block public package metadata/dist when anonymous access is allowed.
- Private registry bootstrap and private package requests require a valid registry token.
- A valid token must have `packages.read.private`.
- A valid token must belong to the resolved organization.
- If the organization is not resolved from the request, a valid token may resolve the organization context from the token's organization.
- Invalid or missing tokens are rate limited through `security.rate_limits['registry_auth']`.
- Valid tokens are not rate limited by the registry-auth failure limiter.

The only current registry protocol permission constant is:

```php
Common\Registry\RegistryProtocolPermissions::PACKAGES_READ_PRIVATE
```

Registry token permissions are evaluated with `Sirix\Mezzio\Rbac\PermissionMatcher`, so permission patterns can be matched consistently with RBAC-style permissions.

## Adding a protected web route

1. Add `#[Authenticated]` unless the route is intentionally public or guest-only.
2. Add `#[Can(...)]` for every route that represents a protected capability.
3. For organization-scoped permissions, pass `CURRENT_ORGANIZATION` and `CURRENT_MEMBERSHIP` context.
4. Add `RequireExistingOrganizationMiddleware::class` to organization routes where a missing/unknown organization should stop the request before handler logic.
5. Use `RequestContextAttributes` inside the handler.
6. Use `ActorContext` for organization write operations.
7. Add or update feature tests for user-visible auth, organization membership, owner-only, admin or registry behavior.

## Adding a new permission

1. Add the permission to `config/autoload/access.global.php`.
2. Choose the narrowest existing rule that matches the behavior, usually `OrganizationMemberRule` for reads and `OrganizationOwnerRule` for writes.
3. If a new rule is needed, add it under `src/Module/Access/src/Infrastructure/Rbac/Rule`.
4. Use the permission through `#[Can('permission.name', ...)]` on handlers.
5. Update `AuthorizationExceptionResponseFactory` only when the permission needs custom browser redirect behavior.
6. Add tests for allowed and denied paths.

When changing config, clear the config cache in environments that use cached configuration:

```bash
composer clear-config-cache
```

## Files

| File | Purpose |
|------|---------|
| `config/autoload/authentication.global.php` | Web authentication transport, session and cookie configuration |
| `config/autoload/access.global.php` | RBAC role-to-permission mapping |
| `config/pipeline.php` | Middleware order for auth, context and authorization handling |
| `src/Module/Identity/src/Infrastructure/Authentication/DatabaseUserActorProvider.php` | Loads authenticated users from auth token payloads |
| `src/Module/Identity/src/Infrastructure/Authentication/UserActor.php` | Auth actor carrying roles and optional current user DTO |
| `src/Application/src/Middleware/ResolveCurrentUserMiddleware.php` | Resolves and validates current web user context |
| `src/Application/src/Middleware/RequireEmailVerificationMiddleware.php` | Redirects unverified authenticated users to verification |
| `src/Application/src/Middleware/ResolveCurrentOrganizationMiddleware.php` | Resolves organization from route/registry context |
| `src/Application/src/Middleware/ResolveCurrentMembershipMiddleware.php` | Resolves current user's organization membership |
| `src/Application/src/Middleware/ResolveOrganizationActorContextMiddleware.php` | Builds organization `ActorContext` for application services |
| `src/Application/src/Infrastructure/Http/RequestContextAttributes.php` | Typed helpers for request context attributes |
| `src/Application/src/Error/AuthorizationExceptionResponseFactory.php` | Central browser/JSON responses for RBAC failures |
| `src/Module/Access/src/Application/Service/AccessRoleRegistrar.php` | Registers configured roles and permissions |
| `src/Module/Access/src/Infrastructure/Rbac/Rule` | Organization-aware RBAC rules |
| `src/Module/Registry/src/Infrastructure/Middleware/RequireRegistryAccessMiddleware.php` | Composer registry token authentication middleware |
| `src/Module/Registry/src/Infrastructure/Authorization/RegistryProtocolAuthorizer.php` | Registry anonymous/token authorization decisions |
| `src/Module/Registry/src/Application/Service/RegistryTokenPermissionEvaluator.php` | Registry token permission matching |
