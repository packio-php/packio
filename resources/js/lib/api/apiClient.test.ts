import { beforeEach, describe, expect, it, vi } from "vitest";
import { csrfStore } from "./csrfStore";
import { apiClient } from "./apiClient";

function jsonResponse(
  body: unknown,
  init: ResponseInit = {},
  headers: Record<string, string> = {},
): Response {
  return new Response(JSON.stringify(body), {
    ...init,
    headers: {
      "content-type": "application/json",
      ...headers,
    },
  });
}

describe("apiClient", () => {
  beforeEach(() => {
    csrfStore.set("csrf-a");
  });

  it("sends session-authenticated JSON requests with CSRF and parses successful JSON", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValue(jsonResponse({ ok: true }, { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    const result = await apiClient.post<{ ok: boolean }>("/api/test", {
      name: "Packio",
    });

    expect(fetchMock).toHaveBeenCalledWith("/api/test", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-CSRF-Token": "csrf-a",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ name: "Packio" }),
      signal: undefined,
    });
    expect(result).toEqual({
      ok: true,
      status: 200,
      data: { ok: true },
    });
  });

  it("does not send CSRF headers for safe, empty-token, or stateless requests", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true }));
    vi.stubGlobal("fetch", fetchMock);

    await apiClient.get("/profile");
    csrfStore.set("");
    await apiClient.post("/profile");
    csrfStore.set("csrf-a");
    await apiClient.post("/api/v1/tokens", undefined, {
      authenticationMode: "stateless",
    });

    expect(fetchMock.mock.calls.map(([, options]) => options.headers)).toEqual([
      { Accept: "application/json" },
      { Accept: "application/json" },
      { Accept: "application/json" },
    ]);
  });

  it("preserves a case-insensitive caller CSRF header", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true }));
    vi.stubGlobal("fetch", fetchMock);

    await apiClient.patch(
      "/profile",
      { name: "Packio" },
      {
        headers: { "x-csrf-token": "explicit-token" },
      },
    );

    expect(fetchMock).toHaveBeenCalledWith(
      "/profile",
      expect.objectContaining({
        headers: {
          Accept: "application/json",
          "x-csrf-token": "explicit-token",
          "Content-Type": "application/json",
        },
      }),
    );
  });

  it("does not set JSON content type for FormData", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true }));
    vi.stubGlobal("fetch", fetchMock);
    const formData = new FormData();
    formData.set("name", "Packio");

    await apiClient.post("/api/form", formData, {
      headers: { "X-Custom": "yes" },
    });

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/form",
      expect.objectContaining({
        body: formData,
        headers: {
          Accept: "application/json",
          "X-CSRF-Token": "csrf-a",
          "X-Custom": "yes",
        },
      }),
    );
  });

  it("returns network failures without throwing", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("offline")));

    await expect(apiClient.get("/api/test")).resolves.toEqual({
      ok: false,
      status: 0,
      error: "offline",
    });
  });

  it("uses response error messages in priority order", async () => {
    vi.stubGlobal(
      "fetch",
      vi
        .fn()
        .mockResolvedValue(
          jsonResponse({ error: "Fallback error" }, { status: 422 }),
        ),
    );

    await expect(apiClient.get("/api/test")).resolves.toEqual({
      ok: false,
      status: 422,
      error: "Fallback error",
      data: { error: "Fallback error" },
    });
  });

  it("parses non-json success responses as text", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        new Response("plain text", {
          status: 200,
          headers: { "content-type": "text/plain" },
        }),
      ),
    );

    await expect(apiClient.get<string>("/api/text")).resolves.toEqual({
      ok: true,
      status: 200,
      data: "plain text",
    });
  });
});
