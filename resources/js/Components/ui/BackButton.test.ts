import { render, screen } from "@testing-library/svelte";
import { describe, expect, it } from "vitest";
import BackButton from "./BackButton.svelte";

describe("BackButton", () => {
  it("renders a non-draggable back link", () => {
    render(BackButton, {
      props: { href: "/admin/users", label: "Back to users" },
    });

    const link = screen.getByRole("link", { name: /Back to users/ });
    expect(link).toHaveAttribute("href", "/admin/users");
    expect(link).toHaveAttribute("draggable", "false");
  });
});
