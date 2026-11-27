import { describe, expect, it } from "vitest";
import { activeFilterChips, clearFiltersQuery } from "./packageFilters";
import type { PackageIndexFilters } from "@/types/pages";

const empty: PackageIndexFilters = {
  listed: "",
  page: 1,
  per_page: 18,
  search: "",
  source: "",
  source_sync_status: [],
  visibility: [],
};
const options = {
  source_sync_statuses: [{ value: "failed", label: "Failed sync" }],
};

describe("packageFilters", () => {
  it("returns no chips for empty filters", () => {
    expect(activeFilterChips(empty, options)).toEqual([]);
  });

  it("maps filters to chip labels and skips the search term", () => {
    expect(
      activeFilterChips(
        {
          ...empty,
          listed: "hidden",
          search: "x",
          source: "manual",
          source_sync_status: ["failed", "other"],
          visibility: ["private"],
        },
        options,
      ),
    ).toEqual([
      "Listed: Hidden",
      "Source: Manual",
      "Visibility: private",
      "Source sync: Failed sync",
      "Source sync: other",
    ]);
  });

  it("builds the clear query", () => {
    expect(clearFiltersQuery(18)).toEqual({ per_page: 18 });
  });
});
