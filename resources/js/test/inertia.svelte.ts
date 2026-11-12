import type {
  HttpRequestConfig,
  HttpRequestHandler,
  HttpResponse,
  HttpResponseHandler,
} from "@inertiajs/core";
import { SvelteMap, SvelteSet } from "svelte/reactivity";
import type { AppPageProps } from "@/types/app";
import { vi } from "vitest";

type TestPage = {
  component: string;
  props: Partial<AppPageProps> & Record<string, unknown>;
  url: string;
  version: string | null;
  clearHistory?: boolean;
  encryptHistory?: boolean;
};

function defaultPage(props: TestPage["props"] = {}): TestPage {
  return {
    component: "Test/Page",
    props,
    url: "/",
    version: null,
  };
}

export const page = $state<TestPage>(defaultPage());

export function usePage<
  TPageProps extends Record<string, unknown> = AppPageProps,
>(): TestPage & { props: TPageProps & AppPageProps } {
  return page as TestPage & { props: TPageProps & AppPageProps };
}

const eventListeners = new SvelteMap<
  string,
  SvelteSet<(event: unknown) => void>
>();
const requestHandlers = new SvelteSet<HttpRequestHandler>();
const responseHandlers = new SvelteSet<HttpResponseHandler>();

export const router = {
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
  reload: vi.fn(),
  on: vi.fn((event: string, callback: (event: unknown) => void) => {
    const listeners = eventListeners.get(event) ?? new SvelteSet();
    listeners.add(callback);
    eventListeners.set(event, listeners);

    return () => listeners.delete(callback);
  }),
};

export const http = {
  onRequest: vi.fn((handler: HttpRequestHandler) => {
    requestHandlers.add(handler);

    return () => requestHandlers.delete(handler);
  }),
  onResponse: vi.fn((handler: HttpResponseHandler) => {
    responseHandlers.add(handler);

    return () => responseHandlers.delete(handler);
  }),
  async processRequest(config: HttpRequestConfig): Promise<HttpRequestConfig> {
    let nextConfig = config;
    for (const handler of requestHandlers) {
      nextConfig = await handler(nextConfig);
    }

    return nextConfig;
  },
  async processResponse(response: HttpResponse): Promise<HttpResponse> {
    let nextResponse = response;
    for (const handler of responseHandlers) {
      nextResponse = await handler(nextResponse);
    }

    return nextResponse;
  },
};

export const createInertiaApp = vi.fn();

const resetCallbacks = new SvelteSet<() => void>();

export function registerInertiaReset(callback: () => void): () => void {
  resetCallbacks.add(callback);

  return () => {
    resetCallbacks.delete(callback);
  };
}

export function setPageProps(props: TestPage["props"]): void {
  Object.assign(page, defaultPage(props));
  emitRouterEvent("success", { page });
}

export function setPageUrl(url: string): void {
  Object.assign(page, { url });
  emitRouterEvent("success", { page });
}

export function emitRouterEvent(
  event: string,
  detail: unknown,
  fields: Record<string, unknown> = {},
): void {
  for (const listener of eventListeners.get(event) ?? []) {
    listener({ detail, ...fields });
  }
}

export function resetInertiaMocks(): void {
  for (const method of Object.values(router)) {
    method.mockClear();
  }
  http.onRequest.mockClear();
  http.onResponse.mockClear();
  createInertiaApp.mockClear();
  requestHandlers.clear();
  responseHandlers.clear();
  Object.assign(page, defaultPage());
  for (const callback of resetCallbacks) {
    callback();
  }
}
