import { act, fireEvent, render, screen } from "@testing-library/svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import ScrollTopButton from "./ScrollTopButton.svelte";

function createContainer(): HTMLDivElement {
  const container = document.createElement("div");

  Object.defineProperty(container, "scrollTo", {
    configurable: true,
    writable: true,
    value: vi.fn(),
  });
  document.body.appendChild(container);

  return container;
}

function setScrollTop(container: HTMLElement, value: number): void {
  Object.defineProperty(container, "scrollTop", {
    configurable: true,
    writable: true,
    value,
  });
}

function setWindowScrollY(value: number): void {
  Object.defineProperty(window, "scrollY", {
    configurable: true,
    writable: true,
    value,
  });
}

beforeEach(() => {
  setWindowScrollY(0);
});

describe("ScrollTopButton", () => {
  it("keeps the window-based visibility and scrolling behavior by default", async () => {
    render(ScrollTopButton);

    expect(screen.queryByRole("button", { name: "Scroll to top" })).toBeNull();

    await act(() => {
      setWindowScrollY(400);
      window.dispatchEvent(new Event("scroll"));
    });

    const button = screen.getByRole("button", { name: "Scroll to top" });
    expect(button).toHaveTextContent("↑ Top");
    expect(window.scrollTo).not.toHaveBeenCalled();

    await fireEvent.click(button);
    expect(window.scrollTo).toHaveBeenCalledWith({
      top: 0,
      behavior: "smooth",
    });
  });

  it("listens to the container scroll and scrolls the container back to top", async () => {
    const container = createContainer();

    render(ScrollTopButton, { props: { container } });

    expect(screen.queryByRole("button", { name: "Scroll to top" })).toBeNull();

    await act(() => {
      setScrollTop(container, 100);
      container.dispatchEvent(new Event("scroll"));
    });
    expect(screen.queryByRole("button", { name: "Scroll to top" })).toBeNull();

    await act(() => {
      setScrollTop(container, 400);
      container.dispatchEvent(new Event("scroll"));
    });

    const button = screen.getByRole("button", { name: "Scroll to top" });
    await fireEvent.click(button);

    expect(container.scrollTo).toHaveBeenCalledWith({
      top: 0,
      behavior: "smooth",
    });
    expect(window.scrollTo).not.toHaveBeenCalled();
  });

  it("ignores window scroll when a container is provided", async () => {
    const container = createContainer();
    setScrollTop(container, 0);

    render(ScrollTopButton, { props: { container } });

    await act(() => {
      setWindowScrollY(900);
      window.dispatchEvent(new Event("scroll"));
    });

    expect(screen.queryByRole("button", { name: "Scroll to top" })).toBeNull();
  });
});
