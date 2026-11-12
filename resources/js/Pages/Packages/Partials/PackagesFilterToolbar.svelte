<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import { FilterX, ListFilter, Search } from "@lucide/svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ListViewToggle from "@/Components/ui/ListViewToggle.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import { cleanQuery } from "@/lib/queryFilters";
  import type { ListViewMode } from "@/lib/listViewMode";
  import type {
    PackageFilterOptions,
    PackageIndexFilters,
  } from "@/types/pages";

  const visibilityOptions = ["public", "private"] as const;
  const defaultFilters: PackageIndexFilters = {
    listed: "",
    page: 1,
    per_page: 18,
    search: "",
    source: "",
    source_sync_status: [],
    visibility: [],
  };
  const defaultFilterOptions: PackageFilterOptions = {
    source_sync_statuses: [],
  };

  interface Props {
    filters?: PackageIndexFilters;
    filterOptions?: PackageFilterOptions;
    mode?: ListViewMode;
    onModeChange?: (mode: ListViewMode) => void;
    withOrganization: (path: string) => string;
  }

  let {
    filters = defaultFilters,
    filterOptions = defaultFilterOptions,
    mode = "grid",
    onModeChange = () => {},
    withOrganization,
  }: Props = $props();

  let filtersOpen = $state(false);

  let resolvedFilters = $derived({ ...defaultFilters, ...(filters ?? {}) });
  let resolvedFilterOptions = $derived({
    ...defaultFilterOptions,
    ...(filterOptions ?? {}),
  });

  function checked(values: string[], value: string): boolean {
    return values.includes(value);
  }

  function activeFilterCount(): number {
    let count = 0;

    if (resolvedFilters.search !== "") count += 1;
    if (resolvedFilters.visibility.length > 0) count += 1;
    if (resolvedFilters.listed !== "") count += 1;
    if (resolvedFilters.source !== "") count += 1;
    if (resolvedFilters.source_sync_status.length > 0) count += 1;

    return count;
  }

  function applyFilters(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    const formData = new FormData(event.currentTarget);

    router.get(
      withOrganization("/packages"),
      cleanQuery({
        search: String(formData.get("search") ?? ""),
        visibility: formData.getAll("visibility").map(String),
        listed: String(formData.get("listed") ?? ""),
        source: String(formData.get("source") ?? ""),
        source_sync_status: formData.getAll("source_sync_status").map(String),
        per_page: resolvedFilters.per_page,
        page: 1,
      }),
      { preserveScroll: true },
    );
  }

  function clearFilters(): void {
    router.get(
      withOrganization("/packages"),
      cleanQuery({
        per_page: resolvedFilters.per_page,
      }),
      { preserveScroll: true },
    );
  }
</script>

<Card className="rounded-[1.25rem] p-4">
  <div class="grid gap-4">
    <div
      class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
      <div class="flex items-center gap-2">
        <div class="relative">
          <Button
            aria-controls="package-index-filters"
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

      <div class="flex shrink-0 justify-end">
        <ListViewToggle {mode} onChange={onModeChange} />
      </div>
    </div>

    {#if filtersOpen}
      <form
        id="package-index-filters"
        class="grid min-w-0 flex-1 gap-4"
        onsubmit={(event) => {
          event.preventDefault();
          applyFilters(
            event as SubmitEvent & {
              currentTarget: EventTarget & HTMLFormElement;
            },
          );
        }}
      >
        <div
          class="grid gap-4 lg:grid-cols-[minmax(14rem,1.2fr)_repeat(2,minmax(10rem,0.8fr))]"
        >
          <label class="grid gap-2 text-sm">
            <span class="font-medium ui-title">Search</span>
            <input
              class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
              name="search"
              type="search"
              value={resolvedFilters.search}
            />
          </label>

          <label class="grid gap-2 text-sm">
            <span class="font-medium ui-title">Listed</span>
            <Select
              className="rounded-xl px-3 py-2 text-sm"
              name="listed"
              value={resolvedFilters.listed}
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
              value={resolvedFilters.source}
            >
              <option value="">Any source</option>
              <option value="managed">Managed</option>
              <option value="manual">Manual</option>
            </Select>
          </label>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <fieldset class="grid gap-2 text-sm">
            <legend class="font-medium ui-title">Visibility</legend>
            <div class="flex flex-wrap gap-3">
              {#each visibilityOptions as visibility (visibility)}
                <label class="flex items-center gap-2 ui-title">
                  <input
                    checked={checked(resolvedFilters.visibility, visibility)}
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

          {#if resolvedFilterOptions.source_sync_statuses.length > 0}
            <fieldset class="grid gap-2 text-sm">
              <legend class="font-medium ui-title">Source sync</legend>
              <div class="flex flex-wrap gap-3">
                {#each resolvedFilterOptions.source_sync_statuses as status (status.value)}
                  <label class="flex items-center gap-2 ui-title">
                    <input
                      checked={checked(
                        resolvedFilters.source_sync_status,
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

        <div class="flex flex-wrap gap-3">
          <Button
            className="gap-2 rounded-full px-5"
            type="submit"
            variant="primary"
          >
            <Search aria-hidden="true" size={16} />
            Apply filters
          </Button>
          {#if activeFilterCount() > 0}
            <Button
              className="gap-2 rounded-full px-5"
              title="Clear filters"
              type="button"
              variant="secondary"
              onclick={clearFilters}
            >
              <FilterX aria-hidden="true" size={16} />
              Clear
            </Button>
          {/if}
        </div>
      </form>
    {/if}
  </div>
</Card>
