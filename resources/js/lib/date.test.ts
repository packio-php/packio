import { describe, expect, it } from "vitest";
import { addCalendarMonths, browserTimeZone } from "./date";

describe("addCalendarMonths", () => {
  it("clamps to the last day of a shorter month", () => {
    const result = addCalendarMonths(new Date(2027, 0, 31, 10, 30, 45), 1);
    expect([result.getFullYear(), result.getMonth(), result.getDate()]).toEqual(
      [2027, 1, 28],
    );
    expect(addCalendarMonths(new Date(2028, 0, 31), 1).getDate()).toBe(29);
  });

  it("keeps hours and minutes and zeroes seconds", () => {
    const result = addCalendarMonths(new Date(2026, 2, 15, 10, 30, 45), 12);
    expect(result).toEqual(new Date(2027, 2, 15, 10, 30, 0));
  });
});

describe("browserTimeZone", () => {
  it("returns a non-empty zone name", () => {
    expect(browserTimeZone()).not.toBe("");
  });
});
