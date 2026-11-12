import {
  fireEvent,
  render,
  screen,
  waitFor,
  within,
} from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router, setPageProps } from "@/test/inertia";
import { apiClient } from "@/lib/api";
import SourcesIndex from "./Index.svelte";
import type {
  PaginationPayload,
  ProviderAuthorizationPayload,
  SourceFilterOptions,
  SourceIndexFilters,
  SourceListItem,
  SourceProviderPayload,
} from "@/types/pages";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const organization = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  anonymous_access: false,
};

const genericProvider: SourceProviderPayload = {
  key: "generic_git",
  name: "Generic Git",
  description: "Any Git repository",
  oauth_available: false,
  oauth_enabled: false,
  oauth_provider: null,
  capabilities: { supports_synchronization: true },
};

const githubProvider: SourceProviderPayload = {
  key: "github",
  name: "GitHub",
  description: "GitHub repositories",
  oauth_available: true,
  oauth_enabled: true,
  oauth_provider: "github",
  capabilities: { supports_synchronization: true },
};

const activeSource: SourceListItem = {
  active: true,
  archived: false,
  default_ref: "main",
  id: "source-1",
  keep_last_releases: 3,
  last_failed_sync_at: null,
  last_successful_sync_at: "2026-01-02T12:00:00+00:00",
  last_sync_error: null,
  last_sync_status: "success",
  name: "acme/app",
  provider: "generic_git",
  provider_label: "Generic Git",
  url: "https://example.com/acme/app.git",
  visibility: "private",
};

const archivedSource: SourceListItem = {
  ...activeSource,
  active: false,
  archived: true,
  archived_at: "2026-01-03T12:00:00+00:00",
  id: "source-2",
  name: "acme/legacy",
  url: "https://example.com/acme/legacy.git",
  visibility: "public",
};

const pagination: PaginationPayload<SourceListItem> = {
  current_page: 1,
  from: 1,
  has_more_pages: true,
  has_previous_pages: false,
  last_page: 2,
  per_page: 18,
  to: 1,
  total: 20,
};

const filterOptions: SourceFilterOptions = {
  providers: [{ value: "generic_git", label: "Generic Git" }],
  sync_statuses: [{ value: "success", label: "success" }],
};

const activeFilters: SourceIndexFilters = {
  page: 1,
  per_page: 18,
  provider: ["generic_git"],
  search: "acme",
  status: ["active"],
  sync_status: ["success"],
  visibility: ["private"],
};

