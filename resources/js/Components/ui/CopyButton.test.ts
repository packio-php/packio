import { fireEvent, render, screen } from "@testing-library/svelte";
import { tick } from "svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import CopyButton from "./CopyButton.svelte";

describe("CopyButton", () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  it("copies the configured value and resets copied state", async () => {
    render(CopyButton, {
      props: {
        value: "secret-token",
        label: "Copy token",
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: "Copy token" }));

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith("secret-token");
    expect(screen.getByRole("button", { name: "Copied" })).toBeInTheDocument();

    vi.advanceTimersByTime(1500);
    await tick();

    expect(
      screen.getByRole("button", { name: "Copy token" }),
    ).toBeInTheDocument();
  });

  it("does not copy empty values", async () => {
    render(CopyButton, { props: { value: "", label: "Copy token" } });

    await fireEvent.click(screen.getByRole("button", { name: "Copy token" }));

    expect(navigator.clipboard.writeText).not.toHaveBeenCalled();
  });

  it("keeps default state when clipboard write fails", async () => {
    vi.mocked(navigator.clipboard.writeText).mockRejectedValueOnce(
      new Error("denied"),
    );

    render(CopyButton, { props: { value: "secret", label: "Copy token" } });

    await fireEvent.click(screen.getByRole("button", { name: "Copy token" }));

    expect(
      screen.getByRole("button", { name: "Copy token" }),
    ).toBeInTheDocument();
  });
});
