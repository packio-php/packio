import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import Login from "./Login.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Login page", () => {
  it("posts login payload through the Inertia client", async () => {
    render(Login, {
      props: {
        old: { email: "user@example.com" },
        redirect_to: "/organization/acme/packages",
        honeypot: { field_name: "website" },
      },
    });

    await fireEvent.input(screen.getByLabelText("Password"), {
      target: { value: "secret-pass" },
    });
    await fireEvent.submit(screen.getByRole("button", { name: "Sign in" }));

    expect(router.post).toHaveBeenCalledWith(
      "/auth/login",
      {
        email: "user@example.com",
        password: "secret-pass",
        redirect_to: "/organization/acme/packages",
        website: "",
      },
      expect.objectContaining({
        preserveScroll: true,
      }),
    );
  });

  it("blocks submit when Turnstile is required and no token exists", async () => {
    render(Login, {
      props: {
        turnstile: { enabled: true, site_key: "site-key" },
      },
    });

    expect(screen.getByRole("button", { name: "Sign in" })).toBeDisabled();

    await fireEvent.submit(screen.getByRole("button", { name: "Sign in" }));

    expect(router.post).not.toHaveBeenCalled();
  });

  it("renders registration state and redirect-aware register link", () => {
    render(Login, {
      props: {
        redirect_to: "/organization/acme/packages",
        registration_open: true,
      },
    });

    expect(screen.getByRole("link", { name: "Sign up" })).toHaveAttribute(
      "href",
      "/auth/register?redirect_to=%2Forganization%2Facme%2Fpackages",
    );
  });

  it("hides register link when registration is closed", () => {
    render(Login, {
      props: {
        registration_open: false,
      },
    });

    expect(
      screen.queryByRole("link", { name: "Sign up" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByText("Registration is currently closed."),
    ).toBeInTheDocument();
  });
});
