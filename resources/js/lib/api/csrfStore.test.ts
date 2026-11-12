import { beforeEach, describe, expect, it } from "vitest";
import { resetInertiaMocks, setPageProps } from "@/test/inertia";
import { csrfStore } from "./csrfStore";

describe("csrfStore", () => {
  beforeEach(() => {
    csrfStore.set("");
  });

  it("exposes the current token synchronously", () => {
    csrfStore.set("token-a");

    expect(csrfStore.current()).toBe("token-a");
  });

  it("updates only from successful Inertia page props", () => {
    csrfStore.set("initial-token");

    setPageProps({ csrf: { token: "page-token" } });

    expect(csrfStore.current()).toBe("page-token");
  });

  it("is cleared by the shared Inertia test reset", () => {
    csrfStore.set("leaky-token");

    resetInertiaMocks();

    expect(csrfStore.current()).toBe("");
  });
});
