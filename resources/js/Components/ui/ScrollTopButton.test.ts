import { act, fireEvent, render, screen } from "@testing-library/svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { emitRouterEvent } from "@/test/inertia";
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

function setWindowInnerWidth(value: number): void {
  Object.defineProperty(window, "innerWidth", {
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

function createReference(rect: Partial<DOMRect>): {
  element: HTMLDivElement;
  spy: ReturnType<typeof vi.spyOn>;
} {
  const element = document.createElement("div");
  element.className = "reference-marker";
  document.body.appendChild(element);

  const spy = vi.spyOn(element, "getBoundingClientRect").mockReturnValue({
    x: 0,
    y: 0,
    width: 1024,
    height: 800,
    top: 0,
    left: 0,
    right: 800,
    bottom: 800,
    toJSON: () => ({}),
    ...rect,
  } as DOMRect);

  return { element, spy };
}

beforeEach(() => {
  setWindowScrollY(0);
  setWindowInnerWidth(1024);
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

  it("anchors the right offset to the reference element and updates it on resize", async () => {
    const container = createContainer();
    setScrollTop(container, 400);
    createReference({ right: 800 });
    setWindowInnerWidth(1000);

    render(ScrollTopButton, {
      props: { container, referenceSelector: ".reference-marker" },
    });

    const button = screen.getByRole("button", { name: "Scroll to top" });
    expect(button.parentElement).toHaveStyle({ right: "184px" });

    await act(() => {
      setWindowInnerWidth(900);
      window.dispatchEvent(new Event("resize"));
    });
    expect(button.parentElement).toHaveStyle({ right: "84px" });

    await act(() => {
      setWindowInnerWidth(500);
      window.dispatchEvent(new Event("resize"));
    });
    expect(button.parentElement).toHaveStyle({ right: "24px" });
  });

  it("falls back to the default right offset when the reference is missing", () => {
    const container = createContainer();
    setScrollTop(container, 400);

    render(ScrollTopButton, {
      props: { container, referenceSelector: ".missing-marker" },
    });

    const button = screen.getByRole("button", { name: "Scroll to top" });
    expect(button.parentElement?.style.right).toBe("");
    expect(button.parentElement).toHaveClass("right-6");
  });

  it("recalculates the offset after an Inertia navigation", async () => {
    const container = createContainer();
    setScrollTop(container, 400);
    const { spy } = createReference({ right: 800 });
    setWindowInnerWidth(1000);

    render(ScrollTopButton, {
      props: { container, referenceSelector: ".reference-marker" },
    });

    const button = screen.getByRole("button", { name: "Scroll to top" });
    expect(button.parentElement).toHaveStyle({ right: "184px" });

    spy.mockReturnValue({
      x: 0,
      y: 0,
      width: 1024,
      height: 800,
      top: 0,
      left: 0,
      right: 700,
      bottom: 800,
      toJSON: () => ({}),
    } as DOMRect);
    emitRouterEvent("navigate", { detail: { page: {} } });

    await vi.waitFor(() => {
      expect(button.parentElement).toHaveStyle({ right: "284px" });
    });
  });
});
