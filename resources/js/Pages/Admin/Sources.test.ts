import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
  within,
} from "@testing-library/svelte";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import AdminSources from "./Sources.svelte";

vi.mock("@/Layouts/AdminShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const organization = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  status: "active",
};

const pagination = {
  current_page: 1,
  from: 1,
  has_more_pages: false,
  has_previous_pages: false,
  last_page: 1,
  per_page: 20,
  to: 2,
  total: 2,
};

const filters = {
  archived_from: "",
  archived_to: "",
  created_from: "",
  created_to: "",
  default_ref: "",
  delete_health: ["stale_without_job"] as string[],
  direction: "desc" as const,
  organization_id: "",
  owner: "",
  page: 1,
  per_page: 20,
  provider: [] as string[],
  repository: "",
  search: "",
  sort: "created_at",
  source_id: "",
  status: [] as string[],
  sync_status: [] as string[],
  url: "",
  visibility: [] as string[],
};

const missingJobSource = {
  active: false,
  archived: false,
  archived_at: "",
  canonical_url: "https://example.com/acme/missing.git",
  created_at: "2026-06-01T00:00:00+00:00",
  default_ref: "main",
  id: "source-missing",
  keep_last_releases: 0,
  last_failed_sync_at: "",
  last_successful_sync_at: "",
  last_sync_error: "",
  last_sync_status: "pending",
  logical_status: "deleting",
  name: "acme/missing",
  organization,
  owner: "acme",
  provider: "github",
  provider_label: "GitHub",
  recovery: {
    active_jobs_count: 0,
    can_cancel: true,
    can_force_finalize: true,
    can_force_requeue: true,
    can_requeue: true,
    health: "stale_without_job",
    latest_job_created_at: null,
    latest_job_error: null,
    latest_job_id: null,
    latest_job_status: null,
    linked_packages_count: 1,
    linked_releases_count: 0,
    organization_id: "org-1",
    related_jobs: [],
    related_jobs_count: 0,
    source_id: "source-missing",
    webhook_external_id_present: false,
  },
  repository: "missing",
  status: "deleting",
  updated_at: "2026-06-02T00:00:00+00:00",
  url: "https://example.com/acme/missing.git",
  visibility: "private",
};

const failedSource = {
  ...missingJobSource,
  canonical_url: "https://example.com/acme/failed.git",
  id: "source-failed",
  name: "acme/failed",
  recovery: {
    ...missingJobSource.recovery,
    health: "failed",
    latest_job_created_at: "2026-06-02T00:00:00+00:00",
    latest_job_error: "cleanup failed",
    latest_job_id: "job-1",
    latest_job_status: "failed",
    related_jobs: [
      {
        completed_at: "2026-06-02T00:01:00+00:00",
        created_at: "2026-06-02T00:00:00+00:00",
        deletion_run_id: null,
        error_message: "cleanup failed",
        id: "job-1",
        idempotency_key: "source-delete:source-failed",
        started_at: "2026-06-02T00:00:30+00:00",
        status: "failed",
      },
    ],
    related_jobs_count: 1,
    source_id: "source-failed",
  },
  repository: "failed",
  url: "https://example.com/acme/failed.git",
};

const activeSource = {
  ...missingJobSource,
  active: true,
  canonical_url: "https://example.com/acme/active.git",
  id: "source-active",
  last_sync_status: "success",
  logical_status: "active",
  name: "acme/active",
  recovery: null,
  repository: "active",
  status: "active",
  url: "https://example.com/acme/active.git",
  visibility: "public",
};

function renderPage(): void {
  render(AdminSources, {
    props: {
      filter_options: {
        providers: [{ label: "GitHub", value: "github" }],
        sync_statuses: [{ label: "pending", value: "pending" }],
      },
      filters,
      initial_list_view_mode: "table",
      organizations: [organization],
      pagination,
      source_stats: {
        active: 0,
        archived: 0,
        deleting: 2,
        deleting_failed: 1,
        deleting_in_progress: 0,
        deleting_missing_jobs: 1,
        inactive: 0,
        private: 2,
        public: 0,
        total: 2,
      },
      sources: [missingJobSource, failedSource],
    },
  });
}

function renderDeletePage(): void {
  render(AdminSources, {
    props: {
      filter_options: {
        providers: [{ label: "GitHub", value: "github" }],
        sync_statuses: [],
      },
      filters,
      initial_list_view_mode: "table",
      organizations: [organization],
      pagination,
      source_stats: {
        active: 1,
        archived: 0,
        deleting: 0,
        deleting_failed: 0,
        deleting_in_progress: 0,
        deleting_missing_jobs: 0,
        inactive: 0,
        private: 0,
        public: 1,
        total: 1,
      },
      sources: [activeSource],
    },
  });
}

