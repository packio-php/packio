# OAuth

## Overview

Packio has two OAuth flows that intentionally look similar at the provider level but mean different things inside the application:

- OAuth login authenticates a Packio user and creates a Packio web session.
- Source OAuth connects a source provider account so Packio can read repositories, register webhooks and refresh provider access later.

Do not collapse these flows. A provider OAuth token is not automatically a Packio login session, and a Packio login session is not automatically a source authorization.

## Why the flows are separated

The separation protects three boundaries:

1. Identity boundary: OAuth login proves who the Packio user is. It creates or resolves `User` and `UserOAuthAccount`, then issues a Packio session token.
2. Source-access boundary: Source OAuth proves that the already logged-in Packio user granted provider API access for source operations. It creates or updates `SourceOAuthAccount`.
3. Organization boundary: A source connection can only become usable for an organization after an owner creates a `ProviderAuthorization` from that `SourceOAuthAccount`.

This avoids several bad states:

- A user cannot become logged in just because a source OAuth callback completed.
- A source provider token cannot be used across users.
- A source provider token cannot be attached to an organization without `sources.manage` permission.
- Login scopes can stay small while source scopes can request repository/webhook permissions.
- Self-hosted provider instances can use organization-specific OAuth client configuration without changing the global login provider.

## Entities

| Entity | Module | Purpose |
|--------|--------|---------|
| `User` | Identity | Packio account used for web authentication |
| `UserOAuthAccount` | Identity | External OAuth identity linked to a Packio user for login/profile purposes |
| `SourceOAuthAccount` | Source | Provider API credentials granted by a logged-in user for source operations |
| `ProviderAuthorization` | Source | Organization-scoped provider authorization used by sources, resource loading and webhooks |
| `SourceProviderInstance` | Source | Organization-owned self-hosted provider configuration, for example GitLab self-hosted |

## OAuth login flow

OAuth login starts from a guest route and ends by attaching a Packio authentication token to the response.

```
GET /auth/oauth/:provider/redirect
    -> OAuthRedirectHandler
    -> store oauth_state
    -> optionally store oauth_redirect_target
    -> optionally store PKCE verifier
    -> redirect to provider

Provider callback
    -> GET /auth/oauth/:provider/callback
    -> OAuthCallbackHandler
    -> validate state
    -> exchange code for provider token
    -> fetch provider user
    -> resolve/create Packio user
    -> persist UserOAuthAccount (provider identity and scopes, no tokens)
    -> AuthManagerInterface::login() creates a Packio auth token with user_id and credentials_fingerprint
    -> AuthManagerInterface attaches the token to the redirect response
```

### Login routes

| Route | Handler | Auth attribute | Purpose |
|-------|---------|----------------|---------|
| `GET /auth/oauth/:provider/redirect` | `OAuthRedirectHandler` | `#[GuestOnly]` | Start provider login |
| `GET /auth/oauth/:provider/callback` | `OAuthCallbackHandler` | none | Provider callback, validates state itself |
| `GET /auth/oauth/email` | `OAuthEmailHandler` | `#[GuestOnly]` | Ask for email when provider did not return one |
| `POST /auth/oauth/email` | `OAuthEmailSubmitHandler` | `#[GuestOnly]` | Complete email-required registration |

The callback route is not marked `#[GuestOnly]` because provider callbacks need to be accepted and then classified by state. Normal login callback state is handled as login. Source-connect callback state is forwarded to the source callback route.

### Login state

`OAuthRedirectHandler` stores a random raw state in session under `oauth_state`.

The login redirect passes the provider a simple state string. The callback validator compares the received state with the stored state using `hash_equals()`.

Codeberg login additionally uses PKCE:

- `OAuthPkce::createCodeVerifier()` generates the verifier.
- The verifier is stored in session as `oauth_pkce_code_verifier`.
- The challenge is sent to the provider.
- The callback consumes the verifier before token exchange.

### User resolution

`BrowserOAuthWorkflow` exchanges the authorization code and fetches provider user data,
then passes them to `OAuthCallbackUseCase`, which owns the login callback business rules.

