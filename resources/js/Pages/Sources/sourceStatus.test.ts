import { describe, expect, it } from "vitest";
import { sourceStateLabel, syncSummary } from "./sourceStatus";

describe("sourceStatus", () => {
  it("labels source state independently of sync", () => {
    expect(sourceStateLabel({ active: true, archived: false })).toBe("Active");
    expect(sourceStateLabel({ active: false, archived: false })).toBe(
      "Inactive",
    );
    expect(sourceStateLabel({ active: false, archived: true })).toBe(
      "Archived",
    );
  });

  it("summarises sync results", () => {
    expect(
      syncSummary({
        last_sync_status: "failed",
        last_failed_sync_at: "f",
        last_successful_sync_at: "s",
      }),
    ).toEqual({ label: "Failed", tone: "danger", at: "f" });
    expect(
      syncSummary({
        last_sync_status: "success",
        last_failed_sync_at: "f",
        last_successful_sync_at: "s",
      }),
    ).toEqual({ label: "Synced", tone: "accent", at: "s" });
    expect(syncSummary({ last_sync_status: "pending" })).toEqual({
      label: "Never synced",
      tone: "muted",
      at: null,
    });
    expect(
      syncSummary({
        last_sync_status: "pending",
        last_successful_sync_at: "s",
      }).label,
    ).toBe("Pending");
  });
});
