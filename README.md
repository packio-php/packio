# Packio

Packio is a self-hosted private Composer package registry. It provides organization and package management, release publishing, private registry tokens, a REST API, and GitHub, GitLab, Bitbucket, and Codeberg source integrations.

![Packio landing page](docs/assets/screenshot.png)

To try Packio without installing it, register at [packio.us](https://packio.us), a live instance run by the maintainer. Registration is open.

## Supported deployment

Packio supports a Linux single host on amd64, using RoadRunner 2025.1.15, PHP 8.5.x, Node 24.21.0 for the image build, and PostgreSQL 17. The `deploy/` Compose files run the application, queue and maintenance workers, optional Inertia SSR, and PostgreSQL. See [the support scope](docs/support.md) for the full list of supported, experimental and unsupported configurations.

## Try it locally

Try Packio on your own machine from the published images (published starting with the first release after 0.2.0). No clone, no build, no GitHub token. It serves plain HTTP and is not a production setup.

Requirements: Linux on amd64, Docker with Compose v2, curl. For step 5 below, create a GitHub [fine-grained personal access token](https://github.com/settings/personal-access-tokens/new) first: set Repository access to "Public repositories", add no permissions, and choose a short expiry. The token can only read public data; a classic token with no scopes selected works too. Find the latest version on the [releases page](https://github.com/packio-php/packio/releases) and replace `X.Y.Z` with it (without the `v`):

```bash
mkdir packio && cd packio
curl -fsSL https://github.com/packio-php/packio/archive/refs/tags/vX.Y.Z.tar.gz | tar -xz --strip-components=2 --wildcards '*/deploy/*'
cp .env.local.example .env
docker compose up -d
```

Then:

3. Open `http://packio.localhost:8080` and register. The verification code arrives in Mailpit at `http://localhost:8025`.
4. Create an organization, for example `acme`.
5. Open Sources, click Add source, and choose GitHub. Under "or enter token manually", paste your GitHub token and click Save provider access, then Continue to add source. The list shows only repositories of the token owner, so pick one of your own public repositories that has a `composer.json` and version tags; if you have none, fork a small Composer package and untick "Copy the main branch only" in GitHub's fork dialog so the fork keeps its tags. Click Connect. A read-only token cannot create webhooks, so Packio shows a warning and does not follow new pushes on its own; synchronization from the source page still works. The source synchronizes in the background; its page shows the sync status and the packages found.
6. Open Tokens (`/organization/acme/tokens`), enter a name, and click Issue token. Copy the token.
7. The Registry Setup page (`/organization/acme/registry/setup`) shows the registry host and repository URL; for `acme` the host is `acme.packio.localhost:8080`. In an empty project folder, run Composer in Docker (Linux; `--network host` lets the container reach the host's port 8080). Any local Composer 2 works too.

   ```bash
   mkdir demo && cd demo
   composer() { docker run --rm $([ -t 0 ] && echo -it) --user "$(id -u):$(id -g)" --network host -v "$PWD":/app -w /app composer:2.8 "$@"; }
   composer init --no-interaction --name acme/demo
   composer config secure-http false   # only for this local trial: the registry is plain HTTP
   composer config --auth http-basic.acme.packio.localhost:8080 token '<registry-token>'
   composer config repositories.packio composer '<repository URL from the Registry page>'
   composer require <vendor/package>
   ```
8. Stop and remove everything, including the data (run this in the `packio` folder, not in `demo`):

   ```bash
   docker compose down -v
   ```

To work on the code, see [the source-based development stack](docs/docker.md#try-the-development-stack-from-source).

## Install on a server

1. Download `deploy/` and copy `.env.example` to `.env`.
2. Fill in the domain, two secrets and the mail address, and choose the Caddy line for automatic HTTPS.
3. Run `docker compose up -d`.

The full guide with every command: [Self-hosting Packio](docs/self-hosting.md). Building from a Git clone is described in [Docker and runtime operations](docs/docker.md#install-from-a-clean-git-clone).

## First account and Composer setup

Register through the app, then verify the account using the email sent through your configured SMTP service. After registration, promote the existing user to admin from the app container:

```bash
docker compose exec app php vendor/bin/laminas user:make-admin admin@example.com
```

This is the command for the `deploy/` setup. With a source build, add `--env-file .env.prod.local -f compose.prod.yaml` after `docker compose`.

Sign in as that user and create an organization. To import repositories, configure a source OAuth app for the provider, connect that account, add a repository, and run a source sync from its page. Login OAuth is optional; source OAuth setup is covered in [the OAuth guide](docs/oauth.md).

Create a registry token for the organization in **Organization → Tokens**. In **Organization → Registry**, Packio shows the Composer repository URL and auth host for that organization. Configure authentication globally so the token is written to Composer's global `auth.json`, outside the consuming project:

```bash
composer config --global --auth http-basic.acme.packio.example.com token '<registry-token>'
```

If you prefer project-local credentials, add `/auth.json` to the **consuming project's** `.gitignore` before writing the token. Then run this command in that project instead of the global command:

```bash
composer config --auth http-basic.acme.packio.example.com token '<registry-token>'
```

Add the private repository URL shown in Packio to the consuming project's `composer.json`:

```json
{
  "repositories": [
    { "type": "composer", "url": "https://acme.packio.example.com/private/" }
  ]
}
```

Replace `acme` with your organization slug and `packio.example.com` with your registry domain. The hostname in Composer's `http-basic` configuration must exactly match the organization registry hostname in the repository URL. Keep the token out of `composer.json`, `composer.lock`, and source control; use Composer's auth file or an injected `COMPOSER_AUTH` secret in CI.

Then install a private package with Composer:

```bash
composer require 'acme/private-library:^1.0'
```

Replace the package name and version with one published by your organization.

## Development and reference

For local development, use [the development Compose guide](docs/docker.md#local-command-matrix). The host tooling targets PHP 8.5.x and Node 24.21.0. Copy `.env.example` to `.env`; local defaults use PostgreSQL on `localhost:15432` and Packio's localhost development domains.

- [Environment variables](ENV.md)
- [Self-hosting guide](docs/self-hosting.md)
- [Docker and runtime operations](docs/docker.md)
- [Support matrix](docs/support.md)
- [Testing](docs/testing.md)
- [Application CLI commands](docs/commands.md)
- [OAuth setup](docs/oauth.md)
- [Authentication and authorization](docs/authentication-authorization.md)
- [REST API contract](docs/api-rest-contract.md)
- [Module architecture](docs/development/architecture.md)
- [Adapter boundaries, including webhooks](docs/development/adapter-boundaries.md)
- [Runtime settings](docs/runtime-settings.md)
- [Rate limiting](docs/rate-limiting.md)
- [Object mapper](docs/object-mapper.md)
- [PHP dependency security](docs/security/php-dependencies.md)

## License

MIT
