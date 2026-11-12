import { describe, expect, it } from "vitest";
import { page, setPageProps, usePage } from "./inertia";

describe("Inertia v3 test page", () => {
  it("is a native-shaped reactive page object without Svelte store methods", () => {
    setPageProps({ marker: "updated" });

    expect(usePage()).toBe(page);
    expect(page.props).toMatchObject({ marker: "updated" });
    expect(page).not.toHaveProperty("subscribe");
    expect(page).not.toHaveProperty("set");
    expect(page).not.toHaveProperty("update");
  });
});
