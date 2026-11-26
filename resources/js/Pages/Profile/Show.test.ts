import { fireEvent, render, screen } from "@testing-library/svelte";
import { tick } from "svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import ProfileShow from "./Show.svelte";
import type { ApiAccessTokenPayload, ProfilePayload } from "@/types/pages";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const profile: ProfilePayload = {
  id: "user-1",
  display_name: "Test User",
  email: "user@example.test",
  has_password: true,
  roles: ["user"],
  created_at: "2026-01-01T00:00:00Z",
  updated_at: null,
};

const revokedToken: ApiAccessTokenPayload = {
  id: "token-revoked",
  name: "Old automation",
  token_prefix: "packio_api_old",
  scopes: ["read"],
  created_at: "2026-01-02T00:00:00Z",
  last_used_at: null,
  expires_at: null,
  revoked: true,
  revoked_at: "2026-01-03T00:00:00Z",
};

describe("Profile show page", () => {
  it("deletes revoked API tokens through confirmation", async () => {
    render(ProfileShow, {
      props: {
        profile,
        api_tokens: [revokedToken],
      },
    });

    expect(screen.getByText("Revoked")).toBeInTheDocument();

    await fireEvent.click(screen.getByRole("button", { name: "Delete" }));
    await fireEvent.click(screen.getByRole("button", { name: "Delete token" }));

    expect(router.delete).toHaveBeenCalledWith(
      "/profile/api-tokens/token-revoked",
      expect.objectContaining({
        preserveScroll: true,
      }),
    );

    const options = router.delete.mock.calls[0][1] as { onFinish?: () => void };
    options.onFinish?.();
    await tick();

    expect(
      screen.queryByRole("dialog", { name: "Delete revoked token?" }),
    ).not.toBeInTheDocument();
  });

  it("lists the sign-in history", () => {
    render(ProfileShow, {
      props: {
        profile,
        sign_in_history: [
          {
            id: "s1",
            method: "github",
            method_label: "GitHub",
            occurred_at: "2026-11-22T10:00:00Z",
            ip: "203.0.113.5",
            user_agent: null,
          },
        ],
      },
    });

    expect(screen.getByText("Sign-in history")).toBeInTheDocument();
    expect(screen.getByText("203.0.113.5")).toBeInTheDocument();
  });
});
