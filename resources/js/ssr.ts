import { createServer, type IncomingMessage, type ServerResponse } from "http";
import { Buffer } from "buffer";
import process from "process";
import type { Page } from "@inertiajs/core";
import { createInertiaApp } from "@inertiajs/svelte";
import { render } from "svelte/server";
import { resolveSsrPage } from "@/inertia/resolveSsr";

const maxPayloadBytes = 1024 * 1024;
const env = process.env as Record<string, string | undefined>;
const consoleMethods = {
  info: console.info,
  warn: console.warn,
  error: console.error,
} as const;

function log(
  level: "info" | "warn" | "error",
  message: string,
  details?: unknown,
) {
  const timestamp = new Date().toISOString();
  const write = consoleMethods[level];

  if (details === undefined) {
    write(`[ssr] ${timestamp} ${message}`);
    return;
  }

  write(`[ssr] ${timestamp} ${message}`, details);
}

function describeError(error: unknown) {
  if (error instanceof Error) {
    return {
      name: error.name,
      message: error.message,
      stack: error.stack,
    };
  }

  return {
    value: String(error),
  };
}

function getHost(): string {
  return env["INERTIA_SSR_HOST"] ?? env["HOST"] ?? "127.0.0.1";
}

function getPort(): number {
  const configuredPort = env["INERTIA_SSR_PORT"] ?? env["PORT"] ?? "13714";
  const parsedPort = Number.parseInt(configuredPort, 10);

  return Number.isInteger(parsedPort) && parsedPort > 0 ? parsedPort : 13714;
}

const host = getHost();
const port = getPort();

async function renderPage(page: Page) {
  return createInertiaApp({
    page,
    resolve: resolveSsrPage,
    setup({ App, props }) {
      return render(App, { props });
    },
  });
}

function writeJson(
  response: ServerResponse,
  status: number,
  payload: unknown,
): void {
  response.writeHead(status, {
    "Content-Type": "application/json",
    Server: "Inertia SSR",
  });
  response.end(JSON.stringify(payload));
}

createServer((request: IncomingMessage, response: ServerResponse) => {
  const startedAt = Date.now();

  if (request.url === "/health" && request.method === "GET") {
    writeJson(response, 200, { status: "OK", timestamp: Date.now() });
    return;
  }

  if (request.url !== "/render" || request.method !== "POST") {
    writeJson(response, 404, { status: "NOT_FOUND", timestamp: Date.now() });
    return;
  }

  let size = 0;
  let body = "";

  request.setEncoding("utf8");

  request.on("data", (chunk: string) => {
    size += Buffer.byteLength(chunk);

    if (size > maxPayloadBytes) {
      writeJson(response, 413, { error: "Payload too large" });
      request.destroy();
      return;
    }

    body += chunk;
  });

  request.on("end", async () => {
    let page: Page | undefined;

    try {
      try {
        page = JSON.parse(body) as Page;
      } catch {
        // The engine's SyntaxError message quotes the body, so do not rethrow it.
        throw new Error("Invalid JSON request body");
      }

      const rendered = await renderPage(page);

      log("info", "SSR render succeeded.", {
        component: page.component,
        durationMs: Date.now() - startedAt,
      });
      writeJson(response, 200, rendered);
    } catch (error) {
      log("error", "SSR render failed.", {
        component: page?.component,
        pageUrl: page?.url,
        url: request.url,
        method: request.method,
        durationMs: Date.now() - startedAt,
        error: describeError(error),
      });
      writeJson(response, 500, { error: "SSR render failed" });
    }
  });

  request.on("error", (error: Error) => {
    log("error", "SSR request stream failed.", {
      url: request.url,
      method: request.method,
      durationMs: Date.now() - startedAt,
      error: describeError(error),
    });

    if (!response.headersSent) {
      writeJson(response, 500, { error: "SSR request failed" });
    }
  });
}).listen(port, host, () => {
  log("info", `Starting SSR server on ${host}:${port}.`);
  log("info", "Inertia SSR server started.");
});

process.on("uncaughtException", (error: Error) => {
  log("error", "Uncaught exception in SSR runtime.", describeError(error));
  // Process state is undefined after this; PHP falls back to client rendering while SSR is down, the supervisor restarts it.
  process.exit(1);
});

process.on("unhandledRejection", (reason: unknown) => {
  log(
    "error",
    "Unhandled promise rejection in SSR runtime.",
    describeError(reason),
  );
});
