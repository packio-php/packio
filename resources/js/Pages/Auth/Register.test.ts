import { fireEvent, render, screen } from "@testing-library/svelte";
import { tick } from "svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import Register from "./Register.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Register page", () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  it("does not query account existence when an email is entered", async () => {
    const fetchSpy = vi.spyOn(globalThis, "fetch");
    render(Register);
    await fireEvent.input(screen.getByLabelText("Email"), {
      target: { value: "used@example.com" },
    });
    await vi.advanceTimersByTimeAsync(1000);
    expect(fetchSpy).not.toHaveBeenCalled();
    expect(screen.queryByText(/already registered/)).not.toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Create account" }),
    ).not.toBeDisabled();
  });

  it("posts registration payload and resets submitting on finish", async () => {
    render(Register, {
      props: {
        redirect_to: "/organization/acme/packages",
        honeypot: { field_name: "website" },
      },
    });

    await fireEvent.input(screen.getByLabelText("Display name"), {
      target: { value: "Test User" },
    });
    await fireEvent.input(screen.getByLabelText("Email"), {
      target: { value: "new@example.com" },
    });
    await fireEvent.input(document.querySelector('input[name="password"]')!, {
      target: { value: "secret-pass-1!" },
    });
    await fireEvent.input(
      document.querySelector('input[name="password_confirmation"]')!,
      {
        target: { value: "secret-pass-1!" },
      },
    );

    await fireEvent.submit(
      screen.getByRole("button", { name: "Create account" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      "/auth/register",
      {
        display_name: "Test User",
        email: "new@example.com",
        password: "secret-pass-1!",
        password_confirmation: "secret-pass-1!",
        redirect_to: "/organization/acme/packages",
        website: "",
      },
      expect.objectContaining({
        preserveScroll: true,
      }),
    );

    const options = router.post.mock.calls[0][2] as { onFinish?: () => void };
    options.onFinish?.();
    await tick();

    expect(
      screen.getByRole("button", { name: "Create account" }),
    ).not.toBeDisabled();
  });

  it("blocks registration when closed or Turnstile token is missing", async () => {
    const { rerender } = render(Register, {
      props: {
        registration_open: false,
      },
    });

    expect(
      screen.queryByRole("button", { name: "Create account" }),
    ).not.toBeInTheDocument();

    await rerender({
      registration_open: true,
      turnstile: { enabled: true, site_key: "site-key" },
    });

    expect(
      screen.getByRole("button", { name: "Create account" }),
    ).toBeDisabled();
  });
});
