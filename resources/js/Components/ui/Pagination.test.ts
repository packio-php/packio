import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import Pagination from "./Pagination.svelte";

function renderPagination(overrides = {}) {
  const onPageChange = vi.fn();
  const onPerPageChange = vi.fn();

  render(Pagination, {
    props: {
      currentPage: 3,
      lastPage: 5,
      hasMorePages: true,
      hasPreviousPages: true,
      from: 41,
      to: 60,
      total: 100,
      perPage: 20,
      onPageChange,
      onPerPageChange,
      ...overrides,
    },
  });

  return { onPageChange, onPerPageChange };
}

describe("Pagination", () => {
  it("renders range information and page controls", async () => {
    const { onPageChange } = renderPagination();

    expect(screen.getByText(/Showing/)).toHaveTextContent(
      "Showing 41 to 60 of 100 results",
    );

    await fireEvent.click(screen.getByRole("button", { name: "2" }));
    await fireEvent.click(screen.getByRole("button", { name: "→" }));
    await fireEvent.click(screen.getByRole("button", { name: "←" }));

    expect(onPageChange).toHaveBeenNthCalledWith(1, 2);
    expect(onPageChange).toHaveBeenNthCalledWith(2, 4);
    expect(onPageChange).toHaveBeenNthCalledWith(3, 2);
  });

  it("disables previous and next controls at boundaries", () => {
    renderPagination({
      currentPage: 1,
      lastPage: 1,
      hasMorePages: false,
      hasPreviousPages: false,
    });

    expect(screen.getByRole("button", { name: "←" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "→" })).toBeDisabled();
  });

  it("emits per-page changes when selector is enabled", async () => {
    const { onPerPageChange } = renderPagination();

    await fireEvent.change(screen.getByRole("combobox"), {
      target: { value: "50" },
    });

    expect(onPerPageChange).toHaveBeenCalledWith(50);
  });

  it("hides the per-page selector when disabled", () => {
    renderPagination({
      showPerPageSelector: false,
      onPerPageChange: null,
    });

    expect(screen.queryByRole("combobox")).not.toBeInTheDocument();
  });
});
