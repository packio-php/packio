<script lang="ts">
  import AppShell from "@/Layouts/AppShell.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import Link from "@/Components/core/Link.svelte";
  import SnippetBlock from "@/Components/ui/SnippetBlock.svelte";
  import OAuthIcon from "@/Components/ui/OAuthIcon.svelte";
  import { mergeBranding } from "@/lib/branding";
  import type { AuthPayload } from "@/types/auth";
  import type { OAuthProviderConfig } from "@/types/pages";

  interface Props {
    product_name?: string;
    tagline?: string;
    domain?: string;
    app_base_url?: string;
    auth?: AuthPayload;
    oauth_providers?: OAuthProviderConfig[];
  }

  let {
    product_name = "",
    tagline = "",
    domain = "",
    app_base_url = "",
    auth = {},
    oauth_providers = [],
  }: Props = $props();

  let branding = $derived(
    mergeBranding({
      name: product_name,
      tagline: tagline,
      domain: domain,
    }),
  );

  let enabledProviders = $derived(
    oauth_providers?.filter((p) => p.enabled) ?? [],
  );

  function registryBaseUrl(organization: string): string {
    const fallbackDomain = domain || "packio.local";

    try {
      const url = new URL(app_base_url || `https://${fallbackDomain}`);
      const port = url.port ? `:${url.port}` : "";

      return `${url.protocol}//${organization}.${fallbackDomain}${port}`;
    } catch {
      return `https://${organization}.${fallbackDomain}`;
    }
  }

  let composerJsonSnippet = $derived(`{
  "repositories": [
    { "type": "composer", "url": "${registryBaseUrl("default")}/packages.json" }
  ]
}`);

  let authCommand = $derived(
    `composer config --global --auth http-basic.default.${domain || "packio.local"} token <registry-token>`,
  );

  let composerAuthSnippet = $derived(
    `COMPOSER_AUTH={"http-basic":{"default.${domain || "packio.local"}":{"username":"token","password":"<registry-token>"}}}`,
  );

  let workflow = $derived([
    {
      title: "Connect sources",
      text: "Attach GitHub, GitLab, Bitbucket, Codeberg, self-hosted GitLab, or a generic Git repository.",
    },
    {
      title: "Publish from tags",
      text: `${branding.name} reads composer.json, releases, dist archives, and package metadata.`,
    },
    {
      title: "Install privately",
      text: "Use Composer tokens for CI, developers, and protected packages.",
    },
  ]);

  const capabilities = [
    "Organization-scoped registries",
    "Private package access tokens",
    "Full REST API for automation",
    "Repository webhooks and automated source sync",
    "Hosted, self-hosted, and generic Git sources",
    "OAuth and token-based source access",
  ];

  const apiSurfaces = [
    "Organizations, members, and invitations",
    "Sources, webhooks, packages, and releases",
    "Registry tokens, provider authorizations, and admin operations",
  ];

  const providerCoverage = [
    {
      provider: "github",
      label: "GitHub",
      text: "OAuth discovery, private repositories, and webhook sync.",
    },
    {
      provider: "gitlab",
      label: "GitLab",
      text: "GitLab.com and organization-managed self-hosted instances.",
    },
    {
      provider: "bitbucket",
      label: "Bitbucket",
      text: "Workspace repositories with OAuth-backed source access.",
    },
    {
      provider: "codeberg",
      label: "Codeberg",
      text: "Codeberg repositories, tags, archives, and webhook updates.",
    },
    {
      provider: "generic_git",
      label: "Generic Git",
      text: "Manual HTTPS or SSH repository URLs for custom Git hosting.",
    },
  ];

  let registryPreview = $derived({
    organization: "acme",
    repositoryUrl: `${registryBaseUrl("acme")}/packages.json`,
    host: `acme.${domain || "packio.local"}`,
    packageCount: 12,
    publicCount: 8,
    privateCount: 4,
    authCommand: `composer config --global --auth http-basic.acme.${domain || "packio.local"} token <registry-token>`,
  });

  function oauthLogin(provider: string): void {
    window.location.href = `/auth/oauth/${provider}/redirect`;
  }
</script>

<svelte:head>
  <title>{branding.name} - {branding.tagline}</title>
</svelte:head>

