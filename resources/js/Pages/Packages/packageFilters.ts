import type { PackageFilterOptions, PackageIndexFilters } from "@/types/pages";

const capitalize = (value: string): string =>
  value.charAt(0).toUpperCase() + value.slice(1);

/** Chip labels for applied filters; the search term stays in the field. */
export function activeFilterChips(
  filters: PackageIndexFilters,
  options: PackageFilterOptions,
): string[] {
  return [
    ...(filters.listed ? [`Listed: ${capitalize(filters.listed)}`] : []),
    ...(filters.source ? [`Source: ${capitalize(filters.source)}`] : []),
    ...filters.visibility.map((value) => `Visibility: ${value}`),
    ...filters.source_sync_status.map(
      (value) =>
        `Source sync: ${options.source_sync_statuses.find((o) => o.value === value)?.label ?? value}`,
    ),
  ];
}

export function clearFiltersQuery(perPage: number): { per_page: number } {
  return { per_page: perPage };
}
