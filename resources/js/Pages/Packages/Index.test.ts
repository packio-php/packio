import { fireEvent, render, screen, within } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router, setPageProps } from "@/test/inertia";
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
  it("renders a compact header without intro cards", () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource],
      },
    });

    expect(
      screen.getByRole("heading", { level: 2, name: "Packages" }),
    ).toBeInTheDocument();
    expect(screen.queryByText("What this page is for")).toBeNull();
    expect(screen.queryByText("Visibility split")).toBeNull();
  });

  it("shows a plain package count instead of pagination on a single page", () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: packageStats,
        pagination: { ...pagination, from: 1, to: 2, total: 2, last_page: 1 },
        sections: [packageWithSource],
      },
    });

    expect(screen.getByText("2 packages")).toBeInTheDocument();
    expect(screen.queryByText(/Showing/)).toBeNull();
    expect(screen.queryByText("→")).toBeNull();
  });

  it("uses the singular package count for one package", () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: packageStats,
        pagination: { ...pagination, from: 1, to: 1, total: 1, last_page: 1 },
        sections: [packageWithSource],
      },
    });

    expect(screen.getByText("1 package")).toBeInTheDocument();
  });

  it("does not show a separate result counter above the list", () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: { ...packageStats, total: 40 },
        pagination: { ...multiPagePagination, from: 19, to: 36, total: 40 },
        sections: [packageWithSource],
      },
    });

    expect(screen.queryByText(/of 40 packages/)).toBeNull();
  });

  it("explains where to start in an empty catalog", () => {
    setPageProps({ current_membership: { role: "owner" } });
    const empty = {
      auth: { check: true },
      organization_summary: organization,
      package_stats: { ...packageStats, total: 0 },
      pagination: { ...pagination, from: 0, to: 0, total: 0 },
      sections: [],
    };
    const { unmount } = render(PackagesIndex, { props: empty });

    expect(screen.getByText("No packages yet")).toBeInTheDocument();
    expect(
      screen.getByText(/Connect a repository in Sources/),
    ).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open sources" })).toHaveAttribute(
      "href",
      "/organization/acme/sources",
    );
    unmount();

    setPageProps({});
    render(PackagesIndex, { props: empty });
    expect(screen.queryByRole("link", { name: "Open sources" })).toBeNull();
  });

  it("offers a reset when the search finds nothing", async () => {
    render(PackagesIndex, {
      props: {
        filters: { ...activeFilters, search: "zzz" },
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination: { ...pagination, from: 0, to: 0, total: 0 },
        sections: [],
      },
    });

    expect(
      screen.getByText("No packages match your search"),
    ).toBeInTheDocument();
    await fireEvent.click(
      screen.getByRole("button", { name: "Clear filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/packages",
      { per_page: 18 },
      { preserveScroll: true },
    );
  });

  const privatePackage: PackagesIndexSection = {
    ...packageWithSource,
    visibility: "private",
  };
  const failedPackage: PackagesIndexSection = {
    ...packageWithSource,
    name: "acme/broken",
    source: {
      ...packageWithSource.source!,
      last_sync_status: "failed",
    },
  };

  function renderCatalog(mode: "grid" | "table") {
    localStorage.setItem("packio-list-view-mode", mode);
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: packageStats,
        pagination,
        sections: [privatePackage, failedPackage, hiddenManualPackage],
      },
    });
  }

  it.each(["grid", "table"] as const)(
    "shows the same package data in %s mode",
    (mode) => {
      renderCatalog(mode);

      expect(screen.queryByRole("table", { name: "Packages" }) !== null).toBe(
        mode === "table",
      );
      expect(
        screen.getByRole("link", { name: "acme/app" }).getAttribute("href"),
      ).toContain("/organization/acme/packages/acme/app");
      expect(screen.getAllByText("1.2.3")).toHaveLength(2);
      // The filter toolbar also renders Listed/Hidden/Manual, so scope to the catalog.
      const catalog = within(
        mode === "table"
          ? screen.getByRole("table", { name: "Packages" })
          : screen.getAllByRole("article")[0].parentElement!,
      );
      expect(catalog.getAllByText("Token required")).toHaveLength(2);
      expect(catalog.getAllByText("Listed")).toHaveLength(
        2 + (mode === "table" ? 1 : 0),
      );
      expect(screen.getAllByText("42")).toHaveLength(2);
      expect(
        screen.getAllByRole("link", { name: "acme/app-source" })[0],
      ).toHaveAttribute("href", "/organization/acme/sources/source-1");
      expect(screen.queryByText("https://example.com/acme/app.git")).toBeNull();
      expect(screen.queryByRole("link", { name: "Open details" })).toBeNull();
      expect(screen.getByText("Sync failed")).toBeInTheDocument();
      expect(catalog.getAllByText("Manual")).toHaveLength(1);
      expect(catalog.getAllByText("Hidden")).toHaveLength(1);
      expect(screen.getByText("No available version")).toBeInTheDocument();
    },
  );

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

    await fireEvent.input(screen.getByLabelText("Search packages"), {
      target: { value: "acme" },
    });
    await fireEvent.submit(
      screen
        .getByRole("searchbox", { name: "Search packages" })
        .closest("form")!,
    );

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/packages",
      { search: "acme", per_page: 18, page: 1 },
      { preserveScroll: true },
    );
  });

  it("submits package search from the search button", async () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource],
      },
    });

    await fireEvent.input(screen.getByLabelText("Search packages"), {
      target: { value: "frankenphp" },
    });
    await fireEvent.click(screen.getByRole("button", { name: "Search" }));

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/packages",
      expect.objectContaining({ search: "frankenphp", page: 1 }),
      { preserveScroll: true },
    );
  });

  it("shows the search field without opening filters", () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource],
      },
    });

    expect(
      screen.getByRole("searchbox", { name: "Search packages" }),
    ).toBeInTheDocument();
  });

  it("keeps applied filters when search is submitted with the panel closed", async () => {
    render(PackagesIndex, {
      props: {
        filters: {
          ...activeFilters,
          listed: "hidden",
          page: 3,
          search: "",
          source: "",
          source_sync_status: [],
          visibility: [],
        },
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination: multiPagePagination,
        sections: [packageWithSource],
      },
    });

    const search = screen.getByRole("searchbox", { name: "Search packages" });
    await fireEvent.input(search, { target: { value: "frankenphp" } });
    await fireEvent.submit(search.closest("form")!);

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/packages",
      { search: "frankenphp", listed: "hidden", per_page: 18, page: 1 },
      { preserveScroll: true },
    );
  });

  it("toggles the filters panel", async () => {
    render(PackagesIndex, {
      props: {
        organization_summary: organization,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource],
      },
    });

    const button = screen.getByRole("button", { name: "Filters" });
    expect(button).toHaveAttribute("aria-expanded", "false");
    await fireEvent.click(button);
    expect(button).toHaveAttribute("aria-expanded", "true");
    await fireEvent.click(button);
    expect(button).toHaveAttribute("aria-expanded", "false");
  });

  it("shows applied filters next to results", () => {
    render(PackagesIndex, {
      props: {
        filters: {
          ...activeFilters,
          listed: "hidden",
          search: "",
          source: "manual",
          source_sync_status: [],
          visibility: [],
        },
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination,
        sections: [packageWithSource],
      },
    });

    expect(screen.getByText("Listed: Hidden")).toBeInTheDocument();
    expect(screen.getByText("Source: Manual")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Clear filters" }),
    ).toBeInTheDocument();
  });

  it("renders a single Clear filters control in the filtered empty state", () => {
    render(PackagesIndex, {
      props: {
        filters: { ...activeFilters, search: "zzz" },
        organization_summary: organization,
        package_filter_options: filterOptions,
        package_stats: packageStats,
        pagination: { ...pagination, from: 0, to: 0, total: 0 },
        sections: [],
      },
    });

    expect(
      screen.getAllByRole("button", { name: "Clear filters" }),
    ).toHaveLength(1);
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

    await fireEvent.click(screen.getByRole("button", { name: "Filters" }));
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
