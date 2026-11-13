import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import ResetPassword from "./ResetPassword.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Reset password page", () => {
  it("posts the new password with the selector and token from the link", async () => {
    render(ResetPassword, { props: { selector: "sel-1", token: "tok-1" } });

    await fireEvent.input(screen.getByLabelText(/^Password/), {
      target: { value: "new-secret" },
    });
    await fireEvent.input(screen.getByLabelText(/^Confirm password/), {
      target: { value: "new-secret" },
    });
    await fireEvent.submit(
      screen.getByRole("button", { name: "Set new password" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      "/auth/reset-password",
      {
        selector: "sel-1",
        token: "tok-1",
        password: "new-secret",
        password_confirmation: "new-secret",
        website_url: "",
      },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("keeps the selector and token after a failed submit", async () => {
    render(ResetPassword, {
      props: { old: { selector: "sel-old", token: "tok-old" } },
    });

    await fireEvent.submit(
      screen.getByRole("button", { name: "Set new password" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      "/auth/reset-password",
      expect.objectContaining({ selector: "sel-old", token: "tok-old" }),
      expect.anything(),
    );
  });
});
