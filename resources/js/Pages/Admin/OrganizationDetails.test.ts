import { fireEvent, render, screen } from "@testing-library/svelte";
import { tick } from "svelte";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import type { AdminOrganizationListItem } from "@/types/pages";
import OrganizationDetails from "./OrganizationDetails.svelte";

vi.mock("@/Layouts/AdminShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const organizationRecord: AdminOrganizationListItem = {
  anonymous_access: false,
  created_at: "2026-06-01T00:00:00+00:00",
  id: "org-1",
  invitations_count: 2,
  members: [
    {
      created_at: "2026-06-02T00:00:00+00:00",
      display_name: "Alice Admin",
      email: "alice@example.test",
      id: "member-1",
      role: "owner",
      user_id: "user-1",
    },
  ],
  members_count: 1,
  members_pagination: {
    current_page: 1,
    from: 1,
    has_more_pages: true,
    has_previous_pages: false,
    items: [],
    last_page: 2,
    per_page: 1,
    to: 1,
    total: 2,
  },
  name: "Acme",
  packages_count: 5,
  slug: "acme",
  sources_count: 3,
  status: "active" as const,
  updated_at: "2026-06-03T00:00:00+00:00",
};

describe("Admin organization details page", () => {
  it("renders read-only organization details and links members to admin users", () => {
    render(OrganizationDetails, {
      props: { organization_record: organizationRecord },
    });

    expect(screen.getByText("Acme")).toBeInTheDocument();
    expect(screen.getByText("/acme")).toBeInTheDocument();
    expect(screen.getByText("Alice Admin")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Alice Admin/ })).toHaveAttribute(
      "href",
      "/admin/users/user-1",
    );

    expect(
      screen.queryByRole("button", { name: "Save organization" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Delete organization" }),
    ).toBeInTheDocument();
  });

  it("shows dependency counts in the delete modal and requires the exact organization name", async () => {
    const user = userEvent.setup();
    render(OrganizationDetails, {
      props: { organization_record: organizationRecord },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Delete organization" }),
    );

    const dialog = screen.getByRole("dialog");
    expect(dialog).toHaveTextContent("Sources");
    expect(dialog).toHaveTextContent("3");
    expect(dialog).toHaveTextContent("5");
    expect(dialog).toHaveTextContent("Invitations");
    expect(dialog).toHaveTextContent("2");

    let confirmButton = screen.getAllByRole("button", {
      name: "Delete organization",
    })[1];
    expect(confirmButton).toBeDisabled();

    await user.type(screen.getByRole("textbox"), "not the name");
    confirmButton = screen.getAllByRole("button", {
      name: "Delete organization",
    })[1];
    expect(confirmButton).toBeDisabled();

    await user.clear(screen.getByRole("textbox"));
    await user.type(screen.getByRole("textbox"), "Acme");
    confirmButton = screen.getAllByRole("button", {
      name: "Delete organization",
    })[1];
    expect(confirmButton).toBeEnabled();
  });

  it("posts the delete action with the typed confirmation", async () => {
    const user = userEvent.setup();
    render(OrganizationDetails, {
      props: { organization_record: organizationRecord },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Delete organization" }),
    );
    await user.type(screen.getByRole("textbox"), "Acme");
    await fireEvent.click(
      screen.getAllByRole("button", { name: "Delete organization" })[1],
    );

    expect(router.post).toHaveBeenCalledWith(
      "/admin/organizations/org-1/delete",
      { confirmationName: "Acme" },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("closes the delete modal when the success flash matches", async () => {
    const user = userEvent.setup();
    render(OrganizationDetails, {
      props: { organization_record: organizationRecord },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Delete organization" }),
    );
    await user.type(screen.getByRole("textbox"), "Acme");
    await fireEvent.click(
      screen.getAllByRole("button", { name: "Delete organization" })[1],
    );

    const options = router.post.mock.calls[0][2];
    expect(options).toHaveProperty("onSuccess");

    options.onSuccess({ flash: { success: "Organization deletion queued." } });
    await tick();

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("disables delete initiation while the organization is already deleting", () => {
    render(OrganizationDetails, {
      props: {
        organization_record: { ...organizationRecord, status: "deleting" },
      },
    });

    expect(
      screen.getByRole("button", { name: "Delete organization" }),
    ).toBeDisabled();
    expect(screen.getByText(/already deleting/i)).toBeInTheDocument();
  });

  it("navigates member pages while preserving the bounded member page size", async () => {
    const user = userEvent.setup();
    render(OrganizationDetails, {
      props: { organization_record: organizationRecord },
    });

    await user.click(screen.getByRole("button", { name: "2" }));

    expect(router.get).toHaveBeenCalledWith(
      "/admin/organizations/org-1",
      { members_page: 2, members_per_page: 1 },
      { preserveScroll: true },
    );
  });
});
