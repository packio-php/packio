import { describe, expect, it } from "vitest";
import { sourceIssue } from "./packageSource";

const ok = { active: true, archived: false, last_sync_status: "success" };

describe("sourceIssue", () => {
  it.each([
    [{ ...ok, status: "deleting" }, "Deleting"],
    [{ ...ok, archived: true, last_sync_status: "failed" }, "Archived"],
    [{ ...ok, active: false }, "Source inactive"],
    [{ ...ok, last_sync_status: "failed" }, "Sync failed"],
    [ok, null],
    [{ ...ok, last_sync_status: "running" }, null],
  ])("maps %j to %j", (source, expected) => {
    expect(sourceIssue(source)).toBe(expected);
  });
});