const deletionPreview = {
  source: {
    id: "source-active",
    name: "acme/active",
    provider: "github",
    organization_id: "org-1",
  },
  packages_count: 2,
  releases_count: 3,
};

function stubPreviewFetch(): void {
  vi.stubGlobal(
    "fetch",
    vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: { get: () => "application/json" },
      json: async () => deletionPreview,
    }),
  );
}

describe("Admin sources page", () => {
  it("renders source delete recovery state and links to filtered jobs", () => {
    renderPage();

    expect(screen.getByText("delete job missing")).toBeInTheDocument();
    expect(screen.getByText("delete failed")).toBeInTheDocument();

    const jobsLink = screen.getByRole("link", { name: "Jobs" });
    expect(jobsLink).toHaveAttribute(
      "href",
      "/admin/jobs?organization_id=org-1&type[]=source.delete&source_id=source-failed",
    );
  });

  it("posts a source delete requeue action with the current admin sources return URL", async () => {
    renderPage();

    await fireEvent.click(
      screen.getAllByRole("button", { name: "Requeue delete" })[0],
    );
    await fireEvent.click(
      within(screen.getByRole("dialog")).getByRole("button", {
        name: "Requeue delete",
      }),
    );

    expect(router.post).toHaveBeenCalledWith(
      expect.stringContaining("/admin/sources/source-missing/requeue-delete"),
      {},
      expect.objectContaining({
        preserveScroll: true,
      }),
    );

    const url = router.post.mock.calls[0][0] as string;
    expect(decodeURIComponent(url)).toContain(
      "return_to=/admin/sources?delete_health[]=stale_without_job",
    );
  });

  it("requires typed confirmation before force finalizing source deletion", async () => {
    const user = userEvent.setup();
    renderPage();

    await fireEvent.click(
      screen.getAllByRole("button", { name: "Recovery details" })[0],
    );
    await fireEvent.click(
      within(screen.getByRole("dialog")).getByRole("button", {
        name: "Force finalize",
      }),
    );

    const dialog = screen.getByRole("dialog");
    let confirmButton = within(dialog).getByRole("button", {
      name: "Force finalize",
    });
    expect(confirmButton).toBeDisabled();

    await user.type(within(dialog).getByRole("textbox"), "FORCE DELETE SOURCE");
    await user.click(
      within(dialog).getByLabelText(
        "Continue if archive cleanup cannot be fully scheduled",
      ),
    );

    await waitFor(() => {
      confirmButton = within(screen.getByRole("dialog")).getByRole("button", {
        name: "Force finalize",
      });
      expect(confirmButton).toBeEnabled();
    });
    await fireEvent.click(confirmButton);

    expect(router.post).toHaveBeenCalledWith(
      expect.stringContaining(
        "/admin/sources/source-missing/force-finalize-delete",
      ),
      {
        acknowledge_orphaned_archives: "1",
        confirmation: "FORCE DELETE SOURCE",
      },
      expect.objectContaining({
        preserveScroll: true,
      }),
    );
  });

  it("shows a delete action only for sources that are not deleting", () => {
    renderDeletePage();

    expect(
      screen.getByRole("button", { name: "Delete source" }),
    ).toBeInTheDocument();

    cleanup();
    renderPage();

    expect(
      screen.queryByRole("button", { name: "Delete source" }),
    ).not.toBeInTheDocument();
  });

  it("loads the deletion preview, shows counts, and requires the exact source name", async () => {
    const user = userEvent.setup();
    stubPreviewFetch();
    renderDeletePage();

    await fireEvent.click(
      screen.getByRole("button", { name: "Delete source" }),
    );

    const dialog = screen.getByRole("dialog");
    expect(await within(dialog).findByText("2")).toBeInTheDocument();
    expect(await within(dialog).findByText("3")).toBeInTheDocument();

    let confirmButton = within(dialog).getByRole("button", {
      name: "Delete source",
    });
    expect(confirmButton).toBeDisabled();

    await user.type(within(dialog).getByRole("textbox"), "wrong name");
    confirmButton = within(screen.getByRole("dialog")).getByRole("button", {
      name: "Delete source",
    });
    expect(confirmButton).toBeDisabled();

    await user.clear(within(screen.getByRole("dialog")).getByRole("textbox"));
    await user.type(
      within(screen.getByRole("dialog")).getByRole("textbox"),
      "acme/active",
    );
    await waitFor(() => {
      confirmButton = within(screen.getByRole("dialog")).getByRole("button", {
        name: "Delete source",
      });
      expect(confirmButton).toBeEnabled();
    });
    await fireEvent.click(confirmButton);

    expect(router.post).toHaveBeenCalledWith(
      expect.stringContaining("/admin/sources/source-active/delete"),
      { confirmationName: "acme/active" },
      expect.objectContaining({
        preserveScroll: true,
      }),
    );
  });
});
