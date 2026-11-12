import { get } from "svelte/store";
import { beforeEach, describe, expect, it } from "vitest";
import { toast, toasts } from "./toast";

describe("toast", () => {
  beforeEach(() => {
    toasts.set([]);
  });

  it("adds and dismisses toasts", () => {
    const id = toast.success("Saved");

    expect(get(toasts)).toMatchObject([
      {
        id,
        type: "success",
        message: "Saved",
        duration: 5000,
        position: "top-right",
      },
    ]);

    toast.dismiss(id);

    expect(get(toasts)).toEqual([]);
  });

  it("keeps at most five toasts per position", () => {
    for (let index = 1; index <= 6; index += 1) {
      toast.info(`Top ${index}`);
    }
    toast.error("Bottom", { position: "bottom-left" });

    const current = get(toasts);

    expect(
      current.filter((item) => item.position === "top-right"),
    ).toHaveLength(5);
    expect(current.map((item) => item.message)).not.toContain("Top 1");
    expect(current.map((item) => item.message)).toContain("Bottom");
  });
});
