import { fireEvent, render, screen } from "@testing-library/svelte";
import { tick } from "svelte";
import { afterEach, describe, expect, it, vi } from "vitest";
import ConfirmationModal from "./ConfirmationModal.svelte";

describe("ConfirmationModal", () => {
  afterEach(() => {
    document.body.querySelectorAll("button").forEach((b) => b.remove());
  });

  it("renders only when open", () => {
    const { rerender } = render(ConfirmationModal, {
      props: {
        open: false,
        title: "Delete token?",
      },
    });

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

    rerender({ open: true, title: "Delete token?" });

    expect(screen.getByRole("dialog")).toBeInTheDocument();
    expect(screen.getByText("Delete token?")).toBeInTheDocument();
  });

  it("calls cancel and confirm handlers", async () => {
    const onCancel = vi.fn();
    const onConfirm = vi.fn();
    render(ConfirmationModal, {
      props: {
        open: true,
        title: "Revoke token?",
        confirmLabel: "Revoke token",
        cancelLabel: "Keep token",
        onCancel,
        onConfirm,
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: "Keep token" }));
    await fireEvent.click(screen.getByRole("button", { name: "Revoke token" }));

    expect(onCancel).toHaveBeenCalledOnce();
    expect(onConfirm).toHaveBeenCalledOnce();
  });

  it("blocks cancellation while confirming", async () => {
    const onCancel = vi.fn();
    render(ConfirmationModal, {
      props: {
        open: true,
        confirming: true,
        onCancel,
      },
    });

    expect(screen.getByRole("button", { name: "Working..." })).toBeDisabled();
    expect(
      screen.getByRole("button", { name: "Close confirmation" }),
    ).toBeDisabled();

    await fireEvent.keyDown(window, { key: "Escape" });

    expect(onCancel).not.toHaveBeenCalled();
  });

  it("moves focus to the cancel button when opened", async () => {
    const trigger = document.createElement("button");
    document.body.append(trigger);
    trigger.focus();
    const { rerender } = render(ConfirmationModal, {
      props: { open: false, cancelLabel: "Keep it" },
    });

    await rerender({ open: true, cancelLabel: "Keep it" });
    await tick();

    expect(document.activeElement).toBe(
      screen.getByRole("button", { name: "Keep it" }),
    );
  });

  it("returns focus to the previously focused element when closed", async () => {
    const trigger = document.createElement("button");
    document.body.append(trigger);
    trigger.focus();
    const { rerender } = render(ConfirmationModal, { props: { open: false } });

    await rerender({ open: true });
    await tick();
    expect(document.activeElement).toBe(
      screen.getByRole("button", { name: "Cancel" }),
    );
    await rerender({ open: false });
    await tick();

    expect(document.activeElement).toBe(trigger);
  });

  it("closes without error when the previous element was removed", async () => {
    const trigger = document.createElement("button");
    document.body.append(trigger);
    trigger.focus();
    const { rerender } = render(ConfirmationModal, { props: { open: false } });

    await rerender({ open: true });
    await tick();
    expect(document.activeElement).toBe(
      screen.getByRole("button", { name: "Cancel" }),
    );
    trigger.remove();
    await rerender({ open: false });
    await tick();

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});
