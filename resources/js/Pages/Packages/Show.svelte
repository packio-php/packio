<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import { router } from "@inertiajs/svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Link from "@/Components/core/Link.svelte";
  import BackButton from "@/Components/ui/BackButton.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import { ChevronRight } from "@lucide/svelte";
  import CopyButton from "@/Components/ui/CopyButton.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import { formatDate } from "@/lib/date";
  import { sourceIssue } from "./packageSource";
  import type { AppPageProps } from "@/types/app";
  import type { AuthPayload } from "@/types/auth";
  import type { OrganizationSummary } from "@/types/organization";
  import type {
    PackageDetailsPayload,
    PackageReleasePayload,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    packageData: PackageDetailsPayload;
    releases?: PackageReleasePayload[];
    organization?: OrganizationSummary | null;
    can_manage_packages?: boolean;
  }

  let {
    auth = {},
    packageData: pkg,
    releases = [],
    organization = null,
    can_manage_packages = false,
  }: Props = $props();

  const releasesPerPage = 10;
  let readmeExpanded = $state(false);
  let releasePage = $state(1);
  let listedReleaseCount = $derived(
    releases.filter((release) => release.active).length,
  );
  let hiddenReleaseCount = $derived(releases.length - listedReleaseCount);

  let latestRelease = $derived(releases.find((r) => r.active) ?? null);
  let installable = $derived(pkg.active && latestRelease !== null);
  let tokenRequired = $derived(
    pkg.visibility === "private" || !pkg.anonymous_access,
  );
  let sourceProblem = $derived(
    pkg.source_summary ? sourceIssue(pkg.source_summary) : null,
  );
  let hasOrganizationAccess = $derived(
    Boolean(usePage<AppPageProps>().props.current_membership),
  );
  let releaseLastPage = $derived(
    Math.max(1, Math.ceil(releases.length / releasesPerPage)),
  );
  $effect(() => {
    if (releasePage > releaseLastPage) {
      releasePage = releaseLastPage;
    }
  });
  let releaseOffset = $derived((releasePage - 1) * releasesPerPage);
  let paginatedReleases = $derived(
    releases.slice(releaseOffset, releaseOffset + releasesPerPage),
  );
  let releaseFrom = $derived(releases.length === 0 ? 0 : releaseOffset + 1);
  let releaseTo = $derived(
    Math.min(releaseOffset + releasesPerPage, releases.length),
  );
  let showReleasePagination = $derived(releases.length > releasesPerPage);

  function withOrganization(path: string): string {
    if (!organization?.slug) {
      return path;
    }

    return path === "/"
      ? `/organization/${organization.slug}`
      : `/organization/${organization.slug}${path}`;
  }

  function queryParam(url: string, name: string): string {
    const query = url.split("?")[1] ?? "";

    return new URLSearchParams(query).get(name) ?? "";
  }

  function safeBackUrl(url: string): string {
    const fallback = withOrganization("/packages");
    const returnTo = queryParam(url, "return_to");
    const canUseAdminReturn = auth?.user?.is_admin === true;

    if (returnTo === fallback || returnTo.startsWith(`${fallback}?`)) {
      return returnTo;
    }

    if (
      canUseAdminReturn &&
      (returnTo === "/admin/packages" ||
        returnTo.startsWith("/admin/packages?"))
    ) {
      return returnTo;
    }

    return fallback;
  }

  let backUrl = $derived(safeBackUrl(usePage<AppPageProps>().url ?? ""));

  let pendingRelease = $state<PackageReleasePayload | null>(null);
  let confirming = $state(false);

  function confirmRelease(): void {
    const release = pendingRelease;
    if (!release) {
      return;
    }

    confirming = true;
    // preserveUrl keeps ?return_to so "Back to packages" still works after the redirect.
    router.post(
      withOrganization(`/packages/${pkg.name}/releases/activity`),
      { version: release.version, active: release.active ? "0" : "1" },
      {
        preserveScroll: true,
        preserveUrl: true,
        onFinish: () => {
          confirming = false;
          pendingRelease = null;
        },
      },
    );
  }

  function handleReleasePageChange(page: number): void {
    releasePage = Math.min(Math.max(page, 1), releaseLastPage);
  }
</script>

<svelte:head>
  <title>{pkg?.name ?? "Package details"}</title>
</svelte:head>

