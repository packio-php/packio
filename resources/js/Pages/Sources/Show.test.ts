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
});
