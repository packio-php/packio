import { beforeEach, describe, expect, it } from "vitest";
import {
  isListViewMode,
  listViewStorageKey,
  readStoredListViewMode,
  storeListViewMode,
} from "./listViewMode";

describe("listViewMode", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("reads a valid mode from localStorage before cookies", () => {
    localStorage.setItem(listViewStorageKey, "table");
    document.cookie = `${listViewStorageKey}=grid; Path=/`;

    expect(readStoredListViewMode(listViewStorageKey)).toBe("table");
  });

  it("falls back to cookies and then the configured fallback", () => {
    document.cookie = `${listViewStorageKey}=table; Path=/`;

    expect(readStoredListViewMode(listViewStorageKey)).toBe("table");
    expect(readStoredListViewMode("missing-list-view-mode")).toBe("grid");
    expect(readStoredListViewMode("missing-list-view-mode", "table")).toBe(
      "table",
    );
  });

  it("rejects invalid values", () => {
    localStorage.setItem(listViewStorageKey, "cards");
    document.cookie = `${listViewStorageKey}=rows; Path=/`;

    expect(readStoredListViewMode(listViewStorageKey)).toBe("grid");
    expect(isListViewMode("cards")).toBe(false);
    expect(isListViewMode("table")).toBe(true);
  });

  it("stores the mode in localStorage and cookies", () => {
    storeListViewMode(listViewStorageKey, "table");

    expect(localStorage.getItem(listViewStorageKey)).toBe("table");
    expect(document.cookie).toContain(`${listViewStorageKey}=table`);
  });
});