<AppShell title={pkg?.name ?? "Package details"} {auth}>
  <section class="grid gap-6">
    <BackButton label="Back to packages" href={backUrl} />

    <Card as="article" className="rounded-[1.75rem] p-6">
      <h2 class="ui-title min-w-0 break-all text-2xl font-semibold">
        {pkg.name}
      </h2>
      <p class="ui-muted mt-3 text-sm leading-6">
        {pkg.description || "No description"}
      </p>
      <div class="mt-4 flex flex-wrap items-center gap-2">
        <Badge tone={latestRelease ? "accent" : "muted"}>
          {latestRelease ? latestRelease.version : "No available version"}
        </Badge>
        <Badge tone={tokenRequired ? "danger" : "accent"}>
          {tokenRequired ? "Token required" : "Public"}
        </Badge>
        <Badge tone={pkg.active ? "accent" : "muted"}>
          {pkg.active ? "Listed in registry" : "Hidden from registry"}
        </Badge>
        <span class="ui-muted text-sm">{pkg.download_count} downloads</span>
      </div>

      {#if installable}
        <InsetCard className="mt-6 p-4">
          <div class="flex items-center gap-3">
            <code
              class="min-w-0 flex-1 overflow-x-auto whitespace-nowrap text-sm"
            >
              composer require {pkg.name}
            </code>
            <CopyButton value={`composer require ${pkg.name}`} label="Copy" />
          </div>
          {#if tokenRequired}
            <p class="ui-muted mt-3 text-sm">
              Installing requires a registry token.
              {#if hasOrganizationAccess}
                <Link
                  class="font-medium underline"
                  href={withOrganization("/registry/setup")}
                >
                  Setup
                </Link>
              {/if}
            </p>
          {/if}
        </InsetCard>
      {/if}

      {#if pkg.source_summary}
        <div class="mt-6 flex flex-wrap items-center gap-2 text-sm">
          <span class="ui-muted">Source</span>
          <Link
            class="ui-link-accent min-w-0 break-all font-medium"
            href={withOrganization(`/sources/${pkg.source_summary.id}`)}
          >
            {pkg.source_summary.name}
          </Link>
          {#if sourceProblem}
            <Badge tone="danger">{sourceProblem}</Badge>
          {/if}
        </div>
        {#if pkg.source_summary.last_sync_error}
          <p
            class="mt-2 break-all text-sm"
            style="color: var(--flash-error-text);"
          >
            {pkg.source_summary.last_sync_error}
          </p>
        {/if}
      {/if}
    </Card>

    <Card as="details" className="group rounded-[1.25rem] px-5 py-3">
      <summary
        class="flex cursor-pointer list-none items-center gap-2 text-sm font-medium ui-muted hover:text-(--page-ink) [&::-webkit-details-marker]:hidden"
      >
        <ChevronRight
          aria-hidden="true"
          class="size-4 shrink-0 transition-transform group-open:rotate-90"
        />
        {pkg.source_summary ? "Source details" : "Package details"}
      </summary>
      <dl class="ui-muted mt-3 grid gap-4 text-sm">
        {#if pkg.source_summary}
          <div>
            <dt class="ui-title font-medium">Provider</dt>
            <dd>{pkg.source_summary.type}</dd>
          </div>
          <div>
            <dt class="ui-title font-medium">URL</dt>
            <dd class="break-all">{pkg.source_summary.url}</dd>
          </div>
          <div>
            <dt class="ui-title font-medium">Default branch</dt>
            <dd class="break-all">{pkg.source_summary.default_ref}</dd>
          </div>
          {#if pkg.source_summary.last_successful_sync_at}
            <div>
              <dt class="ui-title font-medium">Last successful sync</dt>
              <dd>{formatDate(pkg.source_summary.last_successful_sync_at)}</dd>
            </div>
          {/if}
        {/if}
        <div>
          <dt class="ui-title font-medium">Package type</dt>
          <dd>{pkg.type}</dd>
        </div>
        {#if !pkg.source_summary && pkg.source_url}
          <div>
            <dt class="ui-title font-medium">Source URL</dt>
            <dd class="break-all">{pkg.source_url}</dd>
          </div>
        {/if}
        <div>
          <dt class="ui-title font-medium">Created</dt>
          <dd>{formatDate(pkg.created_at)}</dd>
        </div>
        {#if pkg.updated_at}
          <div>
            <dt class="ui-title font-medium">Updated</dt>
            <dd>{formatDate(pkg.updated_at)}</dd>
          </div>
        {/if}
      </dl>
    </Card>

    <nav aria-label="Package sections" class="flex flex-wrap gap-3 text-sm">
      <a class="ui-button-secondary rounded-full px-4 py-2" href="#versions">
        Versions
      </a>
      <a class="ui-button-secondary rounded-full px-4 py-2" href="#readme">
        README
      </a>
    </nav>
  </section>

  <div id="versions" class="scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)]">
    <Card as="section" className="rounded-[1.75rem] p-6">
      <h2 class="ui-title text-xl font-semibold">Versions</h2>
      <p class="ui-muted mt-2 text-sm">
        Hide or restore specific versions in the registry without deleting
        release data.
      </p>
      <p class="ui-muted mt-2 text-sm">
        {listedReleaseCount} available · {hiddenReleaseCount} hidden
      </p>
      {#if showReleasePagination}
        <div class="mt-4">
          <Pagination
            currentPage={releasePage}
            lastPage={releaseLastPage}
            hasMorePages={releasePage < releaseLastPage}
            hasPreviousPages={releasePage > 1}
            from={releaseFrom}
            to={releaseTo}
            total={releases.length}
            perPage={releasesPerPage}
            onPageChange={handleReleasePageChange}
            showPerPageSelector={false}
          />
        </div>
      {/if}
      <div class="mt-4 grid gap-3">
        {#if releases.length === 0}
          <p class="text-sm ui-muted">No versions indexed yet.</p>
        {:else}
          {#each paginatedReleases as release (release.version)}
            <InsetCard as="article" className="p-4">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <details class="group min-w-0 flex-1">
                  <summary
                    class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-2"
                  >
                    <ChevronRight
                      aria-hidden="true"
                      class="size-4 shrink-0 transition-transform group-open:rotate-90"
                    />
                    <span class="ui-title break-all font-semibold">
                      {release.version}
                    </span>
                    <span class="ui-muted text-sm">
                      {formatDate(release.released_at)}
                    </span>
                    <Badge tone={release.active ? "accent" : "muted"}>
                      {release.active
                        ? "Listed in registry"
                        : "Hidden from registry"}
                    </Badge>
                    <span class="ui-muted text-sm">
                      {release.download_count} downloads
                    </span>
                  </summary>
                  <div class="ui-muted mt-3 grid min-w-0 gap-2 text-sm">
                    <p class="break-all">
                      <span class="font-medium ui-title">ref</span>
                      <span class="font-mono">{release.reference}</span>
                    </p>
                    <p class="break-all">
                      <span class="font-medium ui-title">Dist:</span>
                      {release.dist_url}
                    </p>
                    <p class="break-all">
                      <span class="font-medium ui-title">Source:</span>
                      {release.source_url}
                    </p>
                    <p>
                      <span class="font-medium ui-title">Imported:</span>
                      {formatDate(release.created_at)}
                    </p>
                  </div>
                </details>
                {#if can_manage_packages}
                  <Button
                    type="button"
                    variant={release.active ? "soft" : "primary"}
                    className="h-9 shrink-0 self-start rounded-full px-4 text-xs"
                    onclick={() => (pendingRelease = release)}
                  >
                    {release.active ? "Hide release" : "Restore release"}
                  </Button>
                {/if}
              </div>
            </InsetCard>
          {/each}
        {/if}
      </div>
      {#if showReleasePagination}
        <div class="mt-4">
          <Pagination
            currentPage={releasePage}
            lastPage={releaseLastPage}
            hasMorePages={releasePage < releaseLastPage}
            hasPreviousPages={releasePage > 1}
            from={releaseFrom}
            to={releaseTo}
            total={releases.length}
            perPage={releasesPerPage}
            onPageChange={handleReleasePageChange}
            showPerPageSelector={false}
          />
        </div>
      {/if}
    </Card>
  </div>
  <div id="readme" class="scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)]">
    <Card as="section" className="rounded-[1.75rem] p-6">
      <h2 class="ui-title text-xl font-semibold">README</h2>
      {#if pkg.readme_html}
        <div class="relative mt-4">
          <div
            class="ui-markdown ui-muted overflow-x-auto"
            class:max-h-72={!readmeExpanded}
            class:overflow-hidden={!readmeExpanded}
          >
            <!-- HTML is sanitized during markdown conversion on the backend. -->
            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
            {@html pkg.readme_html}
          </div>
          {#if !readmeExpanded}
            <div
              class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-b from-transparent to-[var(--surface)]"
            ></div>
          {/if}
        </div>
        <Button
          type="button"
          variant="soft"
          className="mt-4 rounded-full px-4 py-2 text-sm"
          onclick={() => (readmeExpanded = !readmeExpanded)}
        >
          {readmeExpanded ? "Collapse README" : "Show full README"}
        </Button>
      {:else}
        <p class="ui-muted mt-4 text-sm">This package has no README.</p>
      {/if}
    </Card>
  </div>

  <ConfirmationModal
    open={pendingRelease !== null}
    eyebrow="Release"
    title={pendingRelease?.active
      ? `Hide release ${pendingRelease.version}?`
      : `Restore release ${pendingRelease?.version}?`}
    description={pendingRelease?.active
      ? `Composer will stop resolving ${pendingRelease.version} from this registry. Release data is kept.`
      : `${pendingRelease?.version} returns to the registry metadata.`}
    confirmLabel={pendingRelease?.active ? "Hide release" : "Restore release"}
    variant={pendingRelease?.active ? "danger" : "primary"}
    {confirming}
    onConfirm={confirmRelease}
    onCancel={() => (pendingRelease = null)}
  />
</AppShell>
