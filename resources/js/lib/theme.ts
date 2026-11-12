import { readCookie, writeCookie } from "@/lib/cookie";
export type ThemeMode = "dark" | "light" | "system";
export type ResolvedTheme = "dark" | "light";

export const themePageBg: Record<ResolvedTheme, string> = {
  light: "#f3f4f7",
  dark: "#1a1f2a",
};

export function systemTheme(): ResolvedTheme {
  if (typeof window === "undefined" || !window.matchMedia) {
    return "light";
  }

  return window.matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
}

function isThemeMode(value: string | null): value is ThemeMode {
  return value === "light" || value === "dark" || value === "system";
}

export function readStoredTheme(storageKey: string): ThemeMode {
  if (typeof window === "undefined") {
    return "system";
  }

  try {
    const stored = window.localStorage.getItem(storageKey);
    if (isThemeMode(stored)) {
      return stored;
    }
  } catch {
    // Fall back to the cookie below.
  }

  const cookie = readCookie(storageKey);
  return isThemeMode(cookie) ? cookie : "system";
}

export function resolveTheme(mode: ThemeMode): ResolvedTheme {
  return mode === "system" ? systemTheme() : mode;
}

export function storeTheme(storageKey: string, mode: ThemeMode): void {
  if (typeof window !== "undefined") {
    try {
      window.localStorage.setItem(storageKey, mode);
    } catch {
      // Cookie persistence still gives the server an initial preference.
    }
  }

  writeCookie(storageKey, mode);
  if (storageKey !== "packio-theme") {
    writeCookie("packio-theme", mode);
  }
}

export function applyTheme(mode: ThemeMode): ResolvedTheme {
  const resolvedTheme = resolveTheme(mode);

  if (typeof document === "undefined") {
    return resolvedTheme;
  }

  const pageBg = themePageBg[resolvedTheme];
  const root = document.documentElement;

  root.dataset.theme = resolvedTheme;
  root.dataset.themeMode = mode;
  root.style.colorScheme = resolvedTheme;
  root.style.backgroundColor = pageBg;
  document
    .querySelectorAll('meta[name="theme-color"]')
    .forEach((meta) => meta.setAttribute("content", pageBg));

  return resolvedTheme;
}

export function setTheme(storageKey: string, mode: ThemeMode): ResolvedTheme {
  storeTheme(storageKey, mode);

  return applyTheme(mode);
}

export function finishThemeBoot(): void {
  if (typeof document === "undefined") {
    return;
  }

  window.requestAnimationFrame(() => {
    delete document.documentElement.dataset.themeBooting;
  });
}
