import { render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import Show from "./Show.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Legal Show page", () => {
  it("renders the document html inside the markdown container", () => {
    const { container } = render(Show, {
      props: { title: "Privacy Policy", html: "<p>Hello <b>there</b></p>" },
    });

    expect(container.querySelector(".ui-markdown b")?.textContent).toBe(
      "there",
    );
    expect(document.title).toBe("Privacy Policy");
    expect(screen.queryByText("Hello", { exact: false })).not.toBeNull();
  });
});
