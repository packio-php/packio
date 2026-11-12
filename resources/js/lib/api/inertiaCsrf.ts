import type { HttpRequestConfig } from "@inertiajs/core";
import { csrfStore } from "./csrfStore";

export type RequestAuthenticationMode = "session" | "stateless";

type InertiaHttp = {
  onRequest(
    handler: (config: HttpRequestConfig) => HttpRequestConfig,
  ): () => void;
};

const MUTATING_METHODS = new Set(["post", "put", "patch", "delete"]);

function hasHeader(headers: Record<string, unknown>, name: string): boolean {
  const normalizedName = name.toLowerCase();

  return Object.keys(headers).some(
    (headerName) => headerName.toLowerCase() === normalizedName,
  );
}

export function addCsrfTokenHeader(
  method: string,
  headers: Record<string, string>,
  authenticationMode: RequestAuthenticationMode,
): Record<string, string>;
export function addCsrfTokenHeader(
  method: string,
  headers: Record<string, unknown>,
  authenticationMode?: RequestAuthenticationMode,
): Record<string, unknown>;
export function addCsrfTokenHeader(
  method: string,
  headers: Record<string, unknown>,
  authenticationMode: RequestAuthenticationMode = "session",
): Record<string, unknown> {
  const token = csrfStore.current();

  if (
    authenticationMode !== "session" ||
    !MUTATING_METHODS.has(method.toLowerCase()) ||
    token.length === 0 ||
    hasHeader(headers, "X-CSRF-Token")
  ) {
    return headers;
  }

  return {
    ...headers,
    "X-CSRF-Token": token,
  };
}

export function registerInertiaCsrfHandlers(http: InertiaHttp): void {
  http.onRequest((config) => {
    const headers = config.headers ?? {};
    const csrfHeaders = addCsrfTokenHeader(config.method, headers);

    if (csrfHeaders === headers) {
      return config;
    }

    return {
      ...config,
      headers: csrfHeaders,
    };
  });
}
