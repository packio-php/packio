import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import OAuthEmail from "./OAuthEmail.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("OAuth email page", () => {
  it("names the provider and posts the entered email", async () => {
    render(OAuthEmail, {
      props: {
        provider: "github",
        provider_label: "GitHub",
        provider_username: "octocat",
      },
    });

    expect(screen.getByText("GitHub")).toBeInTheDocument();
    expect(screen.getByText(/octocat/)).toBeInTheDocument();

    await fireEvent.input(screen.getByLabelText("Email"), {
      target: { value: "user@example.com" },
    });
    await fireEvent.submit(screen.getByRole("button", { name: "Continue" }));

    expect(router.post).toHaveBeenCalledWith(
      "/auth/oauth/email",
      { email: "user@example.com", website_url: "" },
      expect.objectContaining({ preserveScroll: true }),
    );
  });
});
