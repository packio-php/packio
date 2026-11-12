<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import { router } from "@inertiajs/svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Link from "@/Components/core/Link.svelte";
  import BackButton from "@/Components/ui/BackButton.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { formatDate } from "@/lib/date";
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

  let anonymousAccessEnabled = $derived(Boolean(pkg?.anonymous_access));
  let publicInstallRequiresToken = $derived(
    pkg?.visibility === "public" && !anonymousAccessEnabled,
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

  function submit(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    const form = event.currentTarget;

    router.post(form.action, Object.fromEntries(new FormData(form)), {
      preserveScroll: true,
    });
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
      <div class="flex items-center justify-between gap-4">
        <div>
          <SectionHeading
            eyebrow={pkg.visibility}
            title={pkg.name}
            description=""
          />
          <div class="mt-3 flex flex-wrap gap-2">
            <Badge
              tone={pkg.visibility === "private" || publicInstallRequiresToken
                ? "danger"
                : "accent"}
            >
              {pkg.visibility === "private" || publicInstallRequiresToken
                ? "Token required"
                : "Public"}
            </Badge>
            <Badge tone={pkg.active ? "accent" : "muted"}>
              {pkg.active ? "Listed in registry" : "Hidden from registry"}
            </Badge>
            <Badge tone="muted">
              {pkg.download_count} downloads
            </Badge>
          </div>
        </div>
      </div>

      <p class="ui-muted mt-4 text-sm leading-6">
        {pkg.description || "No description"}
      </p>

      <div class="ui-muted mt-6 grid gap-3 text-sm">
        <p>
          <span class="font-medium ui-title">Type:</span>
          {pkg.type}
        </p>
        <p>
          <span class="font-medium ui-title">Source type:</span>
          {pkg.source_type}
        </p>
        <p class="break-all">
          <span class="font-medium ui-title">Source URL:</span>
          {pkg.source_url}
        </p>
        <p>
          <span class="font-medium ui-title">Registry access:</span>
          {pkg.access_hint}
        </p>
        <p>
          <span class="font-medium ui-title">Created at:</span>
          {formatDate(pkg.created_at)}
        </p>
        <p>
          <span class="font-medium ui-title">Updated at:</span>
          {formatDate(pkg.updated_at)}
        </p>
      </div>

      {#if pkg.source_summary}
        <InsetCard className="mt-6 p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="ui-eyebrow">Managed by source</p>
              <p class="ui-title mt-2 text-lg font-semibold">
                {pkg.source_summary.name}
              </p>
              <p class="ui-muted mt-2 break-all text-sm">
                {pkg.source_summary.url}
              </p>
            </div>
            <Badge
              tone={pkg.source_summary.last_sync_status === "failed"
                ? "danger"
                : pkg.source_summary.last_sync_status === "success"
                  ? "accent"
                  : "gold"}
            >
              {pkg.source_summary.last_sync_status}
            </Badge>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <Badge tone={pkg.source_summary.active ? "accent" : "muted"}>
              {pkg.source_summary.active ? "Source active" : "Source inactive"}
            </Badge>
            <Badge
              tone={pkg.source_summary.visibility === "private"
                ? "danger"
                : "accent"}
            >
              {pkg.source_summary.visibility}
            </Badge>
            {#if pkg.source_summary.archived}
              <Badge tone="muted">Archived</Badge>
            {/if}
          </div>

          <div class="ui-muted mt-4 grid gap-2 text-sm">
            <p>
              <span class="font-medium ui-title">Default ref:</span>
              {pkg.source_summary.default_ref}
            </p>
            {#if pkg.source_summary.last_successful_sync_at}
              <p>
                <span class="font-medium ui-title">Last success:</span>
                {formatDate(pkg.source_summary.last_successful_sync_at)}
              </p>
            {/if}
            {#if pkg.source_summary.last_failed_sync_at}
              <p>
                <span class="font-medium ui-title">Last failure:</span>
                {formatDate(pkg.source_summary.last_failed_sync_at)}
              </p>
            {/if}
            {#if pkg.source_summary.last_sync_error}
              <p style="color: var(--flash-error-text);">
                <span class="font-medium ui-title">Last error:</span>
                {pkg.source_summary.last_sync_error}
              </p>
            {/if}
          </div>

          <div class="mt-4 flex flex-wrap gap-3">
            <Link
              class="ui-button-secondary rounded-full px-4 py-2 text-sm font-medium"
              href={withOrganization(`/sources/${pkg.source_summary.id}`)}
            >
              Open source details
            </Link>
            <Link
              class="ui-button-secondary rounded-full px-4 py-2 text-sm font-medium"
              href={withOrganization("/sources")}
            >
              Open sources
            </Link>
          </div>
        </InsetCard>
      {/if}
    </Card>
  </section>

  {#if pkg.readme_html}
    <Card as="section" className="rounded-[1.75rem] p-6">
      <SectionHeading
        eyebrow="Documentation"
        title="README"
        description="Synced from the package source and shown here for quick reference."
      />
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
    </Card>
  {/if}

  <Card as="section" className="rounded-[1.75rem] p-6">
    <SectionHeading
      eyebrow="Releases"
      title="Version history"
      description="Hide or restore specific versions in the registry without deleting release data."
    />
    <div class="mt-4 flex flex-wrap gap-2" aria-label="Release counts">
      <Badge tone="accent">{listedReleaseCount} listed in registry</Badge>
      <Badge tone="muted">{hiddenReleaseCount} hidden from registry</Badge>
      <Badge tone="neutral">{releases.length} total releases</Badge>
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
    <div class="mt-4 grid gap-3">
      {#if releases.length === 0}
        <p class="text-sm ui-muted">No releases indexed yet.</p>
      {:else}
        {#each paginatedReleases as release (release.version)}
          <InsetCard as="article" className="p-5">
            <div
              class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
              <div class="min-w-0">
                <p class="ui-title text-lg font-semibold">
                  {release.version}
                </p>
                <div class="mt-2 grid min-w-0 gap-2">
                  <p
                    class="ui-muted min-w-0 max-w-full text-[11px] leading-4 sm:text-xs"
                  >
                    <span class="font-medium ui-title">ref</span>
                    <span class="font-mono break-all [overflow-wrap:anywhere]">
                      {release.reference}
                    </span>
                  </p>
                  <div>
                    <Badge
                      tone="muted"
                      className="whitespace-nowrap px-2.5 py-0.5"
                    >
                      {release.download_count} downloads
                    </Badge>
                  </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                  <Badge tone={release.active ? "accent" : "muted"}>
                    {release.active
                      ? "Listed in registry"
                      : "Hidden from registry"}
                  </Badge>
                </div>
              </div>
              <div class="w-full sm:min-w-36 sm:w-auto sm:text-right">
                <div class="grid grid-cols-2 gap-3 sm:block">
                  <div>
                    <p class="text-xs ui-muted">Released</p>
                    <p class="text-sm ui-title">
                      {formatDate(release.released_at)}
                    </p>
                  </div>
                  <div>
                    <p class="text-xs ui-muted sm:mt-2">Imported</p>
                    <p class="text-sm ui-muted">
                      {formatDate(release.created_at)}
                    </p>
                  </div>
                </div>
                {#if can_manage_packages}
                  <form
                    class="mt-3 sm:flex sm:justify-end"
                    method="POST"
                    action={withOrganization(
                      `/packages/${pkg.name}/releases/activity`,
                    )}
                    onsubmit={(event) => {
                      event.preventDefault();
                      submit(
                        event as SubmitEvent & {
                          currentTarget: EventTarget & HTMLFormElement;
                        },
                      );
                    }}
                  >
                    <input
                      type="hidden"
                      name="version"
                      value={release.version}
                    />
                    <input
                      type="hidden"
                      name="active"
                      value={release.active ? "0" : "1"}
                    />
                    <Button
                      type="submit"
                      variant={release.active ? "soft" : "primary"}
                      className="h-9 w-full rounded-full px-4 text-xs sm:w-auto"
                    >
                      {release.active ? "Hide release" : "Show release"}
                    </Button>
                  </form>
                {/if}
              </div>
            </div>
            <div class="ui-muted mt-4 grid gap-2 text-sm">
              <p class="break-all">
                <span class="font-medium ui-title">Dist:</span>
                {release.dist_url}
              </p>
              <p class="break-all">
                <span class="font-medium ui-title">Source:</span>
                {release.source_url}
              </p>
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
</AppShell>