The workflow and use case:

1. Exchange the authorization code for an OAuth token (`BrowserOAuthWorkflow`).
2. Fetch provider user data (`BrowserOAuthWorkflow`).
3. Look for `UserOAuthAccount` by provider and provider user id.
4. If not found, look for an existing Packio user by provider email.
5. If no user exists and registration is allowed, create a new OAuth user.
6. Persist or update `UserOAuthAccount`.
7. Return `OAuthCallbackResult` (with an `OAuthCallbackStatus`) so the HTTP handler can create a Packio session.

If self-registration is disabled, a new OAuth login is allowed only when:

- the OAuth account already exists;
- a Packio user with the provider email already exists;
- the redirect target is a valid pending organization invitation.

Self-registration is controlled by the runtime setting
`identity.registration_open`. When its value is `inherit`, Packio uses the
`IDENTITY_REGISTRATION_OPEN` environment/config fallback.

### Email-required login

Some providers or provider settings may not return email. For a new OAuth identity this cannot create a valid Packio user immediately.

When provider email is missing:

```
OAuthCallbackUseCase (OAuthCallbackStatus::EmailRequired)
    -> OAuthOutcomePresenter
    -> PendingOAuthEmailContext
    -> OAuthSessionContext::storePendingEmailContext()
    -> redirect /auth/oauth/email
```

The pending context stores provider token data and provider user data in session for 15 minutes. Access and refresh tokens are always encrypted through `SecretEncryptorInterface`. Login stores no provider tokens beyond this short-lived context.

`OAuthEmailSubmitHandler` then:

1. validates Turnstile and request data;
2. rejects an email that already belongs to another Packio account;
3. creates the user without persisting the OAuth account yet;
4. requests email verification;
5. redirects to `/auth/verification`.

The OAuth account is persisted after email verification through the verification flow, not at the moment an unverified email is submitted.

## Source OAuth flow

Source OAuth starts from an authenticated organization route and ends by storing provider credentials as a `SourceOAuthAccount`. It does not create a Packio session.

```
GET /organization/:organization/auth/oauth/:provider/source-redirect
    -> #[Authenticated]
    -> #[Can('sources.manage')]
    -> OAuthSourceRedirectHandler
    -> encode OAuthState action=connect_source
    -> store source context
    -> redirect to provider

Provider callback
    -> GET /auth/oauth/:provider/source-callback
    -> OAuthSourceCallbackHandler
    -> validate raw state from encoded OAuthState
    -> require current Packio user from session
    -> exchange code for source-scoped provider token
    -> persist SourceOAuthAccount
    -> redirect or popup postMessage
```

### Source routes

| Route | Handler | Access | Purpose |
|-------|---------|--------|---------|
| `GET /organization/:organization/auth/oauth/:provider/source-redirect` | `OAuthSourceRedirectHandler` | `#[Authenticated]` + `#[Can('sources.manage')]` | Start source OAuth for an organization |
| `GET /auth/oauth/:provider/source-callback` | `OAuthSourceCallbackHandler` | Callback validates state and current user | Store source OAuth credentials |
| `POST /organization/:organization/sources/authorizations/oauth` | `SourceAuthorizationOAuthStoreHandler` | `#[Authenticated]` + `#[Can('sources.manage')]` | Convert a `SourceOAuthAccount` into organization `ProviderAuthorization` |

The source callback route is public at the route-attribute level because it is called by external providers. It still requires:

- valid OAuth state;
- a current logged-in Packio user resolved from the session;
- a source context stored in session by the protected source redirect route.

### Source state

Source OAuth uses encoded `OAuthState`:

```php
OAuthState::forConnectSource($rawState, $organizationSlug, $provider)->encode()
```

The encoded state contains:

- `s`: raw random state;
- `a`: action, currently `connect_source`;
- `o`: organization slug;
- `p`: provider.

