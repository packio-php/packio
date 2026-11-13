import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import AdminOrganizations from "./Organizations.svelte";

vi.mock("@/Layouts/AdminShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Admin organizations page", () => {
  const organizations = [
    {
      anonymous_access: false,
      id: "org-acme",
      invitations_count: 1,
      members_count: 3,
      name: "Acme",
      slug: "acme",
      status: "active" as const,
    },
    {
      anonymous_access: false,
      id: "org-old",
      invitations_count: 0,
      members_count: 1,
      name: "Old Org",
      slug: "old-org",
      status: "deleting" as const,
    },
  ];
  const pagination = {
    current_page: 1,
    from: 1,
    has_more_pages: true,
    has_previous_pages: false,
    last_page: 2,
    per_page: 20,
    to: 20,
    total: 21,
  };

  it("lists organizations and marks the one being deleted", () => {
    render(AdminOrganizations, { props: { organizations, pagination } });

    expect(screen.getByText("Acme")).toBeInTheDocument();
    expect(screen.getByText("3 members")).toBeInTheDocument();
    expect(screen.getAllByText("deleting")).toHaveLength(1);
    expect(screen.getAllByRole("link", { name: "View" })[0]).toHaveAttribute(
      "href",
      "/admin/organizations/org-acme",
    );
  });

  it("requests another page from the server", async () => {
    render(AdminOrganizations, { props: { organizations, pagination } });

    await fireEvent.click(screen.getByRole("button", { name: "2" }));

    expect(router.get).toHaveBeenCalledWith(
      "/admin/organizations",
      { page: 2, per_page: 20 },
      { preserveScroll: true },
    );
  });

  it("shows an empty state", () => {
    render(AdminOrganizations, { props: { organizations: [] } });

    expect(screen.getByText("No organizations found.")).toBeInTheDocument();
  });
});
