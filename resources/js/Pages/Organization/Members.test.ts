import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import Members from "./Members.svelte";
import type { OrganizationInvitationListItem } from "@/types/pages";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const organization = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  anonymous_access: false,
};

const invitations: OrganizationInvitationListItem[] = [
  {
    accept_url: "https://registry.test/invitations/inv-1",
    expires_at: "2026-09-01T00:00:00+00:00",
    id: "inv-1",
    invited_by: "admin@example.test",
    role: "member",
    status: "pending",
  },
  {
    accept_url: "https://registry.test/invitations/inv-2",
    expires_at: "2026-09-01T00:00:00+00:00",
    id: "inv-2",
    invited_by: "admin@example.test",
    role: "member",
    status: "accepted",
  },
];

describe("Organization members page", () => {
  it("asks for confirmation before revoking a pending invite", async () => {
    render(Members, {
      props: {
        organization,
        members: [],
        can_manage_members: true,
        invitations,
        owners_count: 0,
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: "Revoke" }));
    expect(screen.getByText("Revoke invite?")).toBeInTheDocument();

    await fireEvent.click(
      screen.getByRole("button", { name: "Revoke invite" }),
    );
    expect(router.post).toHaveBeenCalledWith(
      "/organization/acme/invitations/inv-1/revoke",
      {},
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("asks for confirmation before deleting a non-pending invite", async () => {
    render(Members, {
      props: {
        organization,
        members: [],
        can_manage_members: true,
        invitations,
        owners_count: 0,
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: "Delete" }));
    expect(screen.getByText("Delete invite?")).toBeInTheDocument();

    await fireEvent.click(
      screen.getByRole("button", { name: "Delete invite" }),
    );
    expect(router.post).toHaveBeenCalledWith(
      "/organization/acme/invitations/inv-2/delete",
      {},
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("hides invites from members", () => {
    render(Members, {
      props: {
        organization,
        members: [],
        can_manage_members: false,
        invitations: [],
        owners_count: 0,
      },
    });

    expect(screen.queryByRole("button", { name: "Create invite" })).toBeNull();
  });

  it("warns about shared registry tokens before removing a member", async () => {
    render(Members, {
      props: {
        organization,
        members: [
          {
            can_demote: false,
            can_promote: false,
            can_remove: true,
            can_update_role: false,
            display_name: "Bob",
            email: "bob@example.test",
            id: "m-1",
            is_current_user: false,
            role: "member",
          },
        ],
        can_manage_members: true,
        invitations: [],
        owners_count: 1,
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: "Remove" }));
    expect(screen.getByText(/reissue registry tokens/i)).toBeInTheDocument();
  });

  it("lists an invitation without a link when it has no accept_url", () => {
    render(Members, {
      props: {
        organization,
        members: [],
        can_manage_members: true,
        invitations: [
          {
            expires_at: "2026-09-01T00:00:00+00:00",
            id: "inv-3",
            invited_by: "admin@example.test",
            role: "member",
            status: "pending",
          },
        ],
        owners_count: 0,
      },
    });

    expect(screen.getByRole("button", { name: "Revoke" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Copy link" })).toBeNull();
  });
});
