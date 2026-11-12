<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import { ChevronDown, FilterX, ListFilter, Search } from "@lucide/svelte";
  import Link from "@/Components/core/Link.svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import TableSortButton from "@/Components/ui/TableSortButton.svelte";
  import { Endpoints } from "@/lib/api";
  import { formatDate } from "@/lib/date";
  import { jobStatusTone, jobTypeLabel } from "@/lib/job";
  import { cleanQuery, queryString, type QueryValue } from "@/lib/queryFilters";
  import type { AuthPayload } from "@/types/auth";

  interface Props {
    auth?: AuthPayload;
    jobs?: AdminJobPayload[];
    organizations?: AdminJobOrganization[];
    job_types?: string[];
    status_counts?: JobStatusCounts;
    queue_progress?: QueueProgress;
    auth_mail_progress?: QueueProgress;
    filters?: AdminJobFilters;
    pagination?: PaginationMeta;
  }

  let {
    auth = {},
    jobs = [],
    organizations = [],
    job_types = [],
    status_counts = {
      pending: 0,
      running: 0,
      completed: 0,
      failed: 0,
      canceled: 0,
      held: 0,
      audited: 0,
    },
    queue_progress,
    auth_mail_progress,
    filters = defaultFilters(),
    pagination = {
      items: [],
      total: 0,
      current_page: 1,
      per_page: 20,
      last_page: 1,
      has_more_pages: false,
      has_previous_pages: false,
      from: 0,
      to: 0,
    },
  }: Props = $props();
  let filtersOpen = $state(false);
  let advancedFiltersOpen = $state(false);
  let currentSort = $derived(filters?.sort ?? "createdAt");
  let currentDirection = $derived(filters?.direction ?? "desc");

  const statuses = [
    "pending",
    "running",
    "completed",
    "failed",
    "canceled",
    "held",
    "audited",
  ] as const;
  const quickDateRanges = [
    { label: "Today", range: "today" },
    { label: "Yesterday", range: "yesterday" },
    { label: "7 days", range: "week" },
    { label: "30 days", range: "month" },
  ] as const;

  type QuickDateRange = (typeof quickDateRanges)[number]["range"];

  interface AdminJobOrganization {
    id: string;
    slug: string;
    name: string;
    status: string;
  }

  interface AdminJobPayload {
    id: string;
    organizationId: string;
    organization: AdminJobOrganization | null;
    type: string;
    payload: Record<string, unknown>;
    deletionRunId: string | null;
    idempotencyKey: string | null;
    status: string;
    priority: number;
    scheduledAt: string;
    startedAt: string | null;
    completedAt: string | null;
    attemptCount: number;
    maxAttempts: number;
    errorMessage: string | null;
    workerId: string | null;
    createdAt: string;
    durationSeconds: number | null;
    warningCount: number;
  }

  interface JobStatusCounts {
    pending: number;
    running: number;
    completed: number;
    failed: number;
    canceled: number;
    held: number;
    audited: number;
  }

  interface QueueProgress {
    pending: number;
    running: number;
    failed: number;
    retrying: number;
    stuck: number;
    oldest_ready_age_seconds: number | null;
  }

  interface AdminJobFilters {
    organization_id: string;
    status: string[];
    type: string[];
    search: string;
    job_id: string;
    idempotency_key: string;
    deletion_run_id: string;
    source_id: string;
    worker_id: string;
    created_from: string;
    created_to: string;
    scheduled_from: string;
    scheduled_to: string;
    has_warnings: boolean;
    has_error: boolean;
    sort: string;
    direction: "asc" | "desc";
    page: number;
    per_page: number;
  }

  interface PaginationMeta {
    items: AdminJobPayload[];
    total: number;
    current_page: number;
    per_page: number;
    last_page: number;
    has_more_pages: boolean;
    has_previous_pages: boolean;
    from: number;
    to: number;
  }

  function defaultFilters(): AdminJobFilters {
    return {
      organization_id: "",
      status: [],
      type: [],
      search: "",
      job_id: "",
      idempotency_key: "",
      deletion_run_id: "",
      source_id: "",
      worker_id: "",
      created_from: "",
      created_to: "",
      scheduled_from: "",
      scheduled_to: "",
      has_warnings: false,
      has_error: false,
      sort: "createdAt",
      direction: "desc",
      page: 1,
      per_page: 20,
    };
  }

  function filterString(key: keyof AdminJobFilters): string {
    const value = filters?.[key];
    if (Array.isArray(value)) {
      return value[0] ?? "";
    }

    return typeof value === "string" ? value : "";
  }

  function filterBool(key: "has_warnings" | "has_error"): boolean {
    return filters?.[key] === true;
  }

  function statusChecked(status: string): boolean {
    return (filters?.status ?? []).includes(status);
  }

  function activeAdvancedFilterCount(): number {
    let count = 0;

    if ((filters?.deletion_run_id ?? "") !== "") count += 1;
    if ((filters?.source_id ?? "") !== "") count += 1;
    if ((filters?.worker_id ?? "") !== "") count += 1;

    return count;
  }

  function activeFilterCount(): number {
    let count = 0;

    if ((filters?.search ?? "") !== "") count += 1;
    if ((filters?.status ?? []).length > 0) count += 1;
    if ((filters?.type ?? []).length > 0) count += 1;
    if ((filters?.organization_id ?? "") !== "") count += 1;
    if ((filters?.job_id ?? "") !== "") count += 1;
    if ((filters?.idempotency_key ?? "") !== "") count += 1;
    if (
      (filters?.created_from ?? "") !== "" ||
      (filters?.created_to ?? "") !== ""
    ) {
      count += 1;
    }
    if (
      (filters?.scheduled_from ?? "") !== "" ||
      (filters?.scheduled_to ?? "") !== ""
    ) {
      count += 1;
    }
    if (filters?.has_warnings === true) count += 1;
    if (filters?.has_error === true) count += 1;
    count += activeAdvancedFilterCount();

    return count;
  }

  function currentQuery(
    overrides: Record<string, QueryValue> = {},
  ): ReturnType<typeof cleanQuery> {
    return cleanQuery({
      ...filters,
      ...overrides,
    });
  }

  function jobsReturnUrl(): string {
    return `${Endpoints.admin.jobs()}${queryString(currentQuery())}`;
  }

  function jobDetailsUrl(jobId: string): string {
    return `${Endpoints.admin.job(jobId)}?return_to=${encodeURIComponent(jobsReturnUrl())}`;
  }

  function applyFilters(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    const formData = new FormData(event.currentTarget);

    router.get(
      Endpoints.admin.jobs(),
      cleanQuery({
        search: String(formData.get("search") ?? ""),
        status: formData.getAll("status").map(String),
        type: String(formData.get("type") ?? ""),
        organization_id: String(formData.get("organization_id") ?? ""),
        deletion_run_id: String(formData.get("deletion_run_id") ?? ""),
        source_id: String(formData.get("source_id") ?? ""),
        worker_id: String(formData.get("worker_id") ?? ""),
        job_id: String(formData.get("job_id") ?? ""),
        idempotency_key: String(formData.get("idempotency_key") ?? ""),
        created_from: String(formData.get("created_from") ?? ""),
        created_to: String(formData.get("created_to") ?? ""),
        scheduled_from: String(formData.get("scheduled_from") ?? ""),
        scheduled_to: String(formData.get("scheduled_to") ?? ""),
        has_warnings: formData.has("has_warnings"),
        has_error: formData.has("has_error"),
        sort: filters?.sort ?? "createdAt",
        direction: filters?.direction ?? "desc",
        per_page: filters?.per_page ?? 20,
        page: 1,
      }),
      { preserveScroll: true },
    );
  }

  function clearFilters(): void {
    router.get(
      Endpoints.admin.jobs(),
      {
        sort: "createdAt",
        direction: "desc",
        per_page: filters?.per_page ?? 20,
      },
      { preserveScroll: true },
    );
  }

  function sortJobs(field: string): void {
    const currentSort = filters?.sort ?? "createdAt";
    const currentDirection = filters?.direction ?? "desc";
    const nextDirection =
      currentSort === field && currentDirection === "asc" ? "desc" : "asc";

    router.get(
      Endpoints.admin.jobs(),
      currentQuery({
        sort: field,
        direction: nextDirection,
        page: 1,
      }),
      { preserveScroll: true },
    );
  }

  function toDateInputValue(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
  }

  function daysAgo(days: number): Date {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate() - days);
  }

  function quickDateBounds(range: QuickDateRange): {
    createdFrom: string;
    createdTo: string;
  } {
    const today = toDateInputValue(new Date());

    if (range === "today") {
      return {
        createdFrom: today,
        createdTo: today,
      };
    }

    if (range === "yesterday") {
      const yesterday = toDateInputValue(daysAgo(1));

      return {
        createdFrom: yesterday,
        createdTo: yesterday,
      };
    }

    return {
      createdFrom: toDateInputValue(daysAgo(range === "week" ? 6 : 29)),
      createdTo: today,
    };
  }

  function applyQuickDateRange(range: QuickDateRange): void {
    const { createdFrom, createdTo } = quickDateBounds(range);

    router.get(
      Endpoints.admin.jobs(),
      currentQuery({
        created_from: createdFrom,
        created_to: createdTo,
        page: 1,
      }),
      { preserveScroll: true },
    );
  }

  function quickDateRangeActive(range: QuickDateRange): boolean {
    const { createdFrom, createdTo } = quickDateBounds(range);

    return (
      (filters?.created_from ?? "") === createdFrom &&
      (filters?.created_to ?? "") === createdTo
    );
  }

  function handlePageChange(page: number): void {
    router.get(Endpoints.admin.jobs(), currentQuery({ page }), {
      preserveScroll: true,
    });
  }

  function handlePerPageChange(perPage: number): void {
    router.get(
      Endpoints.admin.jobs(),
      currentQuery({ page: 1, per_page: perPage }),
      { preserveScroll: true },
    );
  }

  function restartJob(job: AdminJobPayload): void {
    if (
      (job.status === "pending" || job.status === "running") &&
      !confirm("Restart this non-terminal job?")
    ) {
      return;
    }

    router.post(Endpoints.admin.restartJob(job.id), {});
  }
