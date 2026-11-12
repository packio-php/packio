<script lang="ts">
  import { onMount } from "svelte";
  import { router } from "@inertiajs/svelte";
  import {
    ChevronDown,
    ExternalLink,
    FilterX,
    Info,
    ListFilter,
    RefreshCw,
    Search,
    Trash2,
  } from "@lucide/svelte";
  import Link from "@/Components/core/Link.svelte";
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
    type ListViewMode,
    readStoredListViewMode,
    storeListViewMode,
  } from "@/lib/listViewMode";
  import { cleanQuery, queryString, type QueryValue } from "@/lib/queryFilters";
  import type { AuthPayload } from "@/types/auth";
  import SourceRecoveryModal, {
    deleteHealthTone,
    deleteJobsUrl,
    deleteStateLabel,
    statusTone,
    type AdminOrganization,
    type AdminSourcePayload,
    type RecoveryModalAction,
  } from "./Partials/SourceRecoveryModal.svelte";

  const viewStorageKey = "packio-admin-sources-view-mode";
  const visibilityOptions = ["public", "private"] as const;
  const statusOptions = ["active", "inactive", "archived", "deleting"] as const;
  const deleteHealthOptions = [
    { value: "stale_without_job", label: "missing job" },
    { value: "failed", label: "failed" },
    { value: "in_progress", label: "in progress" },
    { value: "completed_but_source_exists", label: "completed but exists" },
    { value: "unknown", label: "unknown" },
  ] as const;

  interface Props {
    auth?: AuthPayload;
    sources?: AdminSourcePayload[];
    organizations?: AdminOrganization[];
    source_stats?: AdminSourceStats;
    filter_options?: AdminSourceFilterOptions;
    filters?: AdminSourceFilters;
    pagination?: PaginationMeta;
    initial_list_view_mode?: ListViewMode;
  }

  let {
    auth = {},
    sources = [],
    organizations = [],
    source_stats = {
      active: 0,
      archived: 0,
      deleting: 0,
      deleting_failed: 0,
      deleting_in_progress: 0,
      deleting_missing_jobs: 0,
      inactive: 0,
      private: 0,
      public: 0,
      total: 0,
    },
    filter_options = {
      providers: [],
      sync_statuses: [],
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
  let recoveryModalSource: AdminSourcePayload | null = $state(null);
  let recoveryModalAction: RecoveryModalAction | null = $state(null);
  let currentSort = $derived(filters?.sort ?? "created_at");
  let currentDirection = $derived(filters?.direction ?? "desc");

  $effect(() => {
    if (!listViewModeInitialized) {
      listViewMode = initial_list_view_mode;
    }
  });

  interface FilterOption {
    value: string;
    label: string;
  }

  interface AdminSourceStats {
    active: number;
    archived: number;
    deleting: number;
    deleting_failed: number;
    deleting_in_progress: number;
    deleting_missing_jobs: number;
    inactive: number;
    private: number;
    public: number;
    total: number;
  }

  interface AdminSourceFilterOptions {
    providers: FilterOption[];
    sync_statuses: FilterOption[];
  }

  interface AdminSourceFilters {
    organization_id: string;
    search: string;
    provider: string[];
    visibility: string[];
    status: string[];
    sync_status: string[];
    delete_health: string[];
    source_id: string;
    url: string;
    owner: string;
    repository: string;
    default_ref: string;
    created_from: string;
    created_to: string;
    archived_from: string;
    archived_to: string;
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

  function defaultFilters(): AdminSourceFilters {
    return {
      organization_id: "",
      search: "",
      provider: [],
      visibility: [],
      status: [],
      sync_status: [],
      delete_health: [],
      source_id: "",
      url: "",
      owner: "",
      repository: "",
      default_ref: "",
      created_from: "",
      created_to: "",
      archived_from: "",
      archived_to: "",
      sort: "created_at",
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

    if ((filters?.source_id ?? "") !== "") count += 1;
    if ((filters?.url ?? "") !== "") count += 1;
    if ((filters?.owner ?? "") !== "") count += 1;
    if ((filters?.repository ?? "") !== "") count += 1;
    if ((filters?.default_ref ?? "") !== "") count += 1;
    if (
      (filters?.created_from ?? "") !== "" ||
      (filters?.created_to ?? "") !== ""
    ) {
      count += 1;
    }
    if (
      (filters?.archived_from ?? "") !== "" ||
      (filters?.archived_to ?? "") !== ""
    ) {
      count += 1;
    }
    if ((filters?.delete_health ?? []).length > 0) count += 1;

    return count;
  }

  function activeFilterCount(): number {
    let count = activeAdvancedFilterCount();

    if ((filters?.organization_id ?? "") !== "") count += 1;
    if ((filters?.search ?? "") !== "") count += 1;
    if ((filters?.provider ?? []).length > 0) count += 1;
    if ((filters?.visibility ?? []).length > 0) count += 1;
    if ((filters?.status ?? []).length > 0) count += 1;
    if ((filters?.sync_status ?? []).length > 0) count += 1;

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
      Endpoints.admin.sources(),
      cleanQuery({
        organization_id: String(formData.get("organization_id") ?? ""),
        search: String(formData.get("search") ?? ""),
        provider: formData.getAll("provider").map(String),
        visibility: formData.getAll("visibility").map(String),
        status: formData.getAll("status").map(String),
        sync_status: formData.getAll("sync_status").map(String),
        delete_health: formData.getAll("delete_health").map(String),
        source_id: String(formData.get("source_id") ?? ""),
        url: String(formData.get("url") ?? ""),
        owner: String(formData.get("owner") ?? ""),
        repository: String(formData.get("repository") ?? ""),
        default_ref: String(formData.get("default_ref") ?? ""),
        created_from: String(formData.get("created_from") ?? ""),
        created_to: String(formData.get("created_to") ?? ""),
        archived_from: String(formData.get("archived_from") ?? ""),
        archived_to: String(formData.get("archived_to") ?? ""),
        sort: filters?.sort ?? "created_at",
        direction: filters?.direction ?? "desc",
        per_page: filters?.per_page ?? 20,
        page: 1,
      }),
      { preserveScroll: true },
    );
  }

  function clearFilters(): void {
    router.get(
      Endpoints.admin.sources(),
      cleanQuery({
        sort: "created_at",
        direction: "desc",
        per_page: filters?.per_page ?? 20,
      }),
      { preserveScroll: true },
    );
  }

  function sortSources(field: string): void {
    const currentSort = filters?.sort ?? "created_at";
    const currentDirection = filters?.direction ?? "desc";
    const nextDirection =
      currentSort === field && currentDirection === "asc" ? "desc" : "asc";

    router.get(
      Endpoints.admin.sources(),
      currentQuery({
        sort: field,
        direction: nextDirection,
        page: 1,
      }),
      { preserveScroll: true },
    );
  }

  function handlePageChange(page: number): void {
    router.get(Endpoints.admin.sources(), currentQuery({ page }), {
      preserveScroll: true,
    });
  }

  function handlePerPageChange(perPage: number): void {
    router.get(
      Endpoints.admin.sources(),
      currentQuery({ page: 1, per_page: perPage }),
      { preserveScroll: true },
    );
  }

  function syncTone(status: string): string {
    if (status === "failed") return "danger";
    return status === "success" ? "accent" : "gold";
  }

  function syncBadgeClass(status: string): string {
    const toneClass =
      syncTone(status) === "danger"
        ? "ui-badge-danger"
        : syncTone(status) === "accent"
          ? "ui-badge-accent"
          : "ui-badge-gold";

    return `inline-flex w-fit items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium leading-4 ${toneClass}`;
  }

  function openRecoveryModal(
    item: AdminSourcePayload,
    action: RecoveryModalAction,
  ): void {
    recoveryModalSource = item;
    recoveryModalAction = action;
  }

  function closeRecoveryModal(): void {
    recoveryModalSource = null;
    recoveryModalAction = null;
  }

  function adminSourcesReturnUrl(): string {
    return `${Endpoints.admin.sources()}${queryString(currentQuery())}`;
  }
</script>

<svelte:head>
  <title>Admin Sources</title>
</svelte:head>

<AdminShell title="Admin Sources" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    <div class="grid min-w-0 gap-6">
      <SectionHeading
        eyebrow="Administration"
        title="Sources"
        description="Global source inventory across every organization."
      />

      <div class="grid gap-3 md:grid-cols-5">
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Sources</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {source_stats.total}
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Active</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {source_stats.active} / {source_stats.inactive} inactive
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Archived</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {source_stats.archived} / {source_stats.deleting} deleting
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">
            Delete recovery
          </p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {source_stats.deleting_missing_jobs} / {source_stats.deleting_failed}
          </p>
          <p class="mt-1 text-xs ui-muted">
            missing / failed, {source_stats.deleting_in_progress} active
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Visibility</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {source_stats.public} / {source_stats.private}
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
                  aria-controls="admin-source-filters"
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
              id="admin-source-filters"
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
              <div class="grid gap-4 lg:grid-cols-3">
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

                {#if filter_options.providers.length > 0}
                  <fieldset class="grid gap-2 text-sm">
                    <legend class="font-medium ui-title">Provider</legend>
                    <div class="flex flex-wrap gap-3">
                      {#each filter_options.providers as provider (provider.value)}
                        <label class="flex items-center gap-2 ui-title">
                          <input
                            checked={checked(
                              filters?.provider ?? [],
                              provider.value,
                            )}
                            class="h-4 w-4 rounded border-(--panel-border)"
                            name="provider"
                            type="checkbox"
                            value={provider.value}
                          />
                          {provider.label}
                        </label>
                      {/each}
                    </div>
                  </fieldset>
                {/if}
              </div>

              <div class="grid gap-4 lg:grid-cols-4">
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

                <fieldset class="grid gap-2 text-sm">
                  <legend class="font-medium ui-title">Status</legend>
                  <div class="flex flex-wrap gap-3">
                    {#each statusOptions as status (status)}
                      <label class="flex items-center gap-2 ui-title">
                        <input
                          checked={checked(filters?.status ?? [], status)}
                          class="h-4 w-4 rounded border-(--panel-border)"
                          name="status"
                          type="checkbox"
                          value={status}
                        />
                        {status}
                      </label>
                    {/each}
                  </div>
                </fieldset>

                {#if filter_options.sync_statuses.length > 0}
                  <fieldset class="grid gap-2 text-sm">
                    <legend class="font-medium ui-title">Sync</legend>
                    <div class="flex flex-wrap gap-3">
                      {#each filter_options.sync_statuses as status (status.value)}
                        <label class="flex items-center gap-2 ui-title">
                          <input
                            checked={checked(
                              filters?.sync_status ?? [],
                              status.value,
                            )}
                            class="h-4 w-4 rounded border-(--panel-border)"
                            name="sync_status"
                            type="checkbox"
                            value={status.value}
                          />
                          {status.label}
                        </label>
                      {/each}
                    </div>
                  </fieldset>
                {/if}

                <fieldset class="grid gap-2 text-sm">
                  <legend class="font-medium ui-title">Delete health</legend>
                  <div class="flex flex-wrap gap-3">
                    {#each deleteHealthOptions as option (option.value)}
                      <label class="flex items-center gap-2 ui-title">
                        <input
                          checked={checked(
                            filters?.delete_health ?? [],
                            option.value,
                          )}
                          class="h-4 w-4 rounded border-(--panel-border)"
                          name="delete_health"
                          type="checkbox"
                          value={option.value}
                        />
                        {option.label}
                      </label>
                    {/each}
                  </div>
                </fieldset>
              </div>

              <div class="grid gap-3 border-t pt-4 theme-border">
                <button
                  aria-controls="admin-source-advanced-filters"
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
                    id="admin-source-advanced-filters"
                    class="grid gap-4 rounded-xl border p-4 theme-border"
                    style="background-color: var(--surface-muted);"
                  >
                    <div class="grid gap-4 lg:grid-cols-3">
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
                        <span class="font-medium ui-title">URL</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="url"
                          type="text"
                          value={filters?.url ?? ""}
                        />
                      </label>
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Default ref</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="default_ref"
                          type="text"
                          value={filters?.default_ref ?? ""}
                        />
                      </label>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-4">
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Owner</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="owner"
                          type="text"
                          value={filters?.owner ?? ""}
                        />
                      </label>
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Repository</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="repository"
                          type="text"
                          value={filters?.repository ?? ""}
                        />
                      </label>
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
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">
                          Archived from
                        </span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="archived_from"
                          type="date"
                          value={filters?.archived_from ?? ""}
                        />
                      </label>
                      <label class="grid gap-2 text-sm">
                        <span class="font-medium ui-title">Archived to</span>
                        <input
                          class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                          name="archived_to"
                          type="date"
                          value={filters?.archived_to ?? ""}
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

      {#if sources.length === 0}
        <p class="text-sm ui-muted">No sources match current filters.</p>
      {:else if listViewMode === "grid"}
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {#each sources as item (item.id)}
            <Card as="article" className="rounded-[1.25rem] p-5">
              <div class="flex items-start justify-between gap-3">
                <p class="ui-eyebrow">
                  {item.organization?.slug ?? "unknown"}
                </p>
                <div class="flex flex-wrap justify-end gap-2">
                  <Badge tone={statusTone(item.logical_status)}>
                    {item.logical_status}
                  </Badge>
                  <Badge
                    tone={item.visibility === "private" ? "danger" : "accent"}
                  >
                    {item.visibility}
                  </Badge>
                </div>
              </div>
              <p class="mt-3 truncate text-lg font-semibold ui-title">
                {item.name}
              </p>
              <p
                class="mt-2 min-h-[2.5rem] break-all text-sm ui-muted line-clamp-2"
              >
                {item.url}
              </p>
              <div class="mt-4 flex flex-wrap gap-2">
                <Badge tone="neutral">{item.provider_label}</Badge>
                <Badge tone={syncTone(item.last_sync_status)}>
                  {item.last_sync_status}
                </Badge>
                {#if item.recovery}
                  <Badge tone={deleteHealthTone(item.recovery.health)}>
                    {deleteStateLabel(item)}
                  </Badge>
                {/if}
              </div>
              <div class="mt-4 grid gap-2 text-sm ui-muted">
                <p>Default ref: {item.default_ref || "-"}</p>
                <p>
                  Created: {item.created_at ? formatDate(item.created_at) : "-"}
                </p>
              </div>
              {#if item.logical_status === "deleting"}
                <div class="mt-4 flex flex-wrap gap-2">
                  {#if item.recovery?.can_requeue}
                    <Button
                      className="gap-2 rounded-full"
                      type="button"
                      variant="primary"
                      onclick={() => openRecoveryModal(item, "requeue")}
                    >
                      <RefreshCw aria-hidden="true" size={15} />
                      Requeue delete
                    </Button>
                  {/if}
                  {#if item.recovery?.related_jobs_count}
                    <Link
                      class="ui-button-secondary inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition"
                      href={deleteJobsUrl(item)}
                    >
                      <ExternalLink aria-hidden="true" size={15} />
                      View jobs
                    </Link>
                  {/if}
                  <Button
                    className="gap-2 rounded-full"
                    type="button"
                    variant="soft"
                    onclick={() => openRecoveryModal(item, "details")}
                  >
                    <Info aria-hidden="true" size={15} />
                    Details
                  </Button>
                </div>
              {:else}
                <div class="mt-4 flex flex-wrap gap-2">
                  <Button
                    className="gap-2 rounded-full"
                    type="button"
                    variant="dangerSoft"
                    onclick={() => openRecoveryModal(item, "delete")}
                  >
                    <Trash2 aria-hidden="true" size={15} />
                    Delete source
                  </Button>
                </div>
              {/if}
            </Card>
          {/each}
        </section>
      {:else}
        <TableFrame
          ariaLabel="Admin sources"
          tableClassName="min-w-[1320px] xl:min-w-0 xl:table-fixed"
        >
          <thead>
            <tr>
              <th class="w-[24%] px-4 py-3">
                <TableSortButton
                  label="Source"
                  field="name"
                  {currentSort}
                  {currentDirection}
                  onSort={sortSources}
                />
              </th>
              <th class="w-[13%] px-4 py-3">
                <TableSortButton
                  label="Organization"
                  field="organization"
                  {currentSort}
                  {currentDirection}
                  onSort={sortSources}
                />
              </th>
              <th class="w-[8%] px-4 py-3">
                <TableSortButton
                  label="Provider"
                  field="provider"
                  {currentSort}
                  {currentDirection}
                  onSort={sortSources}
                />
              </th>
              <th class="w-[7%] px-4 py-3">
                <TableSortButton
                  label="Visibility"
                  field="visibility"
                  {currentSort}
                  {currentDirection}
                  onSort={sortSources}
                />
              </th>
              <th class="w-[8%] px-4 py-3">
                <TableSortButton
                  label="Status"
                  field="logical_status"
                  {currentSort}
                  {currentDirection}
                  onSort={sortSources}
                />
              </th>
              <th class="w-[13%] px-4 py-3 text-left">Delete state</th>
              <th class="w-[12%] px-4 py-3">
                <TableSortButton
                  label="Sync"
                  field="last_sync_status"
                  {currentSort}
                  {currentDirection}
                  onSort={sortSources}
                />
              </th>
              <th class="w-[9%] px-4 py-3">
                <TableSortButton
                  label="Created"
                  field="created_at"
                  {currentSort}
                  {currentDirection}
                  onSort={sortSources}
                />
              </th>
              <th class="w-[6%] px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            {#each sources as item (item.id)}
              <tr class="align-top">
                <td class="min-w-0 px-4 py-3">
                  <p class="break-words font-semibold ui-title">
                    {item.name}
                  </p>
                  <p class="mt-1 break-all text-xs ui-muted line-clamp-2">
                    {item.url}
                  </p>
                </td>
                <td class="min-w-0 px-4 py-3">
                  <p class="break-words font-medium ui-title">
                    {item.organization?.name ?? "unknown"}
                  </p>
                  <p class="text-xs ui-muted">
                    {item.organization?.slug ?? "-"}
                  </p>
                </td>
                <td class="px-4 py-3">
                  <Badge tone="neutral">{item.provider_label}</Badge>
                </td>
                <td class="px-4 py-3">
                  <Badge
                    tone={item.visibility === "private" ? "danger" : "accent"}
                  >
                    {item.visibility}
                  </Badge>
                </td>
                <td class="px-4 py-3">
                  <Badge tone={statusTone(item.logical_status)}>
                    {item.logical_status}
                  </Badge>
                </td>
                <td class="px-4 py-3">
                  {#if item.recovery}
                    <div class="grid gap-1">
                      <Badge
                        className="max-w-full whitespace-normal break-words"
                        tone={deleteHealthTone(item.recovery.health)}
                      >
                        {deleteStateLabel(item)}
                      </Badge>
                      <p class="text-xs ui-muted">
                        {item.recovery.linked_packages_count} packages,
                        {item.recovery.related_jobs_count} jobs
                      </p>
                    </div>
                  {:else}
                    <span class="text-sm ui-muted">-</span>
                  {/if}
                </td>
                <td class="px-4 py-3">
                  <div class="grid gap-1">
                    <span class={syncBadgeClass(item.last_sync_status)}>
                      {item.last_sync_status}
                    </span>
                    <p class="text-xs ui-muted">
                      {item.last_successful_sync_at
                        ? formatDate(item.last_successful_sync_at)
                        : "not synced"}
                    </p>
                  </div>
                </td>
                <td class="px-4 py-3 text-sm ui-muted">
                  {item.created_at ? formatDate(item.created_at) : "-"}
                </td>
                <td class="min-w-0 px-4 py-3">
                  {#if item.logical_status === "deleting"}
                    <div class="flex flex-wrap gap-2">
                      {#if item.recovery?.can_requeue}
                        <Button
                          aria-label="Requeue delete"
                          className="gap-2 rounded-full xl:h-9 xl:w-9 xl:justify-center xl:gap-0 xl:px-0"
                          title="Requeue delete"
                          type="button"
                          variant="primary"
                          onclick={() => openRecoveryModal(item, "requeue")}
                        >
                          <RefreshCw aria-hidden="true" size={15} />
                          <span class="inline xl:hidden">Requeue</span>
                        </Button>
                      {/if}
                      {#if item.recovery?.related_jobs_count}
                        <Link
                          aria-label="Jobs"
                          class="ui-button-secondary inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition xl:h-9 xl:w-9 xl:justify-center xl:gap-0 xl:px-0 xl:py-0"
                          href={deleteJobsUrl(item)}
                          title="Jobs"
                        >
                          <ExternalLink aria-hidden="true" size={15} />
                          <span class="inline xl:hidden">Jobs</span>
                        </Link>
                      {/if}
                      <Button
                        aria-label="Recovery details"
                        className="gap-2 rounded-full xl:h-9 xl:w-9 xl:justify-center xl:gap-0 xl:px-0"
                        title="Recovery details"
                        type="button"
                        variant="soft"
                        onclick={() => openRecoveryModal(item, "details")}
                      >
                        <Info aria-hidden="true" size={15} />
                        <span class="inline xl:hidden">Details</span>
                      </Button>
                    </div>
                  {:else}
                    <Button
                      aria-label="Delete source"
                      className="gap-2 rounded-full xl:h-9 xl:w-9 xl:justify-center xl:gap-0 xl:px-0"
                      title="Delete source"
                      type="button"
                      variant="dangerSoft"
                      onclick={() => openRecoveryModal(item, "delete")}
                    >
                      <Trash2 aria-hidden="true" size={15} />
                      <span class="inline xl:hidden">Delete</span>
                    </Button>
                  {/if}
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

{#if recoveryModalSource && recoveryModalAction}
  {#key `${recoveryModalSource.id}:${recoveryModalAction}`}
    <SourceRecoveryModal
      source={recoveryModalSource}
      action={recoveryModalAction}
      returnUrl={adminSourcesReturnUrl()}
      onClose={closeRecoveryModal}
      onAction={(action) => (recoveryModalAction = action)}
    />
  {/key}
{/if}
