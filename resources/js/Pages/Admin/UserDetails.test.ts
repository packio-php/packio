import { fireEvent, render, screen } from "@testing-library/svelte";
import { tick } from "svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import AdminUserDetails from "./UserDetails.svelte";

vi.mock("@/Layouts/AdminShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const user = {
  ban_reason: "Violation of project rules.",
  banned_at: "2026-08-22T10:00:00+00:00",
  banned_by_user_id: "admin-1",
  created_at: "2026-08-01T10:00:00+00:00",
  display_name: "Blocked User",
  email: "blocked@example.test",
  id: "user-1",
  is_admin: false,
  is_banned: true,
  roles: ["user"],
  updated_at: "2026-08-22T10:00:00+00:00",
};

describe("Admin user details page", () => {
  it("shows moderation status and history", () => {
    render(AdminUserDetails, {
      props: {
        user_record: user,
        ban_history: [
          {
            action: "identity.user.banned",
            actor_id: "admin-1",
            id: "audit-1",
            occurred_at: "2026-08-22T10:00:00+00:00",
            reason: "Violation of project rules.",
          },
        ],
      },
    });

    expect(screen.getByText("Lift ban")).toBeInTheDocument();
    expect(screen.getByText("Moderation history")).toBeInTheDocument();
    expect(screen.getByText("Administrator (admin-1)")).toBeInTheDocument();
  });

  it("requires a confirmation note before submitting an unban", async () => {
    render(AdminUserDetails, { props: { user_record: user } });

    await fireEvent.click(screen.getByRole("button", { name: "Lift ban" }));
    await fireEvent.click(
      screen.getAllByRole("button", { name: "Lift ban" })[1],
    );

    expect(router.post).toHaveBeenCalledWith(
      "/admin/users/user-1/unban",
      { note: "Ban lifted by administrator." },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("closes the confirmation modal when the success flash matches", async () => {
    render(AdminUserDetails, { props: { user_record: user } });

    await fireEvent.click(screen.getByRole("button", { name: "Lift ban" }));
    await fireEvent.click(
      screen.getAllByRole("button", { name: "Lift ban" })[1],
    );

    const options = router.post.mock.calls[0][2];
    expect(options).toHaveProperty("onSuccess");

    options.onSuccess({
      flash: { success: "User unbanned." },
    });
    await tick();

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("keeps the confirmation modal open when the flash does not match", async () => {
    render(AdminUserDetails, { props: { user_record: user } });

    await fireEvent.click(screen.getByRole("button", { name: "Lift ban" }));
    await fireEvent.click(
      screen.getAllByRole("button", { name: "Lift ban" })[1],
    );

    const options = router.post.mock.calls[0][2];
    options.onSuccess({
      flash: { error: "User is not banned." },
    });
    await tick();

    expect(screen.getByRole("dialog")).toBeInTheDocument();
  });

  it("disables the self-ban action", () => {
    render(AdminUserDetails, {
      props: {
        auth: {
          user: {
            id: "user-1",
            display_name: "Blocked User",
            email: "blocked@example.test",
            roles: ["admin"],
          },
        },
        user_record: {
          ...user,
          can_ban: false,
          is_banned: false,
          moderation_hint: "You cannot ban your own account.",
        },
      },
    });

    expect(screen.getByRole("button", { name: "Ban user" })).toBeDisabled();
    expect(
      screen.getByText("You cannot ban your own account."),
    ).toBeInTheDocument();
  });
});