`OAuthCallbackStateValidator` compares the session `oauth_state` with the decoded raw state. `OAuthSourceCallbackHandler` also verifies that the decoded action is `connect_source` and that the decoded provider matches the callback provider.

### Source session context

`OAuthSessionContext` stores source-specific callback data separately from login redirect data:

| Session key | Purpose |
|-------------|---------|
| `oauth_state` | Raw state used for callback validation |
| `oauth_connect_organization` | Organization slug for the source connection |
| `oauth_source_popup` | Whether callback should return popup `postMessage` HTML |
| `oauth_source_provider_instance_id` | Self-hosted provider instance id |
| `oauth_pkce_code_verifier` | PKCE verifier for providers that require it |

The source context is consumed in the callback, so stale callback state does not remain in the session after a successful connection.

### Source credential storage

`SourceOAuthService` exchanges the authorization code and stores credentials in `SourceOAuthAccount`.

For global providers it uses:

- `handleSourceCallback()`;
- source OAuth config under `oauth.<provider>.source`;
- source scopes from the source provider client config.

For self-hosted provider instances it uses:

- `handleSourceProviderInstanceCallback()`;
- the active `SourceProviderInstance` in the current organization;
- OAuth client configuration stored on that provider instance;
- `provider_instance_id` as part of the credential identity.

Stored source OAuth tokens are encrypted by `SourceOAuthAccountSecrets`.

The credential identity is based on:

- Packio user id;
- provider;
- provider user id;
- optional provider instance id.

This lets the same Packio user connect the same provider identity globally and for a self-hosted instance without overwriting the wrong credential.

### Deleting a self-hosted provider instance

An inactive `ProviderAuthorization` still belongs to its GitLab self-hosted
instance and remains a deletion dependency. Organization Settings lists the
sources, active authorizations, and inactive authorizations before attempting
to delete an instance. It exposes only safe summary data and never OAuth
tokens, client secrets, or authorization configuration.

Administrators should reauthorize an inactive authorization when it is still
needed. If an instance has no sources and only inactive authorizations, Settings
offers one confirmed cleanup action that removes those inactive authorizations,
orphan `SourceOAuthAccount` records for that instance, and then the instance.
Sources and active authorizations are never removed by this shortcut. A strict
instance delete remains blocked while any source or authorization exists.

An orphan `SourceOAuthAccount` by itself does not block strict instance deletion;
it is removed as part of the instance lifecycle once sources and provider
authorizations have been resolved.

### Source OAuth token refresh

Source OAuth credentials can be refreshed proactively with the maintenance command:

```bash
php vendor/bin/laminas source:oauth:refresh-maintenance --organization=acme
```

The command inspects active `SourceOAuthAccount` records and refreshes accounts whose provider token expires soon or whose account has been idle long enough to need a keepalive refresh. By default it schedules `source.oauth.refresh` worker jobs; use `--run-inline` only when the refresh should happen inside the command process. Use `--dry-run` to check candidate counts without enqueueing jobs.

The refresh policy is configured under `source_oauth_refresh` and defaults to:

| Key | Default | Meaning |
|-----|---------|---------|
| `refresh_before` | `24 hours` | Refresh tokens expiring within this duration |
| `idle_before` | `30 days` | Refresh accounts not used since this duration |
| `minimum_refresh_interval` | `1 day` | Skip accounts refreshed more recently than this |
| `limit` | `100` | Maximum accounts inspected per command run |

`source_oauth_refresh.providers.<provider>.idle_before` overrides `idle_before` per provider. GitHub defaults to `60 days`; the other providers use `30 days`.

#### Refresh failures

A `SourceTokenRefreshFailedException` with `requiresReauthorization = false` is transient. The `source.oauth.refresh` job fails and is retried, and a source sync fails as retryable. The authorization is not marked invalid.

When the provider requires reauthorization, or the token is missing, the refresh job fails without retry, and a source sync marks the source authorization invalid.

### Popup behavior

Source OAuth can run in popup mode with `popup=1`.

On success or failure, the callback returns a small HTML page that posts this message to `window.opener`:

