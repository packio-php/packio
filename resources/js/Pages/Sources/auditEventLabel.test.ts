import { describe, expect, it } from "vitest";
import { auditEventLabel } from "./auditEventLabel";

describe("auditEventLabel", () => {
  it("maps known keys", () => {
    expect(auditEventLabel("source.sync")).toBe("Source synchronized");
  });

  it("humanizes unknown keys", () => {
    expect(auditEventLabel("publication.github.completed")).toBe(
      "Publication github completed",
    );
    expect(auditEventLabel("some_thing.else")).toBe("Some thing else");
  });
});
