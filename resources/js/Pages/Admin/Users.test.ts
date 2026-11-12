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

  it("requests a server-side status filter from the first page", async () => {
    render(AdminUsers, { props: { filters, pagination, users } });

    await fireEvent.change(screen.getByLabelText("Account status"), {
      target: { value: "banned" },
    });

    expect(router.get).toHaveBeenCalledWith(
      "/admin/users",
      { ...filters, page: 1, status: "banned" },
      { preserveScroll: true },
    );
  });

  it("resets pagination when applying an email or display-name search", async () => {
    render(AdminUsers, {
      props: {
        filters: { ...filters, page: 2, per_page: 50, status: "active" },
        pagination: { ...pagination, current_page: 2, per_page: 50 },
        users,
      },
    });

    await fireEvent.input(screen.getByLabelText("Search users"), {
      target: { value: "active@example.test" },
    });
    const submitEvent = new Event("submit", {
      bubbles: true,
      cancelable: true,
    });
    await fireEvent(
      screen.getByRole("button", { name: "Apply" }).closest("form")!,
      submitEvent,
    );

    expect(submitEvent.defaultPrevented).toBe(true);
    expect(router.get).toHaveBeenCalledWith(
      "/admin/users",
      {
        page: 1,
        per_page: 50,
        search: "active@example.test",
        status: "active",
      },
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
