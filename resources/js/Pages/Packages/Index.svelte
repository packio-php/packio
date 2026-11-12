<script lang="ts">
  import { onMount } from "svelte";
  import { router } from "@inertiajs/svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import Link from "@/Components/core/Link.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import PackagesFilterToolbar from "./Partials/PackagesFilterToolbar.svelte";
  import PackagesGrid from "./Partials/PackagesGrid.svelte";
  import PackagesTable from "./Partials/PackagesTable.svelte";
  import { cleanQuery, queryString, type QueryValue } from "@/lib/queryFilters";
  import {
    listViewStorageKey,
    readStoredListViewMode,
    storeListViewMode,
    type ListViewMode,
  } from "@/lib/listViewMode";
  import type { AuthPayload } from "@/types/auth";
  import type { OrganizationSummary } from "@/types/organization";
  import type {
    PackageFilterOptions,
    PackageIndexFilters,
    PackageStatsPayload,
    PackagesIndexSection,
    PaginationPayload,
  } from "@/types/pages";

  const defaultPagination: PaginationPayload<PackagesIndexSection> = {
    current_page: 1,
    from: 0,
    has_more_pages: false,
    has_previous_pages: false,
    last_page: 1,
    per_page: 18,
    to: 0,
    total: 0,
  };

  const defaultPackageStats: PackageStatsPayload = {
    listed: 0,
    private: 0,
    public: 0,
    total: 0,
  };

  const defaultPackageFilters: PackageIndexFilters = {
    listed: "",
    page: 1,
    per_page: 18,
    search: "",
    source: "",
    source_sync_status: [],
    visibility: [],
  };

  const defaultPackageFilterOptions: PackageFilterOptions = {
    source_sync_statuses: [],
  };

  interface Props {
    auth?: AuthPayload;
    sections?: PackagesIndexSection[];
    package_stats?: PackageStatsPayload;
    package_filter_options?: PackageFilterOptions;
    filters?: PackageIndexFilters;
    pagination?: PaginationPayload<PackagesIndexSection>;
    organization_summary?: OrganizationSummary | null;
    initial_list_view_mode?: ListViewMode;
  }

  let {
    auth = {},
    sections = [],
    package_stats = defaultPackageStats,
    package_filter_options = defaultPackageFilterOptions,
    filters = defaultPackageFilters,
    pagination = defaultPagination,
    organization_summary = null,
    initial_list_view_mode = "grid",
  }: Props = $props();

  let listViewMode: ListViewMode = $state("grid");
  let listViewModeInitialized = $state(false);

  $effect(() => {
    if (!listViewModeInitialized) {
      listViewMode = initial_list_view_mode;
    }
  });

  let packageTotal = $derived(package_stats.total);
  let publicCount = $derived(package_stats.public);
  let privateCount = $derived(package_stats.private);
  let listedCount = $derived(package_stats.listed);
  let anonymousAccessEnabled = $derived(
    Boolean(organization_summary?.anonymous_access),
  );
  let hasPackages = $derived(packageTotal > 0);
  let activeFilterCount = $derived(
    (filters.search !== "" ? 1 : 0) +
      (filters.visibility.length > 0 ? 1 : 0) +
      (filters.listed !== "" ? 1 : 0) +
      (filters.source !== "" ? 1 : 0) +
      (filters.source_sync_status.length > 0 ? 1 : 0),
  );
  let showPackageToolbar = $derived(hasPackages || activeFilterCount > 0);

  onMount(() => {
    listViewMode = readStoredListViewMode(
      listViewStorageKey,
      initial_list_view_mode,
    );
    listViewModeInitialized = true;
  });

  function setListViewMode(mode: ListViewMode): void {
    listViewMode = mode;
    storeListViewMode(listViewStorageKey, mode);
  }

  function withOrganization(path: string): string {
    if (!organization_summary?.slug) {
      return path;
    }

    return path === "/"
      ? `/organization/${organization_summary.slug}`
      : `/organization/${organization_summary.slug}${path}`;
  }

  function handlePageChange(page: number): void {
    router.get(withOrganization("/packages"), currentQuery({ page }), {
      preserveScroll: true,
    });
  }

  function currentQuery(
    overrides: Record<string, QueryValue> = {},
  ): Record<string, boolean | number | string | string[]> {
    return cleanQuery({
      search: filters.search,
      visibility: filters.visibility,
      listed: filters.listed,
      source: filters.source,
      source_sync_status: filters.source_sync_status,
      per_page: filters.per_page,
      page: filters.page,
      ...overrides,
    });
  }

  function packagesReturnUrl(): string {
    return `${withOrganization("/packages")}${queryString(currentQuery())}`;
  }

  function packageDetailsUrl(section: PackagesIndexSection): string {
    return `${withOrganization(`/packages/${section.name}`)}?return_to=${encodeURIComponent(packagesReturnUrl())}`;
  }
