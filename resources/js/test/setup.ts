import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/svelte";
import { afterEach, beforeEach, vi } from "vitest";
import { csrfStore } from "@/lib/api/csrfStore";
import { registerInertiaReset, resetInertiaMocks } from "./inertia";

vi.mock("@inertiajs/svelte", async () => {
  const inertia = await import("./inertia");
  const { default: Link } = await import("./MockInertiaLink.svelte");

  return {
    ...inertia,
    Link,
  };
});

registerInertiaReset(() => {
  csrfStore.set("");
});

class TestResizeObserver {
  observe(): void {}
  unobserve(): void {}
  disconnect(): void {}
}

class TestIntersectionObserver {
  readonly root = null;
  readonly rootMargin = "";
  readonly thresholds: number[] = [];

  disconnect(): void {}
  observe(): void {}
  takeRecords(): IntersectionObserverEntry[] {
    return [];
  }
  unobserve(): void {}
}

function installBrowserStubs(): void {
  Object.defineProperty(window, "matchMedia", {
    configurable: true,
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  });

  Object.defineProperty(window, "scrollTo", {
    configurable: true,
    writable: true,
    value: vi.fn(),
  });

  Object.defineProperty(window, "ResizeObserver", {
    configurable: true,
    writable: true,
    value: TestResizeObserver,
  });
  Object.defineProperty(globalThis, "ResizeObserver", {
    configurable: true,
    writable: true,
    value: TestResizeObserver,
  });

  Object.defineProperty(window, "IntersectionObserver", {
    configurable: true,
    writable: true,
    value: TestIntersectionObserver,
  });
  Object.defineProperty(globalThis, "IntersectionObserver", {
    configurable: true,
    writable: true,
    value: TestIntersectionObserver,
  });

  Object.defineProperty(globalThis, "crypto", {
    configurable: true,
    writable: true,
    value: {
      randomUUID: vi.fn(() => "00000000-0000-4000-8000-000000000000"),
    },
  });

  Object.defineProperty(navigator, "clipboard", {
    configurable: true,
    writable: true,
    value: {
      writeText: vi.fn().mockResolvedValue(undefined),
    },
  });
}

beforeEach(() => {
  installBrowserStubs();
  document.body.innerHTML = "";
  document.head
    .querySelectorAll('script, meta[name="theme-color"]')
    .forEach((element) => element.remove());
  document.documentElement.removeAttribute("data-theme");
  document.documentElement.removeAttribute("data-theme-mode");
  document.documentElement.removeAttribute("data-theme-booting");
  document.documentElement.removeAttribute("style");
  delete (window as Window & { turnstile?: unknown }).turnstile;
  document.cookie
    .split(";")
    .map((cookie) => cookie.split("=")[0]?.trim())
    .filter(Boolean)
    .forEach((name) => {
      document.cookie = `${name}=; Max-Age=0; Path=/`;
    });
  localStorage.clear();
  sessionStorage.clear();
  resetInertiaMocks();
});

afterEach(() => {
  cleanup();
  vi.useRealTimers();
  vi.restoreAllMocks();
  vi.clearAllMocks();
  resetInertiaMocks();
  document.body.removeAttribute("style");
  document.documentElement.removeAttribute("style");
});
