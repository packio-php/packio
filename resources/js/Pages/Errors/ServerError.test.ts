import { render, screen } from "@testing-library/svelte";
import { describe, expect, it } from "vitest";
import ServerError from "./ServerError.svelte";

describe("ServerError", () => {
  it("uses the branding name and domain", () => {
    render(ServerError, { branding: { name: "Acme", domain: "acme.test" } });

    expect(screen.getByText("Acme · acme.test")).toBeTruthy();
    expect(document.body.textContent).not.toContain("Packio");
  });

  it("falls back to default branding when the prop is missing", () => {
    render(ServerError, {});

    expect(document.body.textContent).not.toContain("Packio");
    expect(document.body.textContent).not.toContain("packio.us");
  });
});
