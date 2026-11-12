import { describe, expect, it } from "vitest";
import { fieldErrors, hasFieldErrors } from "./validationErrors";

describe("validationErrors", () => {
  it("normalizes exact, snake_case, and camelCase field keys", () => {
    const errors = {
      display_name: ["Required"],
      displayName: ["Required", "Too short"],
      email: [" Invalid email ", ""],
    };

    expect(fieldErrors(errors, "displayName")).toEqual([
      "Required",
      "Too short",
    ]);
    expect(fieldErrors(errors, "email")).toEqual(["Invalid email"]);
  });

  it("reports whether raw field errors are present", () => {
    expect(hasFieldErrors({ first_name: ["Required"] }, "firstName")).toBe(
      true,
    );
    expect(hasFieldErrors({}, "firstName")).toBe(false);
    expect(hasFieldErrors(undefined, "firstName")).toBe(false);
  });
});
