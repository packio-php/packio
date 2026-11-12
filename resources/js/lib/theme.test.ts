import { beforeEach, describe, expect, it, vi } from "vitest";
import { applyTheme, readStoredTheme, resolveTheme, storeTheme } from "./theme";

describe("theme", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("reads stored theme from localStorage before cookies", () => {
    localStorage.setItem("packio-theme", "dark");
    document.cookie = "packio-theme=light; Path=/";

    expect(readStoredTheme("packio-theme")).toBe("dark");
  });

  it("falls back to cookies and then system", () => {
    document.cookie = "packio-theme=light; Path=/";

    expect(readStoredTheme("packio-theme")).toBe("light");
    expect(readStoredTheme("missing-theme")).toBe("system");
  });

  it("resolves system theme using matchMedia", () => {
    vi.mocked(window.matchMedia).mockReturnValue({
      matches: true,
      media: "(prefers-color-scheme: dark)",
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    });

    expect(resolveTheme("system")).toBe("dark");
  });

  it("stores theme in localStorage and cookies", () => {
    storeTheme("custom-theme", "dark");

    expect(localStorage.getItem("custom-theme")).toBe("dark");
    expect(document.cookie).toContain("custom-theme=dark");
    expect(document.cookie).toContain("packio-theme=dark");
  });

  it("applies theme attributes and meta color", () => {
    const meta = document.createElement("meta");
    meta.setAttribute("name", "theme-color");
    document.head.appendChild(meta);

    expect(applyTheme("dark")).toBe("dark");
    expect(document.documentElement.dataset.theme).toBe("dark");
    expect(document.documentElement.dataset.themeMode).toBe("dark");
    expect(document.documentElement.style.colorScheme).toBe("dark");
    expect(meta.getAttribute("content")).toBe("#1a1f2a");
  });
});
