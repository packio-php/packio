import { act, fireEvent, render, screen } from "@testing-library/svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { setPageProps } from "@/test/inertia";
import AdminShellHarness from "@/test/AdminShellHarness.svelte";

const auth = {
  user: {
    id: "admin-1",
    display_name: "Admin User",
    email: "admin@example.test",
    roles: ["admin"],
  },
};

function setScrollTop(element: HTMLElement, value: number): void {
  Object.defineProperty(element, "scrollTop", {
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
  setPageProps({
    branding: {
      name: "Packio",
      domain: "registry.test",
      themeStorageKey: "packio-theme",
    },
  });
  setWindowScrollY(0);
});

describe("AdminShell content scroll wiring", () => {
  it("binds the real ScrollTopButton to the content scroll container", async () => {
    const { container } = render(AdminShellHarness, { props: { auth } });
    const scrollContainer = container.querySelector(
      "div.admin-content-scroll",
    ) as HTMLElement;

    expect(screen.queryByRole("button", { name: "Scroll to top" })).toBeNull();

    // Window scrolling must not show the button in app-shell mode.
    await act(() => {
      setWindowScrollY(900);
      window.dispatchEvent(new Event("scroll"));
    });
    expect(screen.queryByRole("button", { name: "Scroll to top" })).toBeNull();

    // Scrolling the content column shows the button at the default
    // viewport-edge position (no inline right offset).
    await act(() => {
      setScrollTop(scrollContainer, 400);
      scrollContainer.dispatchEvent(new Event("scroll"));
    });

    const button = screen.getByRole("button", { name: "Scroll to top" });
    expect(button.parentElement?.style.right).toBe("");
    expect(button.parentElement).toHaveClass("right-6");

    // Clicking scrolls the container, not the window.
    const scrollTo = vi.fn();
    Object.defineProperty(scrollContainer, "scrollTo", {
      configurable: true,
      value: scrollTo,
    });
    await fireEvent.click(button);

    expect(scrollTo).toHaveBeenCalledWith({ top: 0, behavior: "smooth" });
    expect(window.scrollTo).not.toHaveBeenCalled();
  });
});
