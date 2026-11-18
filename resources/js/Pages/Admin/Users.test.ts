import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import AdminUsers from "./Users.svelte";

vi.mock("@/Layouts/AdminShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Admin users page", () => {
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
  const filters = { page: 1, per_page: 20, search: "", status: "" as const };
  const users = [
    {
      created_at: "2026-08-22T10:00:00+00:00",
      display_name: "Banned User",
      email: "banned@example.test",
      id: "user-banned",
      is_admin: false,
      is_banned: true,
      roles: ["user"],
      updated_at: "2026-08-22T10:00:00+00:00",
    },
    {
      created_at: "2026-08-22T10:00:00+00:00",
      display_name: "Active User",
      email: "active@example.test",
      id: "user-active",
      is_admin: false,
      is_banned: false,
      roles: ["user"],
      updated_at: "2026-08-22T10:00:00+00:00",
    },
  ];

  it("shows the current account status in the user list", () => {
    render(AdminUsers, {
      props: {
        filters,
        pagination,
        users,
      },
    });

    expect(screen.getAllByText("Banned")).not.toHaveLength(0);
    expect(screen.getAllByText("Active")).not.toHaveLength(0);
  });

  it("keeps status and verification column headers aligned with their values", () => {
    render(AdminUsers, {
      props: {
        filters,
        pagination,
        users: [
          {
            created_at: "2026-08-22T10:00:00+00:00",
            display_name: "Banned User",
            email: "banned@example.test",
            email_verified: true,
            id: "user-banned",
            is_admin: false,
            is_banned: true,
            roles: ["user"],
            updated_at: "2026-08-22T10:00:00+00:00",
          },
        ],
      },
    });

    const headers = screen
      .getAllByRole("columnheader")
      .map((header) => header.textContent);
    expect(headers.indexOf("Status")).toBeLessThan(headers.indexOf("Verified"));
  });

  it("renders stat tiles", () => {
    render(AdminUsers, {
      props: {
        filters,
        pagination,
        stats: { admins_count: 3, users_count: 12 },
        users,
      },
    });

    expect(screen.getByText("12")).toBeInTheDocument();
    expect(screen.getByText("3")).toBeInTheDocument();
    expect(screen.getAllByText("Admins").length).toBeGreaterThan(0);
  });

  it("filters are collapsed by default and apply on submit", async () => {
    render(AdminUsers, { props: { filters, pagination, users } });

    expect(screen.queryByLabelText("Account status")).not.toBeInTheDocument();
    await fireEvent.click(screen.getByRole("button", { name: "Show filters" }));
    await fireEvent.change(screen.getByLabelText("Account status"), {
      target: { value: "banned" },
    });
    expect(router.get).not.toHaveBeenCalled();

    await fireEvent.input(screen.getByLabelText("Search"), {
      target: { value: "active@example.test" },
    });
    await fireEvent.click(
      screen.getByRole("button", { name: "Apply filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      "/admin/users",
      {
        page: 1,
        per_page: 20,
        search: "active@example.test",
        status: "banned",
      },
      { preserveScroll: true },
    );
  });

  it("uses badges for status, verification and roles", () => {
    render(AdminUsers, {
      props: {
        filters,
        pagination,
        users: [
          { ...users[0], email_verified: true, roles: ["admin"] },
          { ...users[1], email_verified: false },
        ],
      },
    });

    expect(screen.getAllByText("Banned")[0]).toHaveClass("ui-badge-danger");
    expect(screen.getAllByText("Active")[0]).toHaveClass("ui-badge-accent");
    expect(
      screen.getAllByText("Verified", { selector: "span" })[0],
    ).toHaveClass("ui-badge-accent");
    expect(screen.getAllByText("Unverified")[0]).toHaveClass("ui-badge-gold");
    expect(screen.getAllByText("admin")[0]).toHaveClass("ui-badge-gold");
    expect(screen.getAllByText("user")[0]).toHaveClass("ui-badge-muted");
  });

  it("clear resets filters", async () => {
    render(AdminUsers, {
      props: {
        filters: { ...filters, search: "x", per_page: 50 },
        pagination: { ...pagination, per_page: 50 },
        users,
      },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Clear filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      "/admin/users",
      { per_page: 50 },
      { preserveScroll: true },
    );
  });

  it("keeps canonical filters while navigating pages", async () => {
    render(AdminUsers, {
      props: {
        filters: { ...filters, search: "example", status: "active" },
        pagination,
        users,
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: "2" }));

    expect(router.get).toHaveBeenCalledWith(
      "/admin/users",
      { ...filters, page: 2, search: "example", status: "active" },
      { preserveScroll: true },
    );
  });

  it("distinguishes an empty installation from a filtered empty result", () => {
    const { rerender } = render(AdminUsers, {
      props: {
        filters,
        pagination: { ...pagination, total: 0, to: 0 },
        users: [],
      },
    });

    expect(
      screen.getByText("No users have been created yet."),
    ).toBeInTheDocument();

    rerender({
      filters: { ...filters, search: "missing" },
      pagination: { ...pagination, total: 0, to: 0 },
      users: [],
    });

    expect(
      screen.getByText("No users match the current filters."),
    ).toBeInTheDocument();
  });
});
