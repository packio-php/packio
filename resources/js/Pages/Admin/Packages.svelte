<script lang="ts">
  import { onMount } from "svelte";
  import { router } from "@inertiajs/svelte";
  import { ChevronDown, FilterX, ListFilter, Search } from "@lucide/svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ListViewToggle from "@/Components/ui/ListViewToggle.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import TableSortButton from "@/Components/ui/TableSortButton.svelte";
  import { Endpoints } from "@/lib/api";
  import { formatDate } from "@/lib/date";
  import {
    readStoredListViewMode,
    storeListViewMode,
    type ListViewMode,
  } from "@/lib/listViewMode";
  import { cleanQuery, type QueryValue } from "@/lib/queryFilters";
  import type { AuthPayload } from "@/types/auth";

  const viewStorageKey = "packio-admin-packages-view-mode";
  const visibilityOptions = ["public", "private"] as const;

  interface Props {
    auth?: AuthPayload;
    packages?: AdminPackagePayload[];
    organizations?: AdminOrganization[];
    package_stats?: AdminPackageStats;
    filter_options?: AdminPackageFilterOptions;
    filters?: AdminPackageFilters;
    pagination?: PaginationMeta;
    initial_list_view_mode?: ListViewMode;
  }

  let {
    auth = {},
    packages = [],
    organizations = [],
    package_stats = {
      hidden: 0,
      listed: 0,
      managed: 0,
      manual: 0,
      private: 0,
      public: 0,
      total: 0,
    },
    filter_options = {
      source_providers: [],
      source_sync_statuses: [],
    },
    filters = defaultFilters(),
    pagination = {
      current_page: 1,
      from: 0,
      has_more_pages: false,
      has_previous_pages: false,
      last_page: 1,
      per_page: 20,
      to: 0,
      total: 0,
    },
    initial_list_view_mode = "table",
  }: Props = $props();

  let filtersOpen = $state(false);
  let advancedFiltersOpen = $state(false);
  let listViewMode: ListViewMode = $state("table");
  let listViewModeInitialized = $state(false);
  let currentSort = $derived(filters?.sort ?? "updated_at");
  let currentDirection = $derived(filters?.direction ?? "desc");

  $effect(() => {
    if (!listViewModeInitialized) {
      listViewMode = initial_list_view_mode;
    }
  });

  interface AdminOrganization {
    id: string;
    slug: string;
    name: string;
    status: string;
  }

  interface FilterOption {
    value: string;
    label: string;
  }

  interface AdminPackagePayload {
    active: boolean;
    created_at: string;
    description: string;
    download_count: number;
    name: string;
    organization: AdminOrganization | null;
    source: {
      active: boolean;
      archived: boolean;
      id: string;
      last_sync_status: string;
      name: string;
      provider: string;
      provider_label: string;
      status: string;
      url: string;
    } | null;
    source_id: string;
    source_type: string;
    source_url: string;
    type: string;
    updated_at: string;
    visibility: "private" | "public" | string;
  }

  interface AdminPackageStats {
    hidden: number;
    listed: number;
    managed: number;
    manual: number;
    private: number;
    public: number;
    total: number;
  }

  interface AdminPackageFilterOptions {
    source_providers: FilterOption[];
    source_sync_statuses: FilterOption[];
  }

  interface AdminPackageFilters {
    organization_id: string;
    search: string;
    visibility: string[];
    listed: string;
    source: string;
    source_provider: string[];
    source_sync_status: string[];
    package_name: string;
    source_id: string;
    source_url: string;
    created_from: string;
    created_to: string;
    updated_from: string;
    updated_to: string;
    min_downloads: string | number;
    max_downloads: string | number;
    sort: string;
    direction: "asc" | "desc";
    page: number;
    per_page: number;
  }

  interface PaginationMeta {
    total: number;
    current_page: number;
    per_page: number;
    last_page: number;
    has_more_pages: boolean;
    has_previous_pages: boolean;
    from: number;
    to: number;
  }

  function defaultFilters(): AdminPackageFilters {
    return {
      organization_id: "",
      search: "",
      visibility: [],
      listed: "",
      source: "",
      source_provider: [],
      source_sync_status: [],
      package_name: "",
      source_id: "",
      source_url: "",
      created_from: "",
      created_to: "",
      updated_from: "",
      updated_to: "",
      min_downloads: "",
      max_downloads: "",
      sort: "updated_at",
      direction: "desc",
      page: 1,
      per_page: 20,
    };
  }

  onMount(() => {
    listViewMode = readStoredListViewMode(
      viewStorageKey,
      initial_list_view_mode,
    );
    listViewModeInitialized = true;
  });

  function setListViewMode(mode: ListViewMode): void {
    listViewMode = mode;
    storeListViewMode(viewStorageKey, mode);
  }

  function checked(values: string[], value: string): boolean {
    return values.includes(value);
  }

  function activeAdvancedFilterCount(): number {
    let count = 0;

    if ((filters?.package_name ?? "") !== "") count += 1;
    if ((filters?.source_id ?? "") !== "") count += 1;
    if ((filters?.source_url ?? "") !== "") count += 1;
    if (
      (filters?.created_from ?? "") !== "" ||
      (filters?.created_to ?? "") !== ""
    ) {
      count += 1;
    }
    if (
      (filters?.updated_from ?? "") !== "" ||
      (filters?.updated_to ?? "") !== ""
    ) {
      count += 1;
    }
    if (`${filters?.min_downloads ?? ""}` !== "") count += 1;
    if (`${filters?.max_downloads ?? ""}` !== "") count += 1;

    return count;
  }

  function activeFilterCount(): number {
    let count = activeAdvancedFilterCount();

    if ((filters?.organization_id ?? "") !== "") count += 1;
    if ((filters?.search ?? "") !== "") count += 1;
    if ((filters?.visibility ?? []).length > 0) count += 1;
    if ((filters?.listed ?? "") !== "") count += 1;
    if ((filters?.source ?? "") !== "") count += 1;
    if ((filters?.source_provider ?? []).length > 0) count += 1;
    if ((filters?.source_sync_status ?? []).length > 0) count += 1;

    return count;
  }

  function currentQuery(
    overrides: Record<string, QueryValue> = {},
  ): Record<string, boolean | number | string | string[]> {
    return cleanQuery({
      ...filters,
      ...overrides,
    });
  }

  function applyFilters(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    const formData = new FormData(event.currentTarget);

    router.get(
      Endpoints.admin.packages(),
      cleanQuery({
        organization_id: String(formData.get("organization_id") ?? ""),
        search: String(formData.get("search") ?? ""),
        visibility: formData.getAll("visibility").map(String),
        listed: String(formData.get("listed") ?? ""),
        source: String(formData.get("source") ?? ""),
        source_provider: formData.getAll("source_provider").map(String),
        source_sync_status: formData.getAll("source_sync_status").map(String),
        package_name: String(formData.get("package_name") ?? ""),
        source_id: String(formData.get("source_id") ?? ""),
        source_url: String(formData.get("source_url") ?? ""),
        created_from: String(formData.get("created_from") ?? ""),
        created_to: String(formData.get("created_to") ?? ""),
        updated_from: String(formData.get("updated_from") ?? ""),
        updated_to: String(formData.get("updated_to") ?? ""),
        min_downloads: String(formData.get("min_downloads") ?? ""),
        max_downloads: String(formData.get("max_downloads") ?? ""),
        sort: filters?.sort ?? "updated_at",
        direction: filters?.direction ?? "desc",
        per_page: filters?.per_page ?? 20,
        page: 1,
      }),
      { preserveScroll: true },
    );
  }

  function clearFilters(): void {
    router.get(
      Endpoints.admin.packages(),
      cleanQuery({
        sort: "updated_at",
        direction: "desc",
        per_page: filters?.per_page ?? 20,
      }),
      { preserveScroll: true },
    );
  }

  function sortPackages(field: string): void {
    const currentSort = filters?.sort ?? "updated_at";
    const currentDirection = filters?.direction ?? "desc";
    const nextDirection =
      currentSort === field && currentDirection === "asc" ? "desc" : "asc";

    router.get(
      Endpoints.admin.packages(),
      currentQuery({
        sort: field,
        direction: nextDirection,
        page: 1,
      }),
      { preserveScroll: true },
    );
  }

  function handlePageChange(page: number): void {
    router.get(Endpoints.admin.packages(), currentQuery({ page }), {
      preserveScroll: true,
    });
  }

  function handlePerPageChange(perPage: number): void {
    router.get(
      Endpoints.admin.packages(),
      currentQuery({ page: 1, per_page: perPage }),
      { preserveScroll: true },
    );
  }

  function syncTone(status: string): string {
    if (status === "failed") return "danger";
    return status === "success" ? "accent" : "gold";
  }
