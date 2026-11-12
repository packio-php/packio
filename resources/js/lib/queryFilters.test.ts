import { describe, expect, it } from "vitest";
import { cleanQuery, queryString } from "./queryFilters";

function parsePhpBracketQuery(
  query: string,
): Record<string, string | string[]> {
  const parsed: Record<string, string | string[]> = {};
  const params = new URLSearchParams(query.replace(/^\?/, ""));

  for (const [key, value] of params.entries()) {
    if (!key.endsWith("[]")) {
      parsed[key] = value;
      continue;
    }

    const arrayKey = key.slice(0, -2);
    const current = parsed[arrayKey];
    parsed[arrayKey] = Array.isArray(current) ? [...current, value] : [value];
  }

  return parsed;
}

describe("queryFilters", () => {
  it("removes empty filter values and preserves active values", () => {
    expect(
      cleanQuery({
        search: "  acme  ",
        provider: ["github"],
        visibility: [],
        listed: "",
        page: 1,
        has_error: true,
        has_warnings: false,
        omitted: null,
      }),
    ).toEqual({
      search: "acme",
      provider: ["github"],
      page: 1,
      has_error: "1",
    });
  });

  it("serializes array parameters in PHP-compatible bracket syntax", () => {
    expect(
      queryString({
        search: "acme",
        visibility: ["public", "private"],
        listed: "",
        page: 2,
      }),
    ).toBe("?search=acme&visibility[]=public&visibility[]=private&page=2");
  });

  it("round-trips multiple selected values through PHP-compatible query parsing", () => {
    const query = queryString({
      page: 2,
      provider: ["github", "gitlab"],
      status: ["active", "archived"],
      visibility: ["public", "private"],
    });

    expect(parsePhpBracketQuery(query)).toEqual({
      page: "2",
      provider: ["github", "gitlab"],
      status: ["active", "archived"],
      visibility: ["public", "private"],
    });
  });
});
