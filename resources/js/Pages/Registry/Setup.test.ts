import { render } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import Setup from "./Setup.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Registry setup page", () => {
  const props = {
    base_url: "https://acme.packio.test",
    public_bootstrap: "https://acme.packio.test/public",
    private_bootstrap: "https://acme.packio.test/private",
  };

  it("shows the private repository URL and auth command to a signed-in user", () => {
    render(Setup, { props: { ...props, auth: { check: true } } });

    const text = document.body.textContent ?? "";
    expect(text).toContain('"url": "https://acme.packio.test/private"');
    expect(text).toContain("http-basic.acme.packio.test token");
  });

  it("shows the public repository URL to a guest", () => {
    render(Setup, { props: { ...props, auth: { check: false } } });

    const text = document.body.textContent ?? "";
    expect(text).toContain('"url": "https://acme.packio.test/public"');
    expect(text).not.toContain("https://acme.packio.test/private");
  });

  it("prefers the registry host over the base URL in Composer commands", () => {
    render(Setup, {
      props: { ...props, registry_host: "registry.packio.test" },
    });

    expect(document.body.textContent).toContain(
      "http-basic.registry.packio.test token",
    );
  });
});
