import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import ForgotPassword from "./ForgotPassword.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Forgot password page", () => {
  it("posts the email through the Inertia client", async () => {
    render(ForgotPassword, { props: { old: { email: "user@example.com" } } });

    await fireEvent.submit(
      screen.getByRole("button", { name: "Send reset link" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      "/auth/forgot-password",
      { email: "user@example.com", website_url: "" },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("blocks submit when Turnstile is required and no token exists", async () => {
    render(ForgotPassword, {
      props: { turnstile: { enabled: true, site_key: "site-key" } },
    });

    const button = screen.getByRole("button", { name: "Send reset link" });
    expect(button).toBeDisabled();

    await fireEvent.submit(button);

    expect(router.post).not.toHaveBeenCalled();
  });
});
