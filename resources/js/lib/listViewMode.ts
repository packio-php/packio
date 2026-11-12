import { readCookie, writeCookie } from "@/lib/cookie";
export type ListViewMode = "grid" | "table";

export const listViewStorageKey = "packio-list-view-mode";

export function isListViewMode(
  value: string | null | undefined,
): value is ListViewMode {
  return value === "grid" || value === "table";
}

export function readStoredListViewMode(
  storageKey: string,
  fallback: ListViewMode = "grid",
): ListViewMode {
  if (typeof window !== "undefined") {
    try {
      const stored = window.localStorage.getItem(storageKey);
      if (isListViewMode(stored)) {
        return stored;
      }
    } catch {
      // Fall back to the cookie below.
    }
  }

  const cookie = readCookie(storageKey);
  return isListViewMode(cookie) ? cookie : fallback;
}

export function storeListViewMode(
  storageKey: string,
  mode: ListViewMode,
): void {
  if (typeof window !== "undefined") {
    try {
      window.localStorage.setItem(storageKey, mode);
    } catch {
      // Cookie persistence still gives the server an initial preference.
    }
  }

  writeCookie(storageKey, mode);
}
