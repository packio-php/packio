import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@inertiajs/svelte";
import { setPageUrl } from "@/test/inertia";
import SourcesShow from "./Show.svelte";
import type { AuthPayload } from "@/types/auth";
import type { OrganizationSummary } from "@/types/organization";
import type { AuditEntryPayload, SourceDetailsPayload } from "@/types/pages";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const organization: OrganizationSummary = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  anonymous_access: true,
};

const source: SourceDetailsPayload = {
  active: true,
  archived: false,
  created_at: "2026-01-01T00:00:00+00:00",
  default_ref: "main",
  id: "source-1",
  keep_last_releases: 0,
  last_sync_status: "success",
  name: "acme/app",
  packages_active: true,
  provider: "github",
  provider_label: "GitHub",
  status: "active",
  type: "git",
  url: "https://github.com/acme/app",
  visibility: "public",
};

function renderPage(auth: AuthPayload, audit: AuditEntryPayload[] = []): void {
  render(SourcesShow, {
    props: {
      audit,
      auth,
      organization,
      packages: [],
      source,
      stats: {},
      webhook_events: [],
    },
  });
}

describe("Source details page", () => {
  it("submits explicit activity for both checked and unchecked settings", async () => {
    render(SourcesShow, {
      props: { source, organization, can_manage_sources: true },
    });
    const checkbox = screen.getByRole("checkbox", { name: /Source is active/ });
    expect(
      screen.getByText(/Older versions are hidden from the registry/),
    ).toHaveTextContent("Dev versions do not count toward the limit.");
    expect(screen.getByText(/0 disables the limit/)).toHaveTextContent(
      "previously hidden versions are not automatically restored.",
    );
    const form = checkbox.closest("form")!;
    await fireEvent.submit(form);
    expect(router.post).toHaveBeenLastCalledWith(
      expect.stringContaining("/sources/source-1/settings"),
      expect.objectContaining({ active: "on" }),
      expect.anything(),
    );
    await fireEvent.click(checkbox);
    await fireEvent.submit(form);
    expect(router.post).toHaveBeenLastCalledWith(
      expect.stringContaining("/sources/source-1/settings"),
      expect.objectContaining({ active: "0" }),
      expect.anything(),
    );
  });

  it("renders every audit entry with a shared event and timestamp", () => {
    setPageUrl("/organization/acme/sources/source-1");

    renderPage(
      {
        check: true,
        user: {
          display_name: "Member",
          email: "member@example.test",
          id: "user-1",
          is_admin: false,
          roles: ["user"],
        },
      },
      [
        {
          event: "publication.github.completed",
          id: "9711fc02-afaa-4dc4-9d17-1874df9f7f01",
          occurred_at: "2026-07-04T10:52:27+00:00",
        },
        {
          event: "publication.github.completed",
          id: "9711fc02-afaa-4dc4-9d17-1874df9f7f02",
          occurred_at: "2026-07-04T10:52:27+00:00",
        },
        {
          event: "publication.github.completed",
          id: "9711fc02-afaa-4dc4-9d17-1874df9f7f03",
          occurred_at: "2026-07-04T10:52:27+00:00",
        },
      ],
    );

    expect(screen.getAllByText("publication.github.completed")).toHaveLength(3);
  });

  it("does not reflect admin source return URLs for non-admin users", () => {
    setPageUrl(
      "/organization/acme/sources/source-1?return_to=%2Fadmin%2Fsources%3Fprovider%5B%5D%3Dgithub",
    );

    renderPage({
      check: true,
      user: {
        display_name: "Member",
        email: "member@example.test",
        id: "user-1",
        is_admin: false,
        roles: ["user"],
      },
    });

    expect(
      screen.getByRole("link", { name: "← Back to sources" }),
    ).toHaveAttribute("href", "/organization/acme/sources");
  });

  it("keeps admin source return URLs for admin users", () => {
    setPageUrl(
      "/organization/acme/sources/source-1?return_to=%2Fadmin%2Fsources%3Fprovider%5B%5D%3Dgithub%26page%3D2",
    );

    renderPage({
      check: true,
      user: {
        display_name: "Admin",
        email: "admin@example.test",
        id: "admin-1",
        is_admin: true,
        roles: ["admin"],
      },
    });

    expect(
      screen.getByRole("link", { name: "← Back to sources" }),
    ).toHaveAttribute("href", "/admin/sources?provider[]=github&page=2");
  });

  describe("summary and re-sync", () => {
    function renderSummary(overrides: Partial<SourceDetailsPayload> = {}) {
      return render(SourcesShow, {
        props: {
          can_manage_sources: true,
          organization,
          source: { ...source, ...overrides },
          stats: { deliveries_count: 4, packages_count: 2, releases_count: 7 },
        },
      });
    }

    it("shows name, link, badges, explicit dates and counts", () => {
      renderSummary({
        last_failed_sync_at: "2026-02-02T00:00:00+00:00",
        last_successful_sync_at: "2026-03-03T00:00:00+00:00",
      });

      expect(
        screen.getByRole("heading", { name: "acme/app" }),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("link", { name: "https://github.com/acme/app" }),
      ).toHaveAttribute("href", "https://github.com/acme/app");
      expect(screen.getByText("GitHub")).toBeInTheDocument();
      expect(screen.getByText("Active")).toBeInTheDocument();
      expect(screen.getByText("Synced")).toBeInTheDocument();
      expect(screen.getByText("Last success:")).toBeInTheDocument();
      expect(screen.getByText("Last failure:")).toBeInTheDocument();
      expect(screen.getByText("2 packages · 7 releases")).toBeInTheDocument();
      expect(screen.getByText(/Recent deliveries \(4\)/)).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: "Re-sync source" }),
      ).toBeEnabled();
    });

    it("disables re-sync with a reason for inactive and archived sources", () => {
      const { unmount } = renderSummary({ active: false });
      expect(
        screen.getByRole("button", { name: "Re-sync source" }),
      ).toBeDisabled();
      expect(
        screen.getByText(
          "Source is inactive. Enable it before running a manual re-sync.",
        ),
      ).toBeInTheDocument();
      unmount();

      renderSummary({ active: false, archived: true });
      expect(screen.getByText("Archived")).toBeInTheDocument();
      expect(
        screen.getByText(
          "Source is archived. Restore it before running a manual re-sync.",
        ),
      ).toBeInTheDocument();
    });

    it("shows queued and running state with a disabled button", () => {
      const { unmount } = renderSummary({ sync_job_status: "pending" });
      expect(
        screen.getByRole("button", { name: "Sync queued…" }),
      ).toBeDisabled();
      expect(screen.getByText("Sync queued")).toBeInTheDocument();
      unmount();

      renderSummary({ sync_job_status: "running" });
      expect(screen.getByRole("button", { name: "Syncing…" })).toBeDisabled();
      expect(screen.getByText("Sync running")).toBeInTheDocument();
    });

    it("polls while a sync job is active and stops when it is done", async () => {
      vi.useFakeTimers();
      vi.mocked(router.reload).mockImplementation((options) => {
        options?.onFinish?.({} as never);
      });
      const { rerender } = renderSummary({ sync_job_status: "pending" });

      expect(router.reload).not.toHaveBeenCalled();
      vi.advanceTimersByTime(5000);
      expect(router.reload).toHaveBeenCalledTimes(1);
      expect(router.reload).toHaveBeenLastCalledWith(
        expect.objectContaining({
          only: ["source", "stats", "packages", "webhook_events", "audit"],
        }),
      );
      vi.advanceTimersByTime(5000);
      expect(router.reload).toHaveBeenCalledTimes(2);

      await rerender({ source: { ...source, sync_job_status: null } });
      vi.advanceTimersByTime(15000);
      expect(router.reload).toHaveBeenCalledTimes(2);
    });

    it("does not overlap reloads", () => {
      vi.useFakeTimers();
      // A reload that never finishes must block the next tick.
      vi.mocked(router.reload).mockImplementation(() => {});
      renderSummary({ sync_job_status: "running" });

      vi.advanceTimersByTime(15000);
      expect(router.reload).toHaveBeenCalledTimes(1);
    });

    it("does not poll without an active sync job", () => {
      vi.useFakeTimers();
      renderSummary();

      vi.advanceTimersByTime(15000);
      expect(router.reload).not.toHaveBeenCalled();
    });

    it("links to provider access after a failed sync that needs reauthorization", () => {
      renderSummary({
        credentials: {
          auth_health: "reauthorization_required",
          configured: true,
        },
        last_sync_error: "Bad credentials",
        last_sync_status: "failed",
      });

      expect(screen.getByText("Failed")).toBeInTheDocument();
      expect(screen.getByText(/Bad credentials/)).toBeInTheDocument();
      expect(
        screen.getByRole("link", { name: "Manage provider access" }),
      ).toHaveAttribute("href", "/organization/acme/sources?manage_access=1");
    });
  });
});
