<script lang="ts">
  import AppShell from "@/Layouts/AppShell.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Link from "@/Components/core/Link.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import SnippetBlock from "@/Components/ui/SnippetBlock.svelte";
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
    private_packages = [],
    branding = {},
    organization = null,
  }: Props = $props();

  let resolvedBranding = $derived(mergeBranding(branding ?? {}));
  const tokenPlaceholder = "<registry-token>";

  let composerHost = $derived(
    registry_host || base_url.replace(/^https?:\/\//, ""),
  );
  let repositoryUrl = $derived(
    auth?.check && private_bootstrap ? private_bootstrap : public_bootstrap,
  );
  let totalPackages = $derived(
    public_packages.length + private_packages.length,
  );
  let anonymousAccessEnabled = $derived(
    Boolean(organization?.anonymous_access),
  );
  let authCommand = $derived(
    `composer config --global --auth http-basic.${composerHost} token <registry-token>`,
  );
  let authCommandCopy = $derived(authCommand.replace(tokenPlaceholder, ""));
  let composerJsonSnippet = $derived(`{
  "repositories": [
    { "type": "composer", "url": "${repositoryUrl}" }
  ]
}`);
  let publicComposerJsonSnippet = $derived(`{
  "repositories": [
    { "type": "composer", "url": "${public_bootstrap}" }
  ]
}`);
  let composerAuthSnippet = $derived(
    `COMPOSER_AUTH={"http-basic":{"${composerHost}":{"username":"token","password":"<registry-token>"}}}`,
  );
  let composerAuthCopy = $derived(
    composerAuthSnippet.replace(tokenPlaceholder, ""),
  );

  function withOrganization(path: string): string {
    if (!organization?.slug) {
      return path;
    }

    return path === "/"
      ? `/organization/${organization.slug}`
      : `/organization/${organization.slug}${path}`;
  }
</script>

<svelte:head>
  <title>Registry Overview</title>
</svelte:head>

<AppShell title="Registry Overview" {auth}>
  <section class="grid gap-6">
    <Card className="rounded-[1.9rem] p-7 md:p-9">
      <div
        class="grid gap-8 lg:grid-cols-[minmax(0,1.6fr)_minmax(18rem,0.9fr)]"
      >
        <div class="min-w-0">
          <SectionHeading
            eyebrow={`${organization?.slug ?? "organization"} overview`}
            title={`${organization?.name ?? resolvedBranding.name} overview`}
            description={`Configure Composer access for packages published by ${organization?.name ?? "this organization"} in ${resolvedBranding.name}.`}
            className="max-w-3xl"
          />

          <p class="mt-5 max-w-3xl text-sm leading-7 ui-muted">
            Configure global authentication to access this organization&apos;s
            packages:
          </p>

          <div class="mt-5 grid gap-4">
            <SnippetBlock
              title="Global Composer authentication"
              content={authCommand}
              copyContent={authCommandCopy}
              actionPlaceholder={tokenPlaceholder}
              actionHref={withOrganization("/tokens")}
              actionLabel="Get Token"
              multiline={true}
            />
            <SnippetBlock
              title="composer.json repository"
              content={composerJsonSnippet}
              multiline={true}
            />
            <SnippetBlock
              title="COMPOSER_AUTH environment variable"
              content={composerAuthSnippet}
              copyContent={composerAuthCopy}
              actionPlaceholder={tokenPlaceholder}
              actionHref={withOrganization("/tokens")}
              actionLabel="Get Token"
              multiline={true}
            />
          </div>
        </div>

        <div class="grid gap-4 self-start">
          <div class="ui-inset rounded-[1.5rem] p-5">
            <p class="text-xs uppercase tracking-[0.24em] ui-muted">
              Organization
            </p>
            <p class="mt-3 text-2xl font-semibold ui-title">
              {organization?.name ?? resolvedBranding.name}
            </p>
            <p class="mt-1 text-sm ui-muted">
              Org URL: {organization?.slug ?? "default"}
            </p>
          </div>

          <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
            <div class="ui-inset rounded-[1.35rem] p-4">
              <p class="text-xs uppercase tracking-[0.2em] ui-muted">Host</p>
              <p class="mt-2 break-all text-sm font-medium ui-title">
                {composerHost}
              </p>
            </div>

            <div class="ui-inset rounded-[1.35rem] p-4">
              <p class="text-xs uppercase tracking-[0.2em] ui-muted">
                Packages
              </p>
              <p class="mt-2 text-2xl font-semibold ui-title">
                {totalPackages}
              </p>
              <p class="mt-1 text-sm ui-muted">
                {public_packages.length} public, {private_packages.length} private
              </p>
            </div>

            <div class="ui-inset rounded-[1.35rem] p-4">
              <p class="text-xs uppercase tracking-[0.2em] ui-muted">
                Repository URL
              </p>
              <p class="mt-2 break-all text-sm font-medium ui-title">
                {repositoryUrl}
              </p>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <Card className="rounded-[1.9rem] p-7 md:p-9">
      <div
        class="grid gap-8 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.9fr)]"
      >
        <div class="min-w-0">
          <SectionHeading
            eyebrow="Public access"
            title="Public package installation"
            description="Public packages use the public Composer repository endpoint. They can be installed without credentials only when anonymous access is enabled for the organization."
            className="max-w-3xl"
          />

          <div class="mt-5 grid gap-4">
            <SnippetBlock
              title="Public composer.json repository"
              content={publicComposerJsonSnippet}
              multiline={true}
            />
            <SnippetBlock
              title="Public repository URL"
              content={public_bootstrap}
              multiline={false}
            />
          </div>
        </div>

        <div class="grid gap-4 self-start">
          <div class="ui-inset rounded-[1.35rem] p-5">
            <p class="text-sm font-medium ui-title">Organization setting</p>
            <p class="mt-2 text-sm leading-7 ui-muted">
              Turn on anonymous access in organization settings before sharing
              public package install instructions without a registry token.
            </p>
            <Link
              class="ui-button-secondary mt-4 inline-flex rounded-full px-4 py-2 text-sm font-medium"
              href={withOrganization("/settings")}
            >
              Open organization settings
            </Link>
          </div>

          <div class="ui-inset rounded-[1.35rem] p-4">
            <p class="text-xs uppercase tracking-[0.2em] ui-muted">
              Anonymous access
            </p>
            <p class="mt-2 text-lg font-semibold ui-title">
              {anonymousAccessEnabled ? "Enabled" : "Disabled"}
            </p>
            <p class="mt-1 text-sm leading-6 ui-muted">
              {anonymousAccessEnabled
                ? "Public packages can be installed without a registry token."
                : "Public packages still require a registry token until anonymous access is enabled."}
            </p>
          </div>
        </div>
      </div>
    </Card>

    <Card className="rounded-[1.9rem] p-7 md:p-9">
      <SectionHeading
        eyebrow="Notes"
        title="Composer setup guidance"
        description={anonymousAccessEnabled
          ? "Use a registry token for private packages. Public packages are installable without authentication."
          : "Use a registry token for this organization. Public packages become installable without authentication only when anonymous access is enabled."}
        className="max-w-3xl"
      />

      <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="ui-inset rounded-[1.35rem] p-4">
          <p class="text-sm font-medium ui-title">Global auth</p>
          <p class="mt-2 text-sm leading-7 ui-muted">
            Configure HTTP basic auth once on your machine if you regularly work
            with this organization&apos;s private packages.
          </p>
        </div>

        <div class="ui-inset rounded-[1.35rem] p-4">
          <p class="text-sm font-medium ui-title">Repository entry</p>
          <p class="mt-2 text-sm leading-7 ui-muted">
            Add this repository to an existing `composer.json`, or replace the
            URL if you already manage multiple Composer registries.
          </p>
        </div>

        <div class="ui-inset rounded-[1.35rem] p-4">
          <p class="text-sm font-medium ui-title">Environment auth</p>
          <p class="mt-2 text-sm leading-7 ui-muted">
            `COMPOSER_AUTH` is useful in CI, containers, and ephemeral
            development environments where you do not want to write credentials
            to disk.
          </p>
        </div>
      </div>
    </Card>
  </section>
</AppShell>