```json
{
  "type": "packio:source-oauth",
  "success": true,
  "provider": "github",
  "sourceOAuthAccountId": "..."
}
```

Non-popup source OAuth redirects to:

```text
/organization/{organization}/sources?oauth_just_connected=1
```

## Creating provider authorization from source OAuth

A `SourceOAuthAccount` is not itself an organization authorization. It is a user-owned provider credential.

To use it for organization source operations, the frontend posts its id to:

```text
POST /organization/:organization/sources/authorizations/oauth
```

`SourceAuthorizationOAuthStoreHandler` then calls:

```php
SourceApiFacadeInterface::authorizeProviderFromSourceOAuth(...)
```

The application layer verifies:

- the current route has organization owner access through `sources.manage`;
- the `SourceOAuthAccount` exists;
- the account belongs to the current Packio user;
- the provider matches the requested provider;
- the account is active;
- the encrypted access token can be decrypted.

Only after those checks does it create `ProviderAuthorization` for the organization. This is the step that makes a source OAuth credential usable for repository listing, source registration, sync and webhook operations.

## Configuration

OAuth configuration lives under `oauth` in `config/autoload/packio.global.php`.
The `enabled` values below are fallback defaults. Admins can override provider
availability at runtime with `inherit`, `on`, or `off` values in Admin Settings.
See [Runtime Settings](runtime-settings.md).

Each provider has login config:

| Key | Purpose |
|-----|---------|
| `enabled` | Fallback for provider login availability |
| `client_id` | Login OAuth client id |
| `client_secret` | Login OAuth client secret |
| `redirect_url` | Login callback URL |
| `scopes` | Login scopes |
| `authorization_url` | Provider authorization endpoint override |
| `token_url` | Provider token endpoint override |
| `user_url` | Provider user endpoint override |

Each provider also has source config:

| Key | Purpose |
|-----|---------|
| `source.enabled` | Fallback for source OAuth availability for this provider |
| `source.client_id` | Source OAuth client id |
| `source.client_secret` | Source OAuth client secret |
| `source.redirect_url` | Source callback URL override |
| `source.default_redirect_url` | Default `/auth/oauth/:provider/source-callback` URL from `APP_URL` |
| `source.scopes` | Source access scopes, usually broader than login scopes |

`OAuthProviderAvailabilityResolver` exposes this distinction:

| Method | Meaning |
|--------|---------|
| `resolveAuthProvider()` | Provider can be used for login |
| `resolveSourceConnectProvider()` | Provider can be used for source connection |
| `forAuthPages()` | Providers visible on login/register pages |
| `forProvider()` | Availability for an OAuth provider, or `null` |
| `forSourceProvider()` | Availability for a source provider, or `null` |
| `supportsAuthLogin()` | Provider can be used for login |
| `supportsSourceConnect()` | Source provider has an enabled OAuth connection flow |
| `gitLabSelfHostedSourceConnectEnabled()` | GitLab self-hosted source OAuth is switched on |
| `gitLabSelfHostedSourceInstanceAvailable()` | The given self-hosted instance state allows source OAuth |

Runtime availability also requires credentials. For login OAuth, `client_id` and
`client_secret` must be configured. For source OAuth, `source.client_id` and
`source.client_secret` must be configured. Source OAuth does not depend on login
OAuth being enabled.

GitLab self-hosted source OAuth is controlled by the runtime setting
`oauth.gitlab_self_hosted.source.enabled`. It falls back to the config key
`oauth.gitlab.self_hosted.source.enabled`
(`OAUTH_GITLAB_SELF_HOSTED_SOURCE_ENABLED`), and additionally requires an active
organization provider instance with OAuth-capable auth mode and configured
instance credentials.

When OAuth configuration changes in an environment using cached config, clear config cache:

```bash
composer clear-config-cache
```

## Provider notes

