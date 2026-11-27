<script lang="ts">
  import { onMount } from "svelte";
  import { router, usePage } from "@inertiajs/svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Link from "@/Components/core/Link.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import PackagesFilterToolbar from "./Partials/PackagesFilterToolbar.svelte";
  import PackagesGrid from "./Partials/PackagesGrid.svelte";
  import PackagesTable from "./Partials/PackagesTable.svelte";
  import { activeFilterChips, clearFiltersQuery } from "./packageFilters";
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

  let anonymousAccessEnabled = $derived(
    Boolean(organization_summary?.anonymous_access),
  );
  let activeFilterCount = $derived(
    (filters.search !== "" ? 1 : 0) +
      (filters.visibility.length > 0 ? 1 : 0) +
      (filters.listed !== "" ? 1 : 0) +
      (filters.source !== "" ? 1 : 0) +
      (filters.source_sync_status.length > 0 ? 1 : 0),
  );
  let chips = $derived(activeFilterChips(filters, package_filter_options));
  let showPackageToolbar = $derived(
    package_stats.total > 0 || activeFilterCount > 0,
  );
  let catalogEmpty = $derived(
    package_stats.total === 0 && activeFilterCount === 0,
  );
  let hasSourcesAccess = $derived(
    Boolean(auth?.check && usePage().props.current_membership),
  );

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

  function clearFilters(): void {
    router.get(
      withOrganization("/packages"),
      clearFiltersQuery(filters.per_page),
      { preserveScroll: true },
    );
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
    <h2 class="ui-title text-2xl font-semibold">Packages</h2>

    {#if showPackageToolbar}
      <PackagesFilterToolbar
        {filters}
        filterOptions={package_filter_options}
        mode={listViewMode}
        onModeChange={setListViewMode}
        {withOrganization}
      />
    {/if}

    {#if chips.length > 0 || (activeFilterCount > 0 && sections.length > 0)}
      <div class="flex flex-wrap items-center gap-2">
        {#each chips as chip (chip)}
          <Badge tone="neutral">{chip}</Badge>
        {/each}
        {#if activeFilterCount > 0 && sections.length > 0}
          <Button
            className="rounded-full px-3 py-1 text-xs"
            type="button"
            variant="secondary"
            onclick={clearFilters}
          >
            Clear filters
          </Button>
        {/if}
      </div>
    {/if}

    {#if pagination.last_page > 1}
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
    {:else if pagination.total > 0}
      <p class="text-sm ui-muted">
        {pagination.total}
        {pagination.total === 1 ? "package" : "packages"}
      </p>
    {/if}

    {#if sections.length === 0}
      <Card className="rounded-[1.75rem] p-6">
        {#if catalogEmpty}
          <p class="ui-title font-medium">No packages yet</p>
          <p class="mt-2 text-sm ui-muted">
            Packages appear here after a source syncs. Connect a repository in
            Sources.
          </p>
          {#if hasSourcesAccess}
            <Link
              class="ui-button-secondary mt-4 inline-flex rounded-full px-4 py-2 text-sm font-medium"
              href={withOrganization("/sources")}
            >
              Open sources
            </Link>
          {/if}
        {:else}
          <p class="ui-title font-medium">No packages match your search</p>
          <Button
            className="mt-4"
            type="button"
            variant="secondary"
            onclick={clearFilters}
          >
            Clear filters
          </Button>
        {/if}
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

    {#if pagination.last_page > 1}
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
