import { fireEvent, render, screen, within } from "@testing-library/svelte";
import { tick } from "svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import AdminDashboard from "./Dashboard.svelte";

vi.mock("@/Layouts/AdminShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const org = { id: "o1", name: "Acme", slug: "acme", status: "active" };
const failed = (n: number) => ({
  id: `s${n}`,
  name: `Source ${n}`,
  organization: org,
  last_failed_sync_at: "2026-11-01T10:00:00Z",
  last_sync_error: `boom ${n}`,
});

describe("Admin dashboard page", () => {
  it("links stat tiles to sections", () => {
    render(AdminDashboard, {
      props: {
        stats: {
          users_count: 7,
          admins_count: 2,
          organizations_count: 3,
          packages_count: 4,
          sources_count: 5,
        },
      },
    });

    const href = (label: string) =>
      screen.getByText(label).closest("a")?.getAttribute("href");
    expect(href("Users")).toBe("/admin/users");
    expect(href("Admins")).toBe("/admin/users");
    expect(href("Organizations")).toBe("/admin/organizations");
    expect(href("Packages")).toBe("/admin/packages");
    expect(href("Sources")).toBe("/admin/sources");
  });

  it("shows failed syncs", () => {
    render(AdminDashboard, {
      props: {
        failed_sources: [failed(1), failed(2)],
        failed_sources_count: 12,
      },
    });

    const card = screen.getByRole("region", { name: "Failed syncs" });
    expect(within(card).getByText("Source 1")).toBeInTheDocument();
    expect(within(card).getByText("Source 2")).toBeInTheDocument();
    expect(within(card).getAllByText("acme").length).toBe(2);
    expect(within(card).getByText("boom 1")).toHaveAttribute("title", "boom 1");
    expect(within(card).getByText("12")).toBeInTheDocument();
    expect(
      within(card).getByRole("link", { name: "All failed" }),
    ).toHaveAttribute("href", "/admin/sources?sync_status=failed");
  });

  it("shows calm state without failed syncs", () => {
    render(AdminDashboard);

    expect(
      screen.getByText("All sources synced successfully."),
    ).toBeInTheDocument();
  });

  it("lists recent events", () => {
    render(AdminDashboard, {
      props: {
        recent_events: [
          {
            id: "e1",
            event: "user.banned",
            occurred_at: "2026-11-01T10:00:00Z",
            actor_name: "Root Admin",
            organization: org,
          },
          {
            id: "e2",
            event: "system.tick",
            occurred_at: "2026-11-01T11:00:00Z",
            actor_name: "System",
            organization: null,
          },
        ],
      },
    });

    const card = screen.getByRole("region", { name: "Recent events" });
    expect(within(card).getAllByText("user.banned").length).toBeGreaterThan(0);
    expect(within(card).getAllByText("Root Admin").length).toBeGreaterThan(0);
    expect(within(card).getAllByText("acme").length).toBeGreaterThan(0);
    expect(within(card).getAllByText("—").length).toBeGreaterThan(0);
  });

  it("shows empty events state", () => {
    render(AdminDashboard);

    expect(screen.getByText("No events yet.")).toBeInTheDocument();
  });

  it("refresh reloads the page", async () => {
    router.reload.mockClear();
    render(AdminDashboard);

    const button = screen.getByRole("button", { name: "Refresh" });
    await fireEvent.click(button);

    expect(router.reload).toHaveBeenCalledTimes(1);
    const options = router.reload.mock.calls[0]?.[0] as {
      onStart: () => void;
      onFinish: () => void;
    };
    options.onStart();
    await tick();
    expect(button).toBeDisabled();
    options.onFinish();
    await tick();
    expect(button).toBeEnabled();
  });

  it("renders queue health with all jobs link", () => {
    render(AdminDashboard);

    expect(screen.getByRole("link", { name: "All jobs" })).toHaveAttribute(
      "href",
      "/admin/jobs",
    );
  });
});
