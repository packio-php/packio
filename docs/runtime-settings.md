# Runtime Settings

Runtime settings are operational boolean overrides stored in `project_settings`.
They let admins change selected behavior from the Admin Settings UI without editing
environment variables, clearing config cache, or restarting services.

Environment/config values remain the fallback source. Runtime settings only take
over when an admin explicitly forces a setting on or off.

## Override values

Runtime boolean settings use three stored values:

| Value | Meaning |
|---|---|
| `inherit` | Use the environment/config fallback value. |
| `on` | Force the setting enabled. |
| `off` | Force the setting disabled. |

Missing rows are treated as `inherit`.

If a fallback config path is absent, Packio uses the default declared by the
runtime setting definition. Settings default to disabled unless the definition
explicitly opts into an enabled default.

## Current settings

| Key | Group | Fallback |
|---|---|---|
| `identity.registration_open` | Identity | `identity.registration_open` / `IDENTITY_REGISTRATION_OPEN` |
| `api.docs.public` | API | `api.docs.public` / `API_DOCS_PUBLIC` |
| `http.cors.enabled` | HTTP | `packio.cors.enabled` / `CORS_ENABLED` |
| `source.providers.github.enabled` | Source Providers | `sources.providers.github.enabled` / `SOURCE_PROVIDER_GITHUB_ENABLED` |
| `source.providers.gitlab.enabled` | Source Providers | `sources.providers.gitlab.enabled` / `SOURCE_PROVIDER_GITLAB_ENABLED` |
| `source.providers.gitlab_self_hosted.enabled` | Source Providers | `sources.providers.gitlab_self_hosted.enabled` / `SOURCE_PROVIDER_GITLAB_SELF_HOSTED_ENABLED` |
| `source.providers.generic_git.enabled` | Source Providers | `sources.providers.generic_git.enabled` / `SOURCE_PROVIDER_GENERIC_GIT_ENABLED` |
| `source.providers.bitbucket.enabled` | Source Providers | `sources.providers.bitbucket.enabled` / `SOURCE_PROVIDER_BITBUCKET_ENABLED` |
| `source.providers.codeberg.enabled` | Source Providers | `sources.providers.codeberg.enabled` / `SOURCE_PROVIDER_CODEBERG_ENABLED` |
| `oauth.github.enabled` | OAuth Login | `oauth.github.enabled` / `OAUTH_GITHUB_ENABLED` |
| `oauth.gitlab.enabled` | OAuth Login | `oauth.gitlab.enabled` / `OAUTH_GITLAB_ENABLED` |
| `oauth.bitbucket.enabled` | OAuth Login | `oauth.bitbucket.enabled` / `OAUTH_BITBUCKET_ENABLED` |
| `oauth.codeberg.enabled` | OAuth Login | `oauth.codeberg.enabled` / `OAUTH_CODEBERG_ENABLED` |
| `oauth.github.source.enabled` | Source OAuth | `oauth.github.source.enabled` / `OAUTH_GITHUB_SOURCE_ENABLED` |
| `oauth.gitlab.source.enabled` | Source OAuth | `oauth.gitlab.source.enabled` / `OAUTH_GITLAB_SOURCE_ENABLED` |
| `oauth.bitbucket.source.enabled` | Source OAuth | `oauth.bitbucket.source.enabled` / `OAUTH_BITBUCKET_SOURCE_ENABLED` |
| `oauth.codeberg.source.enabled` | Source OAuth | `oauth.codeberg.source.enabled` / `OAUTH_CODEBERG_SOURCE_ENABLED` |
| `oauth.gitlab_self_hosted.source.enabled` | Source OAuth | `oauth.gitlab.self_hosted.source.enabled` / `OAUTH_GITLAB_SELF_HOSTED_SOURCE_ENABLED` |

## OAuth availability rules

Source provider settings control whether a provider is available in source
connection and provider access flows at all. Disabling
`source.providers.github.enabled` hides GitHub from those flows even if
`oauth.github.source.enabled` is enabled.

For login OAuth, a provider is available only when:

- the runtime setting evaluates to enabled;
- the provider has login `client_id` and `client_secret` configured.

For source OAuth, a provider is available only when:

- the source runtime setting evaluates to enabled;
- the provider has source `client_id` and `client_secret` configured.

Source OAuth availability is independent from login OAuth availability. Disabling
`oauth.github.enabled` does not disable `oauth.github.source.enabled`.

GitLab self-hosted source OAuth additionally requires:

- `oauth.gitlab_self_hosted.source.enabled` evaluates to enabled;
- inherited values fall back to `OAUTH_GITLAB_SELF_HOSTED_SOURCE_ENABLED`;
- an active organization `SourceProviderInstance`;
- an OAuth-capable auth mode;
- configured instance client credentials.

OAuth redirect and callback handlers both enforce current availability, so
disabling a provider after an OAuth flow starts also blocks the callback.

## HTTP CORS

`http.cors.enabled` only toggles whether Packio applies CORS response handling at
runtime. Allowed origins, allowed headers, exposed headers, max age, and
credential support remain environment/config policy under `packio.cors` and
`expressive.cors`.

## Seeding the catalog

The standard app entrypoint runs the catalog seed at startup when `RUN_MIGRATIONS=true`
(in production Compose, the `app` service only), so all
known rows exist with `inherit`. The seed is idempotent and does not overwrite
existing admin choices. For custom deployments that skip the entrypoint, run it
after deployment:

```bash
php vendor/bin/laminas cycle:seed:run RuntimeSettingsCatalogSeed
```

Running it repeatedly is safe.

## Cache behavior

Admin runtime changes do not require `composer clear-config-cache`.

Changing environment/config fallback values still requires clearing config cache
when config cache is enabled:

```bash
composer clear-config-cache
```
