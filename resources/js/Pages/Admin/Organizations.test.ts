import { fireEvent, render, screen, within } from "@testing-library/svelte";
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

  const filters = { search: "acme" };
  const stats = { organizations_count: 42 };

  it("renders organizations table", () => {
    render(AdminOrganizations, {
      props: { filters, organizations, pagination, stats },
    });

    const table = screen.getByRole("table", { name: "Admin organizations" });
    for (const name of [
      "Name",
      "Members",
      "Invitations",
      "Status",
      "Created",
    ]) {
      expect(
        within(table).getByRole("columnheader", { name }),
      ).toBeInTheDocument();
    }
    expect(within(table).getByRole("link", { name: "Acme" })).toHaveAttribute(
      "href",
      "/admin/organizations/org-acme",
    );
    expect(screen.queryByRole("link", { name: "View" })).toBeNull();
    expect(screen.getByText("42")).toBeInTheDocument();
  });

  it("shows deleting badge", () => {
    render(AdminOrganizations, { props: { organizations, pagination } });

    expect(screen.getAllByText("deleting").length).toBeGreaterThan(0);
    expect(screen.queryAllByText("active")).toHaveLength(0);
  });

  it("submits search", async () => {
    render(AdminOrganizations, { props: { organizations, pagination } });

    await fireEvent.click(screen.getByRole("button", { name: "Show filters" }));
    await fireEvent.input(screen.getByLabelText("Search"), {
      target: { value: "acme" },
    });
    await fireEvent.click(
      screen.getByRole("button", { name: "Apply filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      "/admin/organizations",
      { page: 1, per_page: 20, search: "acme" },
      { preserveScroll: true },
    );
  });

  it("keeps search when paging", async () => {
    render(AdminOrganizations, {
      props: { filters, organizations, pagination },
    });

    await fireEvent.click(screen.getAllByRole("button", { name: "Page 2" })[0]);

    expect(router.get).toHaveBeenCalledWith(
      "/admin/organizations",
      { page: 2, per_page: 20, search: "acme" },
      { preserveScroll: true },
    );
  });

  it("resets to the first page when the page size changes", async () => {
    render(AdminOrganizations, {
      props: { filters, organizations, pagination },
    });

    expect(screen.getAllByText("Per page:")).toHaveLength(2);
    await fireEvent.change(screen.getAllByDisplayValue("20")[0], {
      target: { value: "50" },
    });

    expect(router.get).toHaveBeenCalledWith(
      "/admin/organizations",
      { page: 1, per_page: 50, search: "acme" },
      { preserveScroll: true },
    );
  });

  it("shows an empty state", () => {
    render(AdminOrganizations, { props: { organizations: [] } });

    expect(screen.getByText("No organizations found.")).toBeInTheDocument();
  });
});