</script>

<svelte:head>
  <title>Admin Jobs</title>
</svelte:head>

<AdminShell title="Admin Jobs" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    <div class="grid min-w-0 gap-6">
      <SectionHeading
        eyebrow="Administration"
        title="Jobs"
        description="Global background job operations across all organizations."
      />

      <div class="grid gap-3 md:grid-cols-5">
        {#each statuses as status (status)}
          <div class="ui-inset rounded-xl border p-4 theme-border">
            <p class="text-xs uppercase tracking-[0.18em] ui-muted">
              {status}
            </p>
            <p class="mt-2 text-2xl font-semibold ui-title">
              {status_counts?.[status] ?? 0}
            </p>
          </div>
        {/each}
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        {#each [{ id: "queue-progress", title: "Queue progress", progress: queue_progress }, { id: "system-auth-mail", title: "System auth mail", progress: auth_mail_progress }] as summary (summary.id)}
          <Card className="rounded-[1.25rem] p-4">
            <section aria-labelledby={summary.id}>
              <h2 id={summary.id} class="text-base font-semibold ui-title">
                {summary.title}
              </h2>
              <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                {#each [{ label: "Pending", value: summary.progress?.pending ?? 0 }, { label: "Running", value: summary.progress?.running ?? 0 }, { label: "Failed", value: summary.progress?.failed ?? 0 }, { label: "Retrying", value: summary.progress?.retrying ?? 0 }, { label: "Stuck", value: summary.progress?.stuck ?? 0 }, { label: "Oldest ready job", value: summary.progress?.oldest_ready_age_seconds == null ? "No ready jobs" : `${summary.progress.oldest_ready_age_seconds} seconds` }] as metric (metric.label)}
                  <div class="ui-inset rounded-xl border p-3 theme-border">
                    <dt class="text-xs ui-muted">{metric.label}</dt>
                    <dd class="mt-1 font-semibold ui-title">{metric.value}</dd>
                  </div>
                {/each}
              </dl>
            </section>
          </Card>
        {/each}
      </div>

      <Card className="rounded-[1.25rem] p-4">
        <div class="grid gap-4">
          <div class="flex items-center gap-2">
            <div class="relative">
              <Button
                aria-controls="admin-job-filters"
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

          {#if filtersOpen}
            <form
              id="admin-job-filters"
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
                  <span class="font-medium ui-title">Type</span>
                  <Select
                    className="rounded-xl px-3 py-2 text-sm"
                    name="type"
                    value={filterString("type")}
                  >
                    <option value="">Any type</option>
                    {#each job_types ?? [] as type (type)}
                      <option value={type}>{jobTypeLabel(type)}</option>
                    {/each}
                  </Select>
                </label>

                <label class="grid gap-2 text-sm">
                  <span class="font-medium ui-title">Organization</span>
                  <Select
                    className="rounded-xl px-3 py-2 text-sm"
                    name="organization_id"
                    value={filters?.organization_id ?? ""}
                  >
                    <option value="">Any organization</option>
                    {#each organizations ?? [] as organization (organization.id)}
                      <option value={organization.id}
                        >{organization.name}</option
                      >
                    {/each}
                  </Select>
                </label>
              </div>

              <div class="flex flex-wrap items-center gap-4 text-sm">
                <label class="flex items-center gap-2 ui-title">
                  <input
                    checked={filterBool("has_warnings")}
                    class="h-4 w-4 rounded border-(--panel-border)"
                    name="has_warnings"
                    type="checkbox"
                  />
                  Warnings
                </label>
                <label class="flex items-center gap-2 ui-title">
                  <input
                    checked={filterBool("has_error")}
                    class="h-4 w-4 rounded border-(--panel-border)"
                    name="has_error"
                    type="checkbox"
                  />
                  Errors
                </label>
              </div>

              <div
                class="grid gap-4 rounded-xl border p-4 theme-border"
                style="background-color: var(--surface-muted);"
              >
                <div
                  class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                >
                  <div class="grid gap-2">
                    <span class="text-sm font-semibold ui-title">
                      Created date
                    </span>
                    <div
                      class="flex w-fit flex-wrap gap-1 rounded-full border p-1 theme-border"
                      style="background-color: var(--surface);"
                    >
                      {#each quickDateRanges as quickDateRange (quickDateRange.range)}
                        <button
                          class="h-8 cursor-pointer rounded-full border px-3 text-xs font-semibold transition"
                          style={quickDateRangeActive(quickDateRange.range)
                            ? "border-color: var(--accent); background-color: var(--accent-soft); color: var(--accent-ink);"
                            : "border-color: transparent; background-color: transparent; color: var(--page-muted);"}
                          type="button"
                          onclick={() =>
                            applyQuickDateRange(quickDateRange.range)}
                        >
                          {quickDateRange.label}
                        </button>
                      {/each}
                    </div>
                  </div>

                  <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[28rem]">
                    <label class="grid gap-2 text-sm">
                      <span class="font-medium ui-title">From</span>
                      <input
                        class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                        name="created_from"
                        type="date"
                        value={filters?.created_from ?? ""}
                      />
                    </label>

                    <label class="grid gap-2 text-sm">
                      <span class="font-medium ui-title">To</span>
                      <input
                        class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                        name="created_to"
                        type="date"
                        value={filters?.created_to ?? ""}
                      />
                    </label>
                  </div>
                </div>
              </div>

              <fieldset class="grid gap-3 border-t pt-4 theme-border">
                <legend class="text-sm font-medium ui-title">Status</legend>
                <div class="flex flex-wrap gap-3">
                  {#each statuses as status (status)}
                    <label class="flex items-center gap-2 text-sm ui-title">
                      <input
                        checked={statusChecked(status)}
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

              <input
                name="job_id"
                type="hidden"
                value={filters?.job_id ?? ""}
              />
              <input
                name="idempotency_key"
                type="hidden"
                value={filters?.idempotency_key ?? ""}
              />
              <input
                name="scheduled_from"
                type="hidden"
                value={filters?.scheduled_from ?? ""}
              />
              <input
                name="scheduled_to"
                type="hidden"
                value={filters?.scheduled_to ?? ""}
              />

              <div class="grid gap-3 border-t pt-4 theme-border">
                <button
                  aria-controls="admin-job-advanced-filters"
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

                <div
                  id="admin-job-advanced-filters"
                  class={`rounded-xl border p-4 theme-border ${advancedFiltersOpen ? "grid gap-4" : "hidden"}`}
                  style="background-color: var(--surface-muted);"
                >
                  <div class="grid gap-4 lg:grid-cols-2">
                    <label class="grid gap-2 text-sm">
                      <span class="font-medium ui-title">
                        Deletion operation
                      </span>
                      <input
                        class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                        name="deletion_run_id"
                        type="text"
                        value={filters?.deletion_run_id ?? ""}
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
                  </div>

                  <div class="grid gap-4 lg:grid-cols-2">
                    <label class="grid gap-2 text-sm">
                      <span class="font-medium ui-title">Worker</span>
                      <input
                        class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                        name="worker_id"
                        type="text"
                        value={filters?.worker_id ?? ""}
                      />
                    </label>
                  </div>
                </div>
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

      <div class="grid min-w-0 gap-4">
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

        <TableFrame ariaLabel="Admin jobs" tableClassName="min-w-[1080px]">
          <thead>
            <tr>
              <th class="px-4 py-3 text-left">
                <TableSortButton
                  label="Status"
                  field="status"
                  {currentSort}
                  {currentDirection}
                  onSort={sortJobs}
                />
              </th>
              <th class="px-4 py-3 text-left">
                <TableSortButton
                  label="Type"
                  field="type"
                  {currentSort}
                  {currentDirection}
                  onSort={sortJobs}
                />
              </th>
              <th class="px-4 py-3 text-left">
                <TableSortButton
                  label="Organization"
                  field="organizationId"
                  {currentSort}
                  {currentDirection}
                  onSort={sortJobs}
                />
              </th>
              <th class="px-4 py-3 text-left">
                <TableSortButton
                  label="Created"
                  field="createdAt"
                  {currentSort}
                  {currentDirection}
                  onSort={sortJobs}
                />
              </th>
              <th class="px-4 py-3 text-left">
                <TableSortButton
                  label="Scheduled"
                  field="scheduledAt"
                  {currentSort}
                  {currentDirection}
                  onSort={sortJobs}
                />
              </th>
              <th class="px-4 py-3 text-left">
                <TableSortButton
                  label="Completed"
                  field="completedAt"
                  {currentSort}
                  {currentDirection}
                  onSort={sortJobs}
                />
              </th>
              <th class="px-4 py-3 text-left">
                <TableSortButton
                  label="Attempts"
                  field="attemptCount"
                  {currentSort}
                  {currentDirection}
                  onSort={sortJobs}
                />
              </th>
              <th class="px-4 py-3 text-left">
                <TableSortButton
                  label="Priority"
                  field="priority"
                  {currentSort}
                  {currentDirection}
                  onSort={sortJobs}
                />
              </th>
              <th class="px-4 py-3 text-left">Warnings</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            {#if jobs.length === 0}
              <tr>
                <td class="px-4 py-6 text-sm ui-muted" colspan="10">
                  No jobs found.
                </td>
              </tr>
            {:else}
              {#each jobs as job (job.id)}
                <tr class="align-top">
                  <td class="px-4 py-3">
                    <Badge tone={jobStatusTone(job.status)}>
                      {job.status}
                    </Badge>
                  </td>
                  <td class="px-4 py-3">
                    <div class="grid gap-1">
                      <span class="font-medium ui-title">
                        {jobTypeLabel(job.type)}
                      </span>
                      {#if job.deletionRunId}
                        <Link
                          class="break-all text-xs ui-muted hover:underline"
                          href={`/admin/jobs?deletion_run_id=${encodeURIComponent(job.deletionRunId)}`}
                        >
                          {job.deletionRunId}
                        </Link>
                      {/if}
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    {#if job.organization}
                      <Link
                        class="font-medium ui-title hover:underline"
                        href={`/admin/organizations/${job.organization.id}`}
                      >
                        {job.organization.name}
                      </Link>
                      <p class="text-xs ui-muted">
                        /{job.organization.slug}
                      </p>
                    {:else}
                      <span class="break-all text-xs ui-muted">
                        {job.organizationId}
                      </span>
                    {/if}
                  </td>
                  <td class="px-4 py-3 text-(--muted)">
                    {formatDate(job.createdAt)}
                  </td>
                  <td class="px-4 py-3 text-(--muted)">
                    {formatDate(job.scheduledAt)}
                  </td>
                  <td class="px-4 py-3 text-(--muted)">
                    {formatDate(job.completedAt)}
                  </td>
                  <td class="px-4 py-3 text-(--muted)">
                    {job.attemptCount}/{job.maxAttempts}
                  </td>
                  <td class="px-4 py-3 text-(--muted)">
                    {job.priority}
                  </td>
                  <td class="px-4 py-3">
                    {#if job.warningCount > 0}
                      <Badge tone="gold">{job.warningCount}</Badge>
                    {:else}
                      <span class="text-xs ui-muted">0</span>
                    {/if}
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-2">
                      <Link
                        class="ui-button-secondary inline-flex h-9 items-center rounded-full px-4 text-xs font-semibold no-underline"
                        href={jobDetailsUrl(job.id)}
                      >
                        View
                      </Link>
                      <Button
                        className="h-9 rounded-full px-4 text-xs"
                        type="button"
                        variant="dangerSoft"
                        onclick={() => restartJob(job)}
                      >
                        Restart
                      </Button>
                    </div>
                  </td>
                </tr>
              {/each}
            {/if}
          </tbody>
        </TableFrame>

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
  </div>
</AdminShell>
