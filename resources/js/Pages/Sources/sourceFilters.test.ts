import { describe, expect, it } from "vitest";
import { activeFilterChips, clearFiltersQuery } from "./sourceFilters";
import type { SourceIndexFilters } from "@/types/pages";

const empty: SourceIndexFilters = {
  page: 1,
  per_page: 18,
  provider: [],
  search: "",
  status: [],
  sync_status: [],
  visibility: [],
};
const options = {
  providers: [{ value: "github", label: "GitHub" }],
  sync_statuses: [{ value: "failed", label: "Failed sync" }],
};

describe("sourceFilters", () => {
  it("returns no chips for empty filters", () => {
    expect(activeFilterChips(empty, options)).toEqual([]);
  });

  it("maps filters to chip labels and skips the search term", () => {
    expect(
      activeFilterChips(
        {
          ...empty,
          provider: ["github", "other"],
          search: "x",
          status: ["archived"],
          sync_status: ["failed", "odd"],
          visibility: ["private"],
        },
        options,
      ),
    ).toEqual([
      "Provider: GitHub",
      "Provider: other",
      "Visibility: private",
      "Status: archived",
      "Sync: Failed sync",
      "Sync: odd",
    ]);
  });

  it("builds the clear query", () => {
    expect(clearFiltersQuery(18)).toEqual({ per_page: 18 });
  });
});
