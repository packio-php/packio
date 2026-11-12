import { describe, expect, it, vi } from "vitest";
import { toast } from "@/lib/toast";
import { emitRouterEvent, http, router } from "./test/inertia";

describe("Inertia application bootstrap", () => {
  it("delivers native flash once for initial and navigation pages", async () => {
    const { deliverFlash } = await import("./app");
    const success = vi.spyOn(toast, "success");
    const error = vi.spyOn(toast, "error");

    expect(router.on).toHaveBeenCalledWith(
      "httpException",
      expect.any(Function),
    );
    expect(router.on).toHaveBeenCalledWith("flash", expect.any(Function));
    expect(router.on).not.toHaveBeenCalledWith("invalid", expect.any(Function));
    expect(http.onRequest).toHaveBeenCalledWith(expect.any(Function));
    expect(http.onResponse).not.toHaveBeenCalled();

    const initialFlash = {
      toasts: [
        {
          type: "success" as const,
          message: "Initial notification",
          duration: 3000,
          position: "bottom-right" as const,
        },
      ],
    };

    deliverFlash(initialFlash);
    emitRouterEvent("flash", { flash: initialFlash });
    emitRouterEvent("success", { page: { flash: initialFlash } });

    expect(success).toHaveBeenCalledTimes(1);
    expect(success).toHaveBeenCalledWith("Initial notification", {
      duration: 3000,
      position: "bottom-right",
    });

    emitRouterEvent("flash", {
      flash: {
        toasts: [
          {
            type: "error",
            message: "Navigation notification",
            duration: null,
            position: null,
          },
        ],
      },
    });

    expect(error).toHaveBeenCalledWith("Navigation notification", {
      duration: undefined,
      position: undefined,
    });

    emitRouterEvent("flash", { flash: {} });
    expect(success).toHaveBeenCalledTimes(1);
    expect(error).toHaveBeenCalledTimes(1);
  });

  it("replaces the document with an HTML error response", async () => {
    await import("./app");
    const preventDefault = vi.fn();

    emitRouterEvent(
      "httpException",
      {
        response: {
          status: 500,
          headers: { "content-type": "text/html; charset=UTF-8" },
          data: '<!doctype html><html><body><p id="server-error">Server error</p></body></html>',
        },
      },
      { preventDefault },
    );

    expect(preventDefault).toHaveBeenCalledTimes(1);
    expect(document.getElementById("server-error")?.textContent).toBe(
      "Server error",
    );
  });

  it("reloads the page for a non-HTML error response", async () => {
    await import("./app");
    const preventDefault = vi.fn();
    const assign = vi.fn();
    const root = document.documentElement;

    // jsdom's location.assign is not spy-able; stub the whole object (restored by unstubGlobals).
    vi.stubGlobal("location", { href: "http://localhost/current", assign });

    emitRouterEvent(
      "httpException",
      {
        response: {
          status: 502,
          headers: { "content-type": "application/json" },
          data: {},
        },
      },
      { preventDefault },
    );

    expect(preventDefault).toHaveBeenCalledTimes(1);
    expect(assign).toHaveBeenCalledWith("http://localhost/current");
    expect(document.documentElement).toBe(root);
  });
});
