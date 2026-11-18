import { describe, expect, it } from "vitest";
import source from "./TableFrame.svelte?raw";

describe("TableFrame", () => {
  it("keeps header text in its written case", () => {
    expect(source).not.toMatch(/text-transform:\s*uppercase/);
  });
});
