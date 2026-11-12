import { beforeEach, describe, expect, it } from "vitest";
import { http, resetInertiaMocks } from "@/test/inertia";
import { csrfStore } from "./csrfStore";
import { registerInertiaCsrfHandlers } from "./inertiaCsrf";

describe("Inertia CSRF HTTP handlers", () => {
  beforeEach(() => {
    resetInertiaMocks();
    csrfStore.set("");
    registerInertiaCsrfHandlers(http);
  });

  it("does not add a CSRF header to safe requests", async () => {
    const request = await http.processRequest({
      method: "get",
      url: "/profile",
    });

    expect(request.headers).toBeUndefined();
  });

  it("adds a non-empty current token to mutating requests without replacing an override", async () => {
    csrfStore.set("csrf-current");

    const request = await http.processRequest({
      method: "post",
      url: "/profile",
    });
    const overriddenRequest = await http.processRequest({
      method: "post",
      url: "/profile",
      headers: { "x-csrf-token": "explicit-token" },
    });

    expect(request.headers).toEqual({ "X-CSRF-Token": "csrf-current" });
    expect(overriddenRequest.headers).toEqual({
      "x-csrf-token": "explicit-token",
    });
  });

  it("does not add a CSRF header when the store is empty", async () => {
    const request = await http.processRequest({
      method: "post",
      url: "/profile",
    });

    expect(request.headers).toBeUndefined();
  });
});