| Provider | Login | Source OAuth | Notes |
|----------|-------|--------------|-------|
| GitHub | supported | supported | Source scopes default to repository and organization access |
| GitLab | supported | supported | Supports base URL overrides |
| GitLab self-hosted | not a normal login provider | supported through `SourceProviderInstance` | Uses organization-owned provider instance OAuth config |
| Bitbucket | supported | supported | Source scopes default to repository and webhook access |
| Codeberg | supported | supported | Uses PKCE in current handlers |

## Callback core

`OAuthCallbackUseCase` accepts provider data, tokens, scopes, optional manually supplied
email and redirect context through `ResolveOAuthCallbackCommand`. It returns an
`OAuthCallbackStatus`: authenticated, email required, verification required or
registration disabled. It does not access HTTP requests or sessions.

The registration policy, user resolver and account linker implement application
ports. `OAuthCallbackTransaction` wraps user creation, email verification and account
persistence in one Cycle database transaction and clears the ORM heap on rollback.
Manually supplied email remains unverified and the OAuth account is linked only
after email verification. `OAuthCallbackResult::userCreated` reports actual user creation,
independently of email verification (replacing the inaccurate `isNewUser` field).

## Rules for future changes

- Keep login OAuth and source OAuth as separate use cases, even when they call the same upstream provider.
- Login handlers may create Packio sessions. Source handlers must not.
- Source redirect routes must stay authenticated and permission guarded.
- Provider callbacks must validate state before trusting any query parameter.
- Source callbacks must require the current Packio user before storing `SourceOAuthAccount`.
- Do not keep login provider tokens; `UserOAuthAccount` stores only the provider identity and scopes. Source operations use `SourceOAuthAccount`.
- Do not use `SourceOAuthAccount` tokens for login.
- Convert a source OAuth credential into `ProviderAuthorization` only through the owner-guarded source authorization flow.
- Use source scopes only for source OAuth clients, not for login clients.
- For self-hosted providers, validate the provider instance belongs to the current organization.

## Files

| File | Purpose |
|------|---------|
| `config/autoload/packio.global.php` | OAuth login and source OAuth configuration |
| `src/Module/Identity/src/Infrastructure/OAuth/OAuthProviderAvailabilityResolver.php` | Resolves provider availability for login and source flows |
| `src/Module/Identity/src/Infrastructure/Dto/OAuthState.php` | Encoded state payload for source OAuth |
| `src/Module/Identity/src/Infrastructure/Support/OAuthSessionContext.php` | Session storage for OAuth state, redirect targets, PKCE, pending email and source context |
| `src/Module/Identity/src/Infrastructure/Support/OAuthCallbackStateValidator.php` | State validation |
| `src/Module/Identity/src/Infrastructure/Support/OAuthPkce.php` | PKCE verifier and challenge generation |
| `src/Module/Identity/src/Infrastructure/Handler/OAuthRedirectHandler.php` | Starts OAuth login |
| `src/Module/Identity/src/Infrastructure/Handler/OAuthCallbackHandler.php` | Completes OAuth login and creates Packio session |
| `src/Module/Identity/src/Infrastructure/Handler/OAuthEmailHandler.php` | Email-required OAuth page |
| `src/Module/Identity/src/Infrastructure/Handler/OAuthEmailSubmitHandler.php` | Completes email-required OAuth registration |
| `src/Module/Identity/src/Infrastructure/Handler/OAuthSourceRedirectHandler.php` | Starts source OAuth |
| `src/Module/Identity/src/Infrastructure/Handler/OAuthSourceCallbackHandler.php` | Completes source OAuth and stores `SourceOAuthAccount` |
| `src/Module/Source/src/Application/Service/SourceOAuthService.php` | Source OAuth token exchange and credential persistence |
| `src/Module/Source/src/Application/Service/SourceOAuthAccountOwnershipVerifier.php` | Verifies source OAuth account ownership before organization authorization |
| `src/Module/Source/src/Infrastructure/Handler/SourceAuthorizationOAuthStoreHandler.php` | Creates `ProviderAuthorization` from source OAuth |
| `src/Module/Source/src/Application/Command/SourceCommandService.php` | Owner-guarded provider authorization creation |
