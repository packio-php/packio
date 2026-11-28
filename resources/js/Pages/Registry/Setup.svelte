<script lang="ts">
  import AppShell from "@/Layouts/AppShell.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Link from "@/Components/core/Link.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import SnippetBlock from "@/Components/ui/SnippetBlock.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import CopyButton from "@/Components/ui/CopyButton.svelte";
  import { usePage } from "@inertiajs/svelte";
  import { ChevronRight } from "@lucide/svelte";
  import { mergeBranding } from "@/lib/branding";
  import type { AuthPayload } from "@/types/auth";
  import type { OrganizationSummary } from "@/types/organization";
  import type {
    RegistryPackageSummary,
    RegistrySetupPageProps,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    base_url?: string;
    public_bootstrap?: string;
    private_bootstrap?: string | null;
    registry_host?: string;
    public_packages?: RegistryPackageSummary[];
    private_packages?: RegistryPackageSummary[];
    branding?: RegistrySetupPageProps["branding"];
    organization?: OrganizationSummary | null;
  }

  let {
    auth = {},
    base_url = "",
    public_bootstrap = "",
    private_bootstrap = null,
    registry_host = "",
    public_packages = [],
    branding = {},
    organization = null,
  }: Props = $props();

  let resolvedBranding = $derived(mergeBranding(branding ?? {}));

  const page = usePage();
  let pageProps = $derived(
    (page as { props?: Record<string, unknown> } | undefined)?.props ?? {},
  );
  let isOrganizationOwner = $derived(
    (pageProps.current_membership as { role?: string } | null)?.role ===
      "owner",
  );

  let composerHost = $derived(
    registry_host || base_url.replace(/^https?:\/\//, ""),
  );
  let repositoryUrl = $derived(private_bootstrap || public_bootstrap);
  let anonymousAccessEnabled = $derived(
    Boolean(organization?.anonymous_access),
  );
  let hasPublicPackages = $derived(public_packages.length > 0);
  let authMode = $state<"local" | "ci">("local");
  let publicOpen = $state<boolean | null>(null);
  let publicExpanded = $derived(
    publicOpen ?? (anonymousAccessEnabled && hasPublicPackages),
  );

  const toggleBaseClass =
    "inline-flex h-9 cursor-pointer items-center rounded-lg border px-3 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color-mix(in_srgb,var(--accent)_60%,transparent)]";
  const toggleActiveClass =
    "border-[var(--accent)] bg-[var(--accent-soft)] text-[var(--accent-ink)]";
  const toggleInactiveClass =
    "border-transparent text-[var(--page-muted)] hover:bg-[var(--surface-strong)] hover:text-[var(--accent-ink)]";

  let localCommand = $derived(
    `composer config --global --auth http-basic.${composerHost} token YOUR_REGISTRY_TOKEN`,
  );
  let ciJson = $derived(
    JSON.stringify(
      {
        "http-basic": {
          [composerHost]: {
            username: "token",
            password: "YOUR_REGISTRY_TOKEN",
          },
        },
      },
      null,
      2,
    ),
  );
  const repositorySnippet = (url: string) => `{
  "repositories": [
    { "type": "composer", "url": "${url}" }
  ]
}`;
  let composerJsonSnippet = $derived(repositorySnippet(repositoryUrl));
  let publicComposerJsonSnippet = $derived(repositorySnippet(public_bootstrap));

  function withOrganization(path: string): string {
    return organization?.slug
      ? `/organization/${organization.slug}${path}`
      : path;
  }
</script>

<svelte:head>
  <title>Setup</title>
</svelte:head>

<AppShell title="Setup" {auth}>
  <section class="grid gap-4">
    <SectionHeading
      title="Composer setup"
      description={`Install packages from ${organization?.name ?? resolvedBranding.name} with Composer.`}
    />

    <Card className="rounded-[1.9rem] p-6">
      <ol class="grid gap-6">
        <li class="grid gap-3">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold ui-title">1. Configure authentication</h3>
            <Link
              class="ui-button-primary inline-flex h-9 items-center rounded-lg px-4 text-sm font-semibold no-underline"
              href={withOrganization("/tokens")}
            >
              Get registry token
            </Link>
          </div>

          <div class="flex gap-2" role="group" aria-label="Authentication mode">
            {#each [["local", "Local development"], ["ci", "CI"]] as [mode, label] (mode)}
              <button
                type="button"
                class={`${toggleBaseClass} ${authMode === mode ? toggleActiveClass : toggleInactiveClass}`}
                aria-pressed={authMode === mode}
                onclick={() => (authMode = mode as "local" | "ci")}
              >
                {label}
              </button>
            {/each}
          </div>

          {#if authMode === "local"}
            <SnippetBlock content={localCommand} copyLabel="Copy command" />
            <p class="text-sm ui-muted">
              Replace <code>YOUR_REGISTRY_TOKEN</code> with a registry token. The
              command stores credentials in the global Composer auth.json, so it applies
              to every project on this machine.
            </p>
          {:else}
            <SnippetBlock
              content={ciJson}
              multiline={true}
              copyLabel="Copy JSON for COMPOSER_AUTH"
              copyText="Copy JSON"
            />
            <p class="text-sm ui-muted">
              Set this as the value of the <code>COMPOSER_AUTH</code> environment
              variable in your CI secrets, with the real token instead of the placeholder.
            </p>
          {/if}
        </li>

        <li class="grid gap-3">
          <h3 class="font-semibold ui-title">2. Add the repository</h3>
          <SnippetBlock
            content={composerJsonSnippet}
            multiline={true}
            copyLabel="Copy JSON for the repository"
            copyText="Copy JSON"
          >
            {#snippet actions()}
              <CopyButton
                value={repositoryUrl}
                label="Copy URL of the repository"
                text="Copy URL"
              />
            {/snippet}
          </SnippetBlock>
          <p class="text-sm ui-muted">
            Add the entry to the existing <code>repositories</code> array of your
            project&apos;s composer.json and keep the other entries. This repository
            needs the token from step 1 and lists the organization&apos;s private
            packages.
          </p>
        </li>

        <li class="grid gap-3">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold ui-title">3. Install a package</h3>
            <Link
              class="ui-button-secondary inline-flex h-9 items-center rounded-lg px-4 text-sm font-semibold no-underline"
              href={withOrganization("/packages")}
            >
              Browse packages
            </Link>
          </div>
          <SnippetBlock
            content="composer require vendor/package"
            copyLabel="Copy command"
          />
          <p class="text-sm ui-muted">
            Replace <code>vendor/package</code> with a package name from the catalog.
          </p>
        </li>
      </ol>
    </Card>

    <Card className="rounded-[1.9rem] p-4">
      <details
        class="group"
        open={publicExpanded}
        ontoggle={(event) =>
          (publicOpen = (event.currentTarget as HTMLDetailsElement).open)}
      >
        <summary
          class="flex cursor-pointer list-none flex-wrap items-center gap-3 [&::-webkit-details-marker]:hidden"
        >
          <ChevronRight
            aria-hidden="true"
            class="size-4 shrink-0 transition-transform group-open:rotate-90"
          />
          <span class="font-semibold ui-title">Public repository</span>
          <Badge tone={anonymousAccessEnabled ? "accent" : "muted"}>
            {anonymousAccessEnabled
              ? "Anonymous access enabled"
              : "Anonymous access disabled"}
          </Badge>
          {#if !hasPublicPackages}
            <span class="text-sm ui-muted">No public packages yet</span>
          {/if}
        </summary>

        <div class="mt-4 grid gap-3">
          {#if !hasPublicPackages}
            <p class="text-sm ui-muted">
              {anonymousAccessEnabled
                ? "Public packages will install from this URL without a token."
                : "Anonymous access is disabled in organization settings."}
            </p>
          {:else if anonymousAccessEnabled}
            <p class="text-sm ui-muted">
              Public packages install from this URL without a token.
            </p>
          {:else}
            <p class="text-sm ui-muted">
              Public packages cannot be installed from this URL until anonymous
              access is enabled in organization settings; it answers 401 even
              with a token. The private repository from step 2 lists private
              packages only.
            </p>
          {/if}
          {#if !anonymousAccessEnabled && isOrganizationOwner}
            <div>
              <Link
                class="ui-button-secondary inline-flex h-9 items-center rounded-lg px-4 text-sm font-semibold no-underline"
                href={withOrganization("/settings")}
              >
                Open organization settings
              </Link>
            </div>
          {/if}

          <SnippetBlock
            content={public_bootstrap}
            copyLabel="Copy URL of the public repository"
            copyText="Copy URL"
          />
          <SnippetBlock
            content={publicComposerJsonSnippet}
            multiline={true}
            copyLabel="Copy JSON for the public repository"
            copyText="Copy JSON"
          />
        </div>
      </details>
    </Card>
  </section>
</AppShell>
