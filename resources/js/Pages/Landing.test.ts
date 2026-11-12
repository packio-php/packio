import { render } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
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
});
