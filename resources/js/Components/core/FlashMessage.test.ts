import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it } from "vitest";
import FlashMessage from "./FlashMessage.svelte";

describe("FlashMessage", () => {
  it("allows long token messages to wrap on mobile", () => {
    const token = `packio_api_${"a".repeat(96)}`;

    render(FlashMessage, {
      props: {
        flash: {
          success: `New API token: ${token}`,
        },
      },
    });

    expect(
      screen.getByText(`New API token: ${token}`).closest(".flash-message"),
    ).not.toBeNull();
  });

  it("dismisses a success message and shows a new flash again", async () => {
    const { rerender } = render(FlashMessage, {
      props: {
        flash: {
          success: "First notice",
        },
      },
    });

    expect(screen.getByText("First notice")).toBeInTheDocument();

    await fireEvent.click(
      screen.getByRole("button", { name: "Dismiss message" }),
    );
    expect(screen.queryByText("First notice")).toBeNull();

    await rerender({ flash: { success: "Second notice" } });
    expect(screen.getByText("Second notice")).toBeInTheDocument();
  });

  it("dismisses success and error messages independently", async () => {
    render(FlashMessage, {
      props: {
        flash: {
          success: "Kept notice",
          error: "Removed notice",
        },
      },
    });

    const buttons = screen.getAllByRole("button", {
      name: "Dismiss message",
    });
    expect(buttons).toHaveLength(2);

    await fireEvent.click(buttons[1]);
    expect(screen.queryByText("Removed notice")).toBeNull();
    expect(screen.getByText("Kept notice")).toBeInTheDocument();
  });
});
