import { render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { setPageProps } from "@/test/inertia";
import Landing from "./Landing.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Landing", () => {
  it("uses the branding name instead of a hard-coded product name", () => {
    const { container } = render(Landing, {
      product_name: "Acme",
      domain: "acme.test",
    });

    expect(container.textContent).toContain("Acme");
    expect(container.textContent).not.toContain("Packio");
  });

  it("shows the legal notice only with OAuth providers", () => {
    setPageProps({
      legal: { privacy_url: "/privacy", terms_url: "/terms" },
    });
    const oauth_providers = [
      { provider: "github" as const, label: "GitHub", enabled: true },
    ];
    const { unmount } = render(Landing, { props: { oauth_providers } });
    expect(
      screen.getByRole("link", { name: "Terms of Service" }),
    ).toBeInTheDocument();
    unmount();

    render(Landing, { props: { oauth_providers: [] } });
    expect(screen.queryByRole("link", { name: "Terms of Service" })).toBeNull();
  });
});
