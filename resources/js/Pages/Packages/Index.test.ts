import { fireEvent, render, screen, within } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import PackagesIndex from "./Index.svelte";
import type {
  PackageFilterOptions,
  PackageIndexFilters,
  PackageStatsPayload,
  PackagesIndexSection,
  PaginationPayload,
} from "@/types/pages";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const organization = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  anonymous_access: true,
};

const packageStats: PackageStatsPayload = {
  listed: 1,
  private: 1,
  public: 1,
  total: 2,
};

const pagination: PaginationPayload<PackagesIndexSection> = {
  current_page: 1,
  from: 1,
  has_more_pages: false,
  has_previous_pages: false,
  last_page: 1,
  per_page: 18,
  to: 2,
  total: 2,
};

const multiPagePagination: PaginationPayload<PackagesIndexSection> = {
  ...pagination,
  has_more_pages: true,
  last_page: 2,
  total: 20,
};

const packageWithSource: PackagesIndexSection = {
  active: true,
  description: "Application package",
  download_count: 42,
  latest_version: "1.2.3",
  name: "acme/app",
  source: {
    active: true,
    archived: false,
    id: "source-1",
    last_failed_sync_at: null,
    last_successful_sync_at: "2026-01-02T12:00:00+00:00",
    last_sync_status: "success",
    name: "acme/app-source",
    type: "git",
    url: "https://example.com/acme/app.git",
    visibility: "private",
  },
  visibility: "public",
};

const hiddenManualPackage: PackagesIndexSection = {
  active: false,
  description: null,
  download_count: 0,
  latest_version: null,
  name: "acme/hidden",
  source: null,
  visibility: "private",
};

const filterOptions: PackageFilterOptions = {
  source_sync_statuses: [{ value: "success", label: "success" }],
};

const activeFilters: PackageIndexFilters = {
  listed: "listed",
  page: 1,
  per_page: 18,
  search: "acme",
  source: "managed",
  source_sync_status: ["success"],
  visibility: ["public"],
};

describe("Packages index page", () => {
  it("renders package cards in the default grid view", () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource],
      },
    });

    expect(
      screen.getByRole("button", { name: "Show as grid" }),
    ).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByText("acme/app")).toBeInTheDocument();
    expect(screen.getByText("Managed by source")).toBeInTheDocument();
    expect(
      screen.queryByRole("table", { name: "Packages" }),
    ).not.toBeInTheDocument();
  });

  it("stores table mode and renders package table rows", async () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource, hiddenManualPackage],
      },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Show as table" }),
    );

    const table = screen.getByRole("table", { name: "Packages" });

    expect(localStorage.getItem("packio-list-view-mode")).toBe("table");
    expect(
      within(table).getByRole("columnheader", { name: "Latest" }),
    ).toBeInTheDocument();
    expect(within(table).getByText("acme/app")).toBeInTheDocument();
    expect(within(table).getByText("1.2.3")).toBeInTheDocument();
    expect(within(table).getByText("manual")).toBeInTheDocument();
  });

  it("keeps package and source badge states in table view", async () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource, hiddenManualPackage],
      },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Show as table" }),
    );

    const table = screen.getByRole("table", { name: "Packages" });

    expect(within(table).getByText("Public")).toBeInTheDocument();
    expect(within(table).getByText("Token required")).toBeInTheDocument();
    expect(within(table).getAllByText("Listed")).toHaveLength(2);
    expect(within(table).getByText("Hidden")).toBeInTheDocument();
    expect(within(table).getByText("success")).toBeInTheDocument();
    expect(within(table).getByText("Source active")).toBeInTheDocument();
  });

  it("submits package search with page reset", async () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource],
      },
    });

    expect(screen.queryByLabelText("Search")).not.toBeInTheDocument();
    await fireEvent.click(screen.getByRole("button", { name: "Show filters" }));
    await fireEvent.input(screen.getByLabelText("Search"), {
      target: { value: "acme" },
    });
    await fireEvent.click(
      screen.getByRole("button", { name: "Apply filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/packages",
      { search: "acme", per_page: 18, page: 1 },
      { preserveScroll: true },
    );
  });

  it("submits package filters with page reset", async () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource, hiddenManualPackage],
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: "Show filters" }));
    await fireEvent.click(screen.getByLabelText("private"));
    await fireEvent.change(screen.getByLabelText("Listed"), {
      target: { value: "hidden" },
    });
    await fireEvent.change(screen.getByLabelText("Source"), {
      target: { value: "manual" },
    });
    await fireEvent.click(screen.getByLabelText("success"));
    await fireEvent.click(
      screen.getByRole("button", { name: "Apply filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/packages",
      {
        visibility: ["private"],
        listed: "hidden",
        source: "manual",
        source_sync_status: ["success"],
        per_page: 18,
        page: 1,
      },
      { preserveScroll: true },
    );
  });

  it("clears package filters without filter params", async () => {
    render(PackagesIndex, {
      props: {
        filters: activeFilters,
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource],
      },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Clear filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/packages",
      { per_page: 18 },
      { preserveScroll: true },
    );
  });

  it("preserves package filters during pagination", async () => {
    render(PackagesIndex, {
      props: {
        filters: activeFilters,
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination: multiPagePagination,
        sections: [packageWithSource],
      },
    });

    await fireEvent.click(screen.getAllByText("→")[0]);

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/packages",
      {
        search: "acme",
        visibility: ["public"],
        listed: "listed",
        source: "managed",
        source_sync_status: ["success"],
        per_page: 18,
        page: 2,
      },
      { preserveScroll: true },
    );
  });

  it("passes current package list query to details links", () => {
    render(PackagesIndex, {
      props: {
        filters: { ...activeFilters, page: 2 },
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination: { ...multiPagePagination, current_page: 2 },
        sections: [packageWithSource],
      },
    });

    const href =
      screen.getByRole("link", { name: "acme/app" }).getAttribute("href") ?? "";
    const returnTo = new URLSearchParams(href.split("?")[1] ?? "").get(
      "return_to",
    );

    expect(returnTo).toBe(
      "/organization/acme/packages?search=acme&visibility[]=public&listed=listed&source=managed&source_sync_status[]=success&per_page=18&page=2",
    );
  });
});