<AppShell title={branding.name} {auth}>
  <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-10 md:px-6 md:py-14">
    <div class="grid gap-10">
      <section class="grid min-h-[calc(100vh-13rem)] content-center gap-8">
        <div
          class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_26rem] lg:items-center"
        >
          <div class="grid max-w-3xl gap-6">
            <div class="flex flex-wrap items-center gap-3">
              <span
                class="ui-badge-accent rounded-full px-3 py-1 text-xs font-semibold"
              >
                Self-hosted Composer registry
              </span>
              <span class="text-sm ui-muted">
                Git sources, REST API, private packages, team tokens
              </span>
            </div>

            <div class="grid gap-4">
              <h1
                class="max-w-3xl text-5xl font-bold leading-tight md:text-6xl"
              >
                {branding.name}
              </h1>
              <p class="max-w-2xl text-xl leading-8 ui-muted">
                {branding.tagline || "Private Composer packages for teams."}
                Connect repositories, publish packages from tags, and install them
                securely with Composer. Automate the same registry workflows through
                the REST API.
              </p>
            </div>

            <div class="flex flex-wrap gap-3">
              <Link
                class="inline-flex h-11 items-center justify-center rounded-lg px-5 text-sm font-semibold transition"
                style="background-color: var(--button-primary-bg); color: var(--button-primary-text);"
                href="/auth/register"
              >
                Create account
              </Link>
              <Link
                class="inline-flex h-11 items-center justify-center rounded-lg border px-5 text-sm font-semibold transition"
                style="border-color: var(--button-secondary-border); background-color: var(--button-secondary-bg); color: var(--button-secondary-text);"
                href="#setup"
              >
                Composer setup
              </Link>
            </div>

            {#if enabledProviders.length > 0}
              <div class="grid gap-3">
                <p class="text-sm ui-muted">Or continue with</p>
                <div class="oauth-provider-grid">
                  {#each enabledProviders as provider}
                    <button
                      type="button"
                      class="oauth-btn"
                      title={`Sign in with ${provider.label}`}
                      onclick={() => oauthLogin(provider.provider)}
                    >
                      <OAuthIcon provider={provider.provider} />
                    </button>
                  {/each}
                </div>
              </div>
            {/if}
          </div>

          <div class="registry-preview">
            <div
              class="flex items-center justify-between gap-4 border-b border-(--panel-border) px-5 py-4"
            >
              <div>
                <p class="text-sm font-semibold ui-title">Registry setup</p>
                <p class="mt-1 text-xs ui-muted">
                  {registryPreview.host}
                </p>
              </div>
              <span class="ui-badge-accent rounded-full px-3 py-1 text-xs">
                composer repository
              </span>
            </div>

            <div class="grid gap-3 p-5">
              <div class="rounded-xl border border-(--panel-border) px-4 py-3">
                <p class="text-xs uppercase tracking-[0.2em] ui-muted">
                  Organization
                </p>
                <p class="mt-2 text-sm font-semibold ui-title">
                  {registryPreview.organization}
                </p>
              </div>

              <div class="rounded-xl border border-(--panel-border) px-4 py-3">
                <p class="text-xs uppercase tracking-[0.2em] ui-muted">
                  Repository URL
                </p>
                <p class="mt-2 break-all text-sm font-medium ui-title">
                  {registryPreview.repositoryUrl}
                </p>
              </div>

              <div class="grid gap-3 md:grid-cols-2">
                <div
                  class="rounded-xl border border-(--panel-border) px-4 py-3"
                >
                  <p class="text-xs uppercase tracking-[0.2em] ui-muted">
                    Packages
                  </p>
                  <p class="mt-2 text-2xl font-semibold ui-title">
                    {registryPreview.packageCount}
                  </p>
                  <p class="mt-1 text-xs ui-muted">available in the registry</p>
                </div>

                <div
                  class="rounded-xl border border-(--panel-border) px-4 py-3"
                >
                  <p class="text-xs uppercase tracking-[0.2em] ui-muted">
                    Visibility
                  </p>
                  <p class="mt-2 text-sm font-semibold ui-title">
                    {registryPreview.publicCount} public / {registryPreview.privateCount}
                    private
                  </p>
                  <p class="mt-1 text-xs ui-muted">
                    token required for private packages
                  </p>
                </div>
              </div>

              <div class="rounded-xl border border-(--panel-border) px-4 py-3">
                <p class="text-xs uppercase tracking-[0.2em] ui-muted">
                  Global auth
                </p>
                <p class="mt-2 break-all text-sm font-medium ui-title">
                  {registryPreview.authCommand}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {#each capabilities as capability (capability)}
          <InsetCard
            className="flex min-h-24 items-center justify-center p-5 text-center"
          >
            <p class="text-sm font-semibold leading-6 ui-title">
              {capability}
            </p>
          </InsetCard>
        {/each}
      </section>

      <section id="how-it-works" class="grid gap-5">
        <div>
          <p class="text-sm font-semibold ui-accent">Workflow</p>
          <h2 class="mt-2 text-2xl font-bold ui-title">
            From repository to installable package
          </h2>
        </div>
        <div class="grid gap-4 lg:grid-cols-3">
          {#each workflow as step, index (step.title)}
            <Card className="p-5" padding="">
              <div class="flex items-start gap-4">
                <span class="step-number">{index + 1}</span>
                <div>
                  <h3 class="font-semibold ui-title">{step.title}</h3>
                  <p class="mt-2 text-sm leading-6 ui-muted">{step.text}</p>
                </div>
              </div>
            </Card>
          {/each}
        </div>
      </section>

      <section class="grid gap-5 lg:grid-cols-[0.95fr_1.05fr]">
        <Card className="p-6" padding="">
          <div class="grid gap-4">
            <div>
              <p class="text-sm font-semibold ui-accent">REST API</p>
              <h2 class="mt-2 text-2xl font-bold ui-title">
                Automate registry operations
              </h2>
            </div>
            <p class="text-sm leading-6 ui-muted">
              Create scoped API tokens from your profile and drive {branding.name}
              from scripts, internal tooling, CI jobs, or deployment pipelines.
            </p>
            <div class="grid gap-2">
              {#each apiSurfaces as surface (surface)}
                <div
                  class="rounded-xl border border-(--panel-border) px-4 py-3 text-sm font-medium ui-title"
                >
                  {surface}
                </div>
              {/each}
            </div>
          </div>
        </Card>

        <Card className="p-6" padding="">
          <div class="grid gap-4">
            <div>
              <p class="text-sm font-semibold ui-accent">Source providers</p>
              <h2 class="mt-2 text-2xl font-bold ui-title">
                Connect more than GitHub and GitLab
              </h2>
            </div>
            <p class="text-sm leading-6 ui-muted">
              Mix hosted providers, self-hosted GitLab instances, and manual Git
              URLs in the same organization registry.
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
              {#each providerCoverage as provider (provider.label)}
                <InsetCard className="flex items-start gap-3 p-4">
                  <span class="mt-0.5 text-(--accent)">
                    <OAuthIcon
                      provider={provider.provider}
                      className="size-5"
                    />
                  </span>
                  <span class="grid gap-1">
                    <span class="text-sm font-semibold ui-title">
                      {provider.label}
                    </span>
                    <span class="text-xs leading-5 ui-muted">
                      {provider.text}
                    </span>
                  </span>
                </InsetCard>
              {/each}
            </div>
          </div>
        </Card>
      </section>

      <section id="setup" class="grid gap-5 lg:grid-cols-2">
        <Card className="p-6" padding="">
          <div class="grid gap-3">
            <h2 class="text-xl font-bold ui-title">Composer repository</h2>
            <p class="text-sm leading-6 ui-muted">
              Add your organization registry once, then require packages by
              name.
            </p>
            <SnippetBlock
              title="composer.json"
              content={composerJsonSnippet}
              multiline={true}
            />
            <code class="code-line">composer require vendor/package</code>
          </div>
        </Card>

        <Card className="p-6" padding="">
          <div class="grid gap-3">
            <h2 class="text-xl font-bold ui-title">Token authentication</h2>
            <p class="text-sm leading-6 ui-muted">
              Use registry tokens locally or in CI without exposing provider
              credentials.
            </p>
            <SnippetBlock
              title="Global auth"
              content={authCommand}
              multiline={true}
            />
            <SnippetBlock
              title="COMPOSER_AUTH"
              content={composerAuthSnippet}
              multiline={true}
            />
          </div>
        </Card>
      </section>

      <section class="cta-band">
        <div>
          <h2 class="text-2xl font-bold ui-title">Start with one repository</h2>
          <p class="mt-2 text-sm leading-6 ui-muted">
            Create an organization, connect a source, and {branding.name} will keep
            the registry ready for Composer installs.
          </p>
        </div>
        <div class="flex flex-wrap gap-3">
          <Link
            class="inline-flex h-11 items-center justify-center rounded-lg px-5 text-sm font-semibold transition"
            style="background-color: var(--button-primary-bg); color: var(--button-primary-text);"
            href="/auth/register"
          >
            Create account
          </Link>
          <Link
            class="inline-flex h-11 items-center justify-center rounded-lg border px-5 text-sm font-semibold transition"
            style="border-color: var(--button-secondary-border); background-color: var(--button-secondary-bg); color: var(--button-secondary-text);"
            href="/auth/login"
          >
            Sign in
          </Link>
        </div>
      </section>
    </div>
  </main>
</AppShell>

<style>
  .oauth-provider-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, 2.5rem);
    gap: 0.5rem;
    justify-content: start;
  }

  .oauth-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.5rem;
    border: 1px solid var(--button-secondary-border);
    background-color: var(--button-secondary-bg);
    color: var(--button-secondary-text);
    cursor: pointer;
    transition: all 0.15s ease;
  }

  .oauth-btn:hover {
    border-color: var(--button-secondary-hover);
    background-color: var(--button-secondary-hover);
  }

  .registry-preview {
    overflow: hidden;
    border: 1px solid var(--panel-border);
    border-radius: var(--radius-xl);
    background: var(--surface);
    box-shadow: 0 24px 60px color-mix(in srgb, var(--page-bg) 52%, transparent);
  }

  .step-number {
    display: inline-flex;
    width: 2rem;
    height: 2rem;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background: var(--accent-soft);
    color: var(--accent-ink);
    font-weight: 700;
  }

  .code-line {
    display: block;
    min-width: 0;
    overflow-x: auto;
    border-radius: 0.75rem;
    background: var(--surface-muted);
    padding: 0.9rem 1rem;
    color: var(--page-ink);
    font-family: monospace;
    font-size: 0.875rem;
  }

  .cta-band {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    border: 1px solid var(--panel-border);
    border-radius: var(--radius-xl);
    background: var(--surface-muted);
    padding: 1.5rem;
  }

  @media (max-width: 768px) {
    .cta-band {
      align-items: flex-start;
      flex-direction: column;
    }
  }
</style>