describe("Sources index page", () => {
  it("renders authorization deletion above the provider access modal", async () => {
    const inactiveAuthorization: ProviderAuthorizationPayload = {
      id: "authorization-inactive",
      provider: "github",
      auth_strategy: "oauth",
      account_name: "Former maintainer",
      configured: false,
      masked_value: "token_****",
      status: "inactive",
      auth_health: "reauthorization_required",
    };
    vi.spyOn(apiClient, "post").mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        has_sources: false,
        source_count: 0,
        sources: [],
        other_authorizations: [],
      },
    });

    render(SourcesIndex, {
      props: {
        organization,
        providers: [githubProvider],
        provider_authorizations: [inactiveAuthorization],
        source_stats: { active: 0, archived: 0, total: 0 },
        can_manage_sources: true,
      },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Manage provider access" }),
    );
    await fireEvent.click(
      screen.getByRole("button", { name: "Delete authorization" }),
    );

    await waitFor(() => {
      expect(
        screen.getByText(/No sources are using this authorization/),
      ).toBeInTheDocument();
    });

    expect(
      screen.getByRole("dialog", { name: "Manage provider access" })
        .parentElement?.parentElement,
    ).toHaveClass("z-[60]");
    expect(
      screen.getByText("Delete authorization").closest('[role="dialog"]'),
    ).toHaveClass("z-[70]");
  });

  it("converts source connect form data into Inertia payload", async () => {
    setPageProps({ current_membership: { role: "owner" } });
    render(SourcesIndex, {
      props: {
        organization,
        providers: [genericProvider],
        source_stats: { active: 0, archived: 0, total: 0 },
        can_manage_sources: true,
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: "Add source" }));
    await fireEvent.input(screen.getByLabelText("Repository URL"), {
      target: { value: "https://example.com/vendor/package.git" },
    });
    await fireEvent.input(screen.getByLabelText("composer.json path"), {
      target: { value: "packages/app/composer.json" },
    });
    await fireEvent.input(
      document.querySelector('input[name="keep_last_releases"]')!,
      {
        target: { value: "5" },
      },
    );

    await fireEvent.click(
      screen.getByRole("button", { name: "Connect 1 source" }),
    );

    await waitFor(() => {
      expect(router.post).toHaveBeenCalledWith(
        expect.stringContaining("/organization/acme/sources/connect"),
        {
          provider: "generic_git",
          repository_url: "https://example.com/vendor/package.git",
          composer_json_path: "packages/app/composer.json",
          visibility: "private",
          keep_last_releases: "5",
        },
        expect.objectContaining({
          preserveState: true,
          preserveScroll: true,
        }),
      );
    });

    expect(sessionStorage.getItem("sources_connect_success")).toBe("true");
  });

  it("hides management controls from non-managers", () => {
    setPageProps({ current_membership: { role: "member" } });
    render(SourcesIndex, {
      props: {
        organization,
        providers: [genericProvider],
        source_stats: { active: 0, archived: 0, total: 0 },
        can_manage_sources: false,
      },
    });

    expect(
      screen.queryByRole("button", { name: "Add source" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByText(
        "Owner membership is required to connect and synchronize organization sources.",
      ),
    ).toBeInTheDocument();
  });

  it("renders source cards in the default grid view", () => {
    render(SourcesIndex, {
      props: {
        organization,
        providers: [genericProvider],
        sources: [activeSource],
        source_stats: { active: 1, archived: 0, total: 1 },
      },
    });

    expect(
      screen.getByRole("button", { name: "Show as grid" }),
    ).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByText("acme/app")).toBeInTheDocument();
    expect(
      screen.getByText("https://example.com/acme/app.git"),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("table", { name: "Active sources" }),
    ).not.toBeInTheDocument();
  });

  it("stores table mode and renders source table rows", async () => {
    render(SourcesIndex, {
      props: {
        organization,
        providers: [genericProvider],
        sources: [activeSource, archivedSource],
        source_stats: { active: 1, archived: 1, total: 2 },
      },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Show as table" }),
    );

    const table = screen.getByRole("table", { name: "Sources" });

    expect(localStorage.getItem("packio-list-view-mode")).toBe("table");
    expect(
      within(table).getByRole("columnheader", { name: "Retention" }),
    ).toBeInTheDocument();
    expect(within(table).getAllByText("keeps latest 3").length).toBeGreaterThan(
      0,
    );
    expect(within(table).getByText("success")).toBeInTheDocument();
    expect(within(table).queryByText(/^success /)).not.toBeInTheDocument();
    expect(within(table).queryByText(/^failure /)).not.toBeInTheDocument();
    expect(within(table).getByText("no failures").closest("p")).toHaveAttribute(
      "title",
      "Last failed sync",
    );
    expect(within(table).getByText("archived")).toBeInTheDocument();
  });

  it("shows one filtered empty state for unified sources", () => {
    render(SourcesIndex, {
      props: {
        organization,
        providers: [genericProvider],
        filters: activeFilters,
        sources: [],
        source_stats: { active: 1, archived: 1, total: 2 },
      },
    });

    expect(
      screen.getByText("No sources match current filters."),
    ).toBeInTheDocument();
  });

  it("submits source search with page reset", async () => {
    render(SourcesIndex, {
      props: {
        organization,
        providers: [genericProvider],
        source_filter_options: filterOptions,
        sources: [activeSource],
        source_stats: { active: 1, archived: 0, total: 1 },
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
      "/organization/acme/sources",
      { search: "acme", per_page: 18, page: 1 },
      { preserveScroll: true },
    );
  });

  it("submits source filters with page reset", async () => {
    render(SourcesIndex, {
      props: {
        organization,
        providers: [genericProvider],
        source_filter_options: filterOptions,
        sources: [activeSource],
        source_stats: { active: 1, archived: 0, total: 1 },
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: "Show filters" }));
    await fireEvent.click(screen.getByLabelText("Generic Git"));
    await fireEvent.click(screen.getByLabelText("private"));
    await fireEvent.click(screen.getByLabelText("active"));
    await fireEvent.click(screen.getByLabelText("success"));
    await fireEvent.click(
      screen.getByRole("button", { name: "Apply filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/sources",
      {
        provider: ["generic_git"],
        visibility: ["private"],
        status: ["active"],
        sync_status: ["success"],
        per_page: 18,
        page: 1,
      },
      { preserveScroll: true },
    );
  });

  it("clears source filters without filter params", async () => {
    render(SourcesIndex, {
      props: {
        organization,
        filters: activeFilters,
        providers: [genericProvider],
        source_filter_options: filterOptions,
        sources: [activeSource],
        source_stats: { active: 1, archived: 0, total: 1 },
      },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Clear filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/sources",
      { per_page: 18 },
      { preserveScroll: true },
    );
  });

  it("preserves source filters during pagination", async () => {
    render(SourcesIndex, {
      props: {
        organization,
        filters: activeFilters,
        pagination,
        providers: [genericProvider],
        source_filter_options: filterOptions,
        sources: [activeSource],
        source_stats: { active: 20, archived: 0, total: 20 },
      },
    });

    await fireEvent.click(screen.getAllByText("→")[0]);

    expect(router.get).toHaveBeenCalledWith(
      "/organization/acme/sources",
      {
        search: "acme",
        provider: ["generic_git"],
        visibility: ["private"],
        status: ["active"],
        sync_status: ["success"],
        per_page: 18,
        page: 2,
      },
      { preserveScroll: true },
    );
  });

  it("passes current source list query to details links", () => {
    render(SourcesIndex, {
      props: {
        organization,
        filters: { ...activeFilters, page: 2 },
        pagination: { ...pagination, current_page: 2 },
        providers: [genericProvider],
        source_filter_options: filterOptions,
        sources: [activeSource],
        source_stats: { active: 20, archived: 0, total: 20 },
      },
    });

    const href =
      screen.getByRole("link", { name: "acme/app" }).getAttribute("href") ?? "";
    const returnTo = new URLSearchParams(href.split("?")[1] ?? "").get(
      "return_to",
    );

    expect(returnTo).toBe(
      "/organization/acme/sources?search=acme&provider[]=generic_git&visibility[]=private&status[]=active&sync_status[]=success&per_page=18&page=2",
    );
  });
});