</script>

<svelte:head>
  <title>Packages</title>
</svelte:head>

<AppShell title="Packages" {auth}>
  <section class="grid gap-6">
    <Card className="rounded-[1.9rem] p-7 md:p-9">
      <div
        class="grid gap-8 lg:grid-cols-[minmax(0,1.5fr)_minmax(16rem,0.9fr)]"
      >
        <div class="min-w-0">
          <SectionHeading
            eyebrow={`${organization_summary?.slug ?? "organization"} packages`}
            title={`${organization_summary?.name ?? "Organization"} packages`}
            description="Packages are the Composer-facing catalog of what this organization publishes. Use Sources to connect repositories, configure credentials, manage webhooks, and troubleshoot synchronization."
            className="max-w-3xl"
          />

          <div class="mt-6 grid gap-4 md:grid-cols-3">
            <InsetCard className="p-4">
              <p class="ui-eyebrow">Packages</p>
              <p class="ui-title mt-2 text-3xl font-semibold">
                {packageTotal}
              </p>
            </InsetCard>
            <InsetCard className="p-4">
              <p class="ui-eyebrow">Listed</p>
              <p class="ui-title mt-2 text-3xl font-semibold">{listedCount}</p>
            </InsetCard>
            <InsetCard className="p-4">
              <p class="ui-eyebrow">Visibility split</p>
              <p class="ui-title mt-2 text-lg font-semibold">
                {publicCount} public / {privateCount} private
              </p>
            </InsetCard>
          </div>
        </div>

        <div class="grid gap-4 self-start">
          <InsetCard className="p-5">
            <p class="text-sm font-medium ui-title">What this page is for</p>
            <p class="mt-3 text-sm leading-7 ui-muted">
              Browse package names, latest versions, visibility, and download
              endpoints. This is the catalog consumers use when they require
              dependencies through Composer.
            </p>
          </InsetCard>

          <InsetCard className="p-5">
            <p class="text-sm font-medium ui-title">Where to manage sources</p>
            <p class="mt-3 text-sm leading-7 ui-muted">
              Repository connections, provider authorization, webhook secrets,
              manual re-sync, and provider troubleshooting live in the
              <span class="font-medium ui-title"> Sources </span>
              section.
            </p>
            <Link
              class="ui-button-secondary mt-4 inline-flex rounded-full px-4 py-2 text-sm font-medium"
              href={withOrganization("/sources")}
            >
              Open sources
            </Link>
          </InsetCard>
        </div>
      </div>
    </Card>

    {#if showPackageToolbar}
      <PackagesFilterToolbar
        {filters}
        filterOptions={package_filter_options}
        mode={listViewMode}
        onModeChange={setListViewMode}
        {withOrganization}
      />
    {/if}

    {#if pagination.total > 0}
      <Pagination
        currentPage={pagination.current_page}
        lastPage={pagination.last_page}
        hasMorePages={pagination.has_more_pages}
        hasPreviousPages={pagination.has_previous_pages}
        from={pagination.from}
        to={pagination.to}
        total={pagination.total}
        perPage={pagination.per_page}
        onPageChange={handlePageChange}
        showPerPageSelector={false}
      />
    {/if}

    {#if sections.length === 0}
      <Card className="rounded-[1.75rem] p-6">
        <p class="text-sm ui-muted">No packages found.</p>
      </Card>
    {:else if listViewMode === "grid"}
      <PackagesGrid
        {sections}
        {anonymousAccessEnabled}
        {withOrganization}
        {packageDetailsUrl}
      />
    {:else}
      <PackagesTable
        {sections}
        {anonymousAccessEnabled}
        {withOrganization}
        {packageDetailsUrl}
      />
    {/if}

    {#if pagination.total > 0}
      <Pagination
        currentPage={pagination.current_page}
        lastPage={pagination.last_page}
        hasMorePages={pagination.has_more_pages}
        hasPreviousPages={pagination.has_previous_pages}
        from={pagination.from}
        to={pagination.to}
        total={pagination.total}
        perPage={pagination.per_page}
        onPageChange={handlePageChange}
        showPerPageSelector={false}
      />
    {/if}
  </section>
</AppShell>
