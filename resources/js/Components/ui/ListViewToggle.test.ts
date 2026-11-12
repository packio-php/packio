import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import ListViewToggle from "./ListViewToggle.svelte";

describe("ListViewToggle", () => {
  it("renders both mode buttons and marks the active mode", () => {
    render(ListViewToggle, {
      props: {
        mode: "table",
        onChange: vi.fn(),
      },
    });

    expect(
      screen.getByRole("button", { name: "Show as grid" }),
    ).toHaveAttribute("aria-pressed", "false");
    expect(
      screen.getByRole("button", { name: "Show as grid" }),
    ).toHaveAttribute("title", "Show as grid");
    expect(
      screen.getByRole("button", { name: "Show as table" }),
    ).toHaveAttribute("aria-pressed", "true");
    expect(
      screen.getByRole("button", { name: "Show as table" }),
    ).toHaveAttribute("title", "Show as table");
  });

  it("calls onChange with the selected mode", async () => {
    const onChange = vi.fn();

    render(ListViewToggle, {
      props: {
        mode: "grid",
        onChange,
      },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Show as table" }),
    );

    expect(onChange).toHaveBeenCalledWith("table");
  });

  it("updates the active button styling when the mode changes", async () => {
    const { rerender } = render(ListViewToggle, {
      props: {
        mode: "grid",
        onChange: vi.fn(),
      },
    });

    const gridButton = screen.getByRole("button", { name: "Show as grid" });
    const tableButton = screen.getByRole("button", { name: "Show as table" });

    expect(gridButton).toHaveClass("border-[var(--accent)]");
    expect(tableButton).toHaveClass("border-transparent");

    await rerender({
      mode: "table",
      onChange: vi.fn(),
    });

    expect(gridButton).toHaveClass("border-transparent");
    expect(tableButton).toHaveClass("border-[var(--accent)]");
  });
});
