import {
  addCsrfTokenHeader,
  type RequestAuthenticationMode,
} from "./inertiaCsrf";
import { reloadCurrentLocation } from "@/inertia/httpException";

// ── Types ─────────────────────────────────────────────────────────────────────

export type ApiSuccess<T> = {
  ok: true;
  status: number;
  data: T;
};

export type ApiError = {
  ok: false;
  status: number;
  error: string;
  data?: unknown;
};

export type ApiResponse<T> = ApiSuccess<T> | ApiError;

export type RequestOptions = {
  /** Additional headers merged over the defaults */
  headers?: Record<string, string>;
  /** AbortSignal used to cancel request */
  signal?: AbortSignal;
  /** Select whether this request uses the browser session or stateless credentials. */
  authenticationMode?: RequestAuthenticationMode;
};

// ── Core ─────────────────────────────────────────────────────────────────────

async function request<T>(
  method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE",
  url: string,
  body?: unknown,
  options: RequestOptions = {},
): Promise<ApiResponse<T>> {
  let headers: Record<string, string> = {
    Accept: "application/json",
    ...options.headers,
  };

  headers = addCsrfTokenHeader(
    method,
    headers,
    options.authenticationMode ?? "session",
  );

  // Do not set Content-Type for FormData; the browser adds boundary automatically
  if (body !== undefined && !(body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
  }

  let response: Response;
  try {
    response = await fetch(url, {
      method,
      credentials: "same-origin",
      headers,
      body:
        body instanceof FormData
          ? body
          : body !== undefined
            ? JSON.stringify(body)
            : undefined,
      signal: options.signal,
    });
  } catch (networkError) {
    return {
      ok: false,
      status: 0,
      error:
        networkError instanceof Error ? networkError.message : "Network error",
    };
  }

  // Expired CSRF token: reload to restore a fresh session token
  if (response.status === 419) {
    reloadCurrentLocation();
    return { ok: false, status: 419, error: "CSRF token expired" };
  }

  // Parse response body
  const contentType = response.headers.get("content-type") ?? "";
  let data: unknown;

  if (contentType.includes("application/json")) {
    try {
      data = await response.json();
    } catch {
      data = null;
    }
  } else {
    data = await response.text();
  }

  if (!response.ok) {
    const responseObject =
      data !== null && typeof data === "object"
        ? (data as Record<string, unknown>)
        : null;
    const errorMessage =
      typeof responseObject?.message === "string"
        ? responseObject.message
        : typeof responseObject?.error === "string"
          ? responseObject.error
          : `HTTP ${response.status}`;

    return {
      ok: false,
      status: response.status,
      error: errorMessage,
      data,
    };
  }

  return { ok: true, status: response.status, data: data as T };
}

// ── Public API ────────────────────────────────────────────────────────────────

export const apiClient = {
  get: <T>(url: string, options?: RequestOptions): Promise<ApiResponse<T>> =>
    request<T>("GET", url, undefined, options),

  post: <T>(
    url: string,
    body?: unknown,
    options?: RequestOptions,
  ): Promise<ApiResponse<T>> => request<T>("POST", url, body, options),

  put: <T>(
    url: string,
    body?: unknown,
    options?: RequestOptions,
  ): Promise<ApiResponse<T>> => request<T>("PUT", url, body, options),

  patch: <T>(
    url: string,
    body?: unknown,
    options?: RequestOptions,
  ): Promise<ApiResponse<T>> => request<T>("PATCH", url, body, options),

  delete: <T>(
    url: string,
    body?: unknown,
    options?: RequestOptions,
  ): Promise<ApiResponse<T>> => request<T>("DELETE", url, body, options),
};
