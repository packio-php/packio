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
  it("shows API token guidance and applies expiration helpers", async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2026-01-15T10:30:00"));

    render(ProfileShow, {
      props: {
        profile,
      },
    });

    expect(screen.getByText("Store the token now")).toBeInTheDocument();
    expect(
      screen.getByText("Leave blank to create a token that never expires."),
    ).toBeInTheDocument();
    const apiDocumentationLink = screen.getByRole("link", {
      name: "Open API documentation",
    });
    expect(apiDocumentationLink).toHaveAttribute("href", "/api/doc");
    expect(apiDocumentationLink).toHaveAttribute("target", "_blank");
    expect(apiDocumentationLink).toHaveAttribute(
      "rel",
      "external noopener noreferrer",
    );

    const expiration = screen.getByLabelText(
      "Expiration date",
    ) as HTMLInputElement;
    const threeMonths = screen.getByLabelText("3 months") as HTMLInputElement;

    await fireEvent.click(threeMonths);
    await tick();

    expect(threeMonths.checked).toBe(true);
    expect(expiration.value).toBe("2026-04-15T10:30");

    await fireEvent.input(expiration, {
      target: { value: "2026-02-01T12:00" },
    });
    await tick();

    expect(threeMonths.checked).toBe(false);

    await fireEvent.input(screen.getByLabelText("Token name"), {
      target: { value: "CI automation" },
    });
    await fireEvent.submit(
      screen.getByRole("button", { name: "Issue API token" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      "/profile/api-tokens",
      {
        name: "CI automation",
        scopes: ["read"],
        expires_at: "2026-02-01 12:00",
      },
      expect.objectContaining({
        preserveScroll: true,
      }),
    );
  });

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
});