</script>

<svelte:head>
  <title>Admin Packages</title>
</svelte:head>

<AdminShell title="Admin Packages" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    <div class="grid min-w-0 gap-6">
      <SectionHeading
        eyebrow="Administration"
        title="Packages"
        description="Global package catalog across every organization."
      />

      <div class="grid gap-3 md:grid-cols-4">
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Packages</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {package_stats.total}
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Listed</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {package_stats.listed} / {package_stats.hidden} hidden
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Visibility</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {package_stats.public} / {package_stats.private}
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Source</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {package_stats.managed} / {package_stats.manual}
          </p>
        </div>
      </div>

      <Card className="rounded-[1.25rem] p-4">
        <div class="grid gap-4">
          <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="flex items-center gap-2">
              <div class="relative">
                <Button
                  aria-controls="admin-package-filters"
                  aria-expanded={filtersOpen}
                  aria-label={filtersOpen ? "Hide filters" : "Show filters"}
                  className="rounded-full"
                  title={filtersOpen ? "Hide filters" : "Show filters"}
                  type="button"
                  variant="icon"
                  onclick={() => {
                    filtersOpen = !filtersOpen;
                  }}
                >
                  <ListFilter aria-hidden="true" size={18} />
                </Button>
                {#if activeFilterCount() > 0}
                  <span
                    class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full px-1 text-[0.65rem] font-bold"
                    style="background-color: var(--accent); color: var(--button-primary-text);"
                  >
                    {activeFilterCount()}
                  </span>
                {/if}
              </div>

              {#if activeFilterCount() > 0}
                <Button
                  aria-label="Clear filters"
                  className="rounded-full"
                  title="Clear filters"
                  type="button"
                  variant="icon"
                  onclick={clearFilters}
                >
                  <FilterX aria-hidden="true" size={18} />
                </Button>
              {/if}
            </div>

            <ListViewToggle mode={listViewMode} onChange={setListViewMode} />
          </div>

          {#if filtersOpen}
            <form
              id="admin-package-filters"
              class="grid gap-4"
              onsubmit={(event) => {
                event.preventDefault();
                applyFilters(
                  event as SubmitEvent & {
                    currentTarget: EventTarget & HTMLFormElement;
                  },
                );
              }}
            >
              <div class="grid gap-4 lg:grid-cols-4">
                <label class="grid gap-2 text-sm">
                  <span class="font-medium ui-title">Search</span>
                  <input
                    class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                    name="search"
                    type="search"
                    value={filters?.search ?? ""}
                  />
                </label>

                <label class="grid gap-2 text-sm">
                  <span class="font-medium ui-title">Organization</span>
                  <Select
                    className="rounded-xl px-3 py-2 text-sm"
                    name="organization_id"
                    value={filters?.organization_id ?? ""}
                  >
                    <option value="">Any organization</option>
                    {#each organizations as organization (organization.id)}
                      <option value={organization.id}>
                        {organization.name}
                      </option>
                    {/each}
                  </Select>
                </label>

                <label class="grid gap-2 text-sm">
                  <span class="font-medium ui-title">Listed</span>
                  <Select
                    className="rounded-xl px-3 py-2 text-sm"
                    name="listed"
                    value={filters?.listed ?? ""}
                  >
                    <option value="">Any listed state</option>
                    <option value="listed">Listed</option>
                    <option value="hidden">Hidden</option>
                  </Select>
                </label>

                <label class="grid gap-2 text-sm">
                  <span class="font-medium ui-title">Source</span>
                  <Select
                    className="rounded-xl px-3 py-2 text-sm"
                    name="source"
                    value={filters?.source ?? ""}
                  >
                    <option value="">Any source</option>
                    <option value="managed">Managed</option>
                    <option value="manual">Manual</option>
                  </Select>
                </label>
              </div>

              <div class="grid gap-4 lg:grid-cols-3">
                <fieldset class="grid gap-2 text-sm">
                  <legend class="font-medium ui-title">Visibility</legend>
                  <div class="flex flex-wrap gap-3">
                    {#each visibilityOptions as visibility (visibility)}
                      <label class="flex items-center gap-2 ui-title">
                        <input
                          checked={checked(
                            filters?.visibility ?? [],
                            visibility,
                          )}
                          class="h-4 w-4 rounded border-(--panel-border)"
                          name="visibility"
                          type="checkbox"
                          value={visibility}
                        />
                        {visibility}
                      </label>
                    {/each}
                  </div>
                </fieldset>

                {#if filter_options.source_providers.length > 0}
                  <fieldset class="grid gap-2 text-sm">
                    <legend class="font-medium ui-title">
                      Source provider
                    </legend>
                    <div class="flex flex-wrap gap-3">
                      {#each filter_options.source_providers as provider (provider.value)}
                        <label class="flex items-center gap-2 ui-title">
                          <input
                            checked={checked(
                              filters?.source_provider ?? [],
                              provider.value,
                            )}
                            class="h-4 w-4 rounded border-(--panel-border)"
                            name="source_provider"
                            type="checkbox"
                            value={provider.value}
                          />
                          {provider.label}
                        </label>
                      {/each}
                    </div>
                  </fieldset>
                {/if}

                {#if filter_options.source_sync_statuses.length > 0}
                  <fieldset class="grid gap-2 text-sm">
                    <legend class="font-medium ui-title">Source sync</legend>
                    <div class="flex flex-wrap gap-3">
                      {#each filter_options.source_sync_statuses as status (status.value)}
                        <label class="flex items-center gap-2 ui-title">
                          <input
                            checked={checked(
                              filters?.source_sync_status ?? [],
                              status.value,
                            )}
                            class="h-4 w-4 rounded border-(--panel-border)"
                            name="source_sync_status"
                            type="checkbox"
                            value={status.value}
                          />
                          {status.label}
                        </label>
                      {/each}
                    </div>
                  </fieldset>
                {/if}
              </div>

              <div class="grid gap-3 border-t pt-4 theme-border">
                <button
                  aria-controls="admin-package-advanced-filters"
                  aria-expanded={advancedFiltersOpen}
                  class="ui-button-secondary flex w-full cursor-pointer items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-sm font-semibold transition"
                  type="button"
                  onclick={() => {
                    advancedFiltersOpen = !advancedFiltersOpen;
                  }}
                >
                  <span class="flex items-center gap-2">
                    <span>Advanced filters</span>
                    {#if activeAdvancedFilterCount() > 0}
                      <span
                        class="grid h-5 min-w-5 place-items-center rounded-full px-1 text-[0.65rem] font-bold"
                        style="background-color: var(--accent); color: var(--button-primary-text);"
                      >
                        {activeAdvancedFilterCount()}
                      </span>
                    {/if}
                  </span>
                  <ChevronDown
                    aria-hidden="true"
                    class={advancedFiltersOpen ? "rotate-180" : ""}
                    size={16}
                  />
                </button>

                {#if advancedFiltersOpen}
                  <div
                    id="admin-package-advanced-filters"
                    class="grid gap-4 rounded-xl border p-4 theme-border"
                    style="background-color: var(--surface-muted);"
                  >
                    <div class="grid gap-4 lg:grid-cols-3">
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Package name</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="package_name"
                          type="text"
                          value={filters?.package_name ?? ""}
                        />
                      </label>
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Source ID</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="source_id"
                          type="text"
                          value={filters?.source_id ?? ""}
                        />
                      </label>
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Source URL</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="source_url"
                          type="text"
                          value={filters?.source_url ?? ""}
                        />
                      </label>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-4">
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Created from</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="created_from"
                          type="date"
                          value={filters?.created_from ?? ""}
                        />
                      </label>
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Created to</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="created_to"
                          type="date"
                          value={filters?.created_to ?? ""}
                        />
                      </label>
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Updated from</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="updated_from"
                          type="date"
                          value={filters?.updated_from ?? ""}
                        />
                      </label>
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Updated to</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="updated_to"
                          type="date"
                          value={filters?.updated_to ?? ""}
                        />
                      </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">
                          Min downloads
                        </span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          min="0"
                          name="min_downloads"
                          type="number"
                          value={filters?.min_downloads ?? ""}
                        />
                      </label>
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">
                          Max downloads
                        </span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          min="0"
                          name="max_downloads"
                          type="number"
                          value={filters?.max_downloads ?? ""}
                        />
                      </label>
                    </div>
                  </div>
                {/if}
              </div>

              <div class="flex flex-wrap gap-3">
                <Button
                  className="gap-2 rounded-full px-5"
                  type="submit"
                  variant="primary"
                >
                  <Search aria-hidden="true" size={16} />
                  Apply filters
                </Button>
                <Button
                  className="gap-2 rounded-full px-5"
                  type="button"
                  variant="soft"
                  onclick={clearFilters}
                >
                  <FilterX aria-hidden="true" size={16} />
                  Clear
                </Button>
              </div>
            </form>
          {/if}
        </div>
      </Card>

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
        onPerPageChange={handlePerPageChange}
      />

      {#if packages.length === 0}
        <p class="text-sm ui-muted">No packages match current filters.</p>
      {:else if listViewMode === "grid"}
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {#each packages as item (item.name + "-" + (item.organization?.id ?? ""))}
            <Card as="article" className="rounded-[1.25rem] p-5">
              <div class="flex items-start justify-between gap-3">
                <p class="ui-eyebrow">
                  {item.organization?.slug ?? "unknown"}
                </p>
                <div class="flex flex-wrap justify-end gap-2">
                  <Badge
                    tone={item.visibility === "private" ? "danger" : "accent"}
                  >
                    {item.visibility}
                  </Badge>
                  <Badge tone={item.active ? "accent" : "muted"}>
                    {item.active ? "Listed" : "Hidden"}
                  </Badge>
                </div>
              </div>
              <p class="mt-3 truncate text-lg font-semibold ui-title">
                {item.name}
              </p>
              <p class="mt-2 min-h-[2.5rem] text-sm ui-muted line-clamp-2">
                {item.description || "No description provided."}
              </p>
              <div class="mt-4 grid gap-2 text-sm ui-muted">
                <p>Downloads: {item.download_count}</p>
                <p>
                  Created: {item.created_at ? formatDate(item.created_at) : "-"}
                </p>
              </div>
              {#if item.source}
                <div
                  class="mt-4 rounded-xl border p-3 theme-border theme-surface-muted"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="text-sm font-medium ui-title">Source</p>
                      <p class="mt-1 truncate text-sm ui-muted">
                        {item.source.name}
                      </p>
                    </div>
                    <Badge tone={syncTone(item.source.last_sync_status)}>
                      {item.source.last_sync_status}
                    </Badge>
                  </div>
                </div>
              {/if}
            </Card>
          {/each}
        </section>
      {:else}
        <TableFrame ariaLabel="Admin packages" tableClassName="min-w-[1080px]">
          <thead>
            <tr>
              <th class="px-4 py-3">
                <TableSortButton
                  label="Package"
                  field="name"
                  {currentSort}
                  {currentDirection}
                  onSort={sortPackages}
                />
              </th>
              <th class="px-4 py-3">
                <TableSortButton
                  label="Organization"
                  field="organization"
                  {currentSort}
                  {currentDirection}
                  onSort={sortPackages}
                />
              </th>
              <th class="px-4 py-3">
                <TableSortButton
                  label="Visibility"
                  field="visibility"
                  {currentSort}
                  {currentDirection}
                  onSort={sortPackages}
                />
              </th>
              <th class="px-4 py-3">
                <TableSortButton
                  label="Listed"
                  field="active"
                  {currentSort}
                  {currentDirection}
                  onSort={sortPackages}
                />
              </th>
              <th class="px-4 py-3 text-right">
                <TableSortButton
                  label="Downloads"
                  field="download_count"
                  {currentSort}
                  {currentDirection}
                  onSort={sortPackages}
                  className="ml-auto"
                />
              </th>
              <th class="px-4 py-3">
                <TableSortButton
                  label="Source"
                  field="source"
                  {currentSort}
                  {currentDirection}
                  onSort={sortPackages}
                />
              </th>
              <th class="px-4 py-3">
                <TableSortButton
                  label="Updated"
                  field="updated_at"
                  {currentSort}
                  {currentDirection}
                  onSort={sortPackages}
                />
              </th>
            </tr>
          </thead>
          <tbody>
            {#each packages as item (item.name + "-" + (item.organization?.id ?? ""))}
              <tr class="align-top">
                <td class="px-4 py-3">
                  <p class="font-semibold ui-title">
                    {item.name}
                  </p>
                  <p class="mt-1 line-clamp-2 max-w-[24rem] text-xs ui-muted">
                    {item.description || "No description provided."}
                  </p>
                </td>
                <td class="px-4 py-3">
                  <p class="font-medium ui-title">
                    {item.organization?.name ?? "unknown"}
                  </p>
                  <p class="text-xs ui-muted">
                    {item.organization?.slug ?? "-"}
                  </p>
                </td>
                <td class="px-4 py-3">
                  <Badge
                    tone={item.visibility === "private" ? "danger" : "accent"}
                  >
                    {item.visibility}
                  </Badge>
                </td>
                <td class="px-4 py-3">
                  <Badge tone={item.active ? "accent" : "muted"}>
                    {item.active ? "Listed" : "Hidden"}
                  </Badge>
                </td>
                <td class="px-4 py-3 text-right tabular-nums">
                  {item.download_count}
                </td>
                <td class="px-4 py-3">
                  {#if item.source}
                    <div class="grid gap-2">
                      <p class="font-medium ui-title">
                        {item.source.name}
                      </p>
                      <div class="flex flex-wrap gap-2">
                        <Badge tone="neutral"
                          >{item.source.provider_label}</Badge
                        >
                        <Badge tone={syncTone(item.source.last_sync_status)}>
                          {item.source.last_sync_status}
                        </Badge>
                      </div>
                    </div>
                  {:else}
                    <span class="text-sm ui-muted">manual</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-sm ui-muted">
                  {item.updated_at ? formatDate(item.updated_at) : "-"}
                </td>
              </tr>
            {/each}
          </tbody>
        </TableFrame>
      {/if}

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
        onPerPageChange={handlePerPageChange}
      />
    </div>
  </div>
</AdminShell>
