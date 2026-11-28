import type { SourceFilterOptions, SourceIndexFilters } from "@/types/pages";

/** Chip labels for applied filters; the search term stays in the field. */
export function activeFilterChips(
  filters: SourceIndexFilters,
  options: SourceFilterOptions,
): string[] {
  return [
    ...filters.provider.map(
      (value) =>
        `Provider: ${options.providers.find((o) => o.value === value)?.label ?? value}`,
    ),
    ...filters.visibility.map((value) => `Visibility: ${value}`),
    ...filters.status.map((value) => `Status: ${value}`),
    ...filters.sync_status.map(
      (value) =>
        `Sync: ${options.sync_statuses.find((o) => o.value === value)?.label ?? value}`,
    ),
  ];
}

export function clearFiltersQuery(perPage: number): { per_page: number } {
  return { per_page: perPage };
}
