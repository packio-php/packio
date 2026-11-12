import { defineConfig, type Plugin, type ViteDevServer } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import tailwindcss from "@tailwindcss/vite";

type Page = {
  component: "Home" | "Next" | "Shell";
  props: Record<string, unknown>;
  url: string;
  version: null;
};

const csrfToken = "csrf-0";

function responsePage(
  pathname: string,
  requestHeaders: Record<string, string | string[] | undefined>,
): Page {
  const partialProps = String(requestHeaders["x-inertia-partial-data"] ?? "")
    .split(",")
    .map((value) => value.trim())
    .filter(Boolean);
  const props: Record<string, unknown> = {
    csrf: { token: csrfToken },
    errors: {},
    jobs: ["initial-job"],
    old: {},
    auth: { check: true, user: { is_admin: true, email: "test@example.test" } },
    organization: { slug: "test", name: "Test" },
    current_membership: { role: "owner" },
  };

  if (partialProps.includes("jobs")) {
    props.jobs = ["reloaded-job"];
    delete props.csrf;
    delete props.errors;
    delete props.old;
  }

  return {
    component:
      pathname.startsWith("/admin") || pathname.startsWith("/organization/")
        ? "Shell"
        : pathname === "/next"
          ? "Next"
          : "Home",
    props,
    url: pathname,
    version: null,
  };
}

function inertiaFixture(): Plugin {
  return {
    name: "inertia-v3-browser-fixture",
    configureServer(server: ViteDevServer) {
      server.middlewares.use(async (request, response, next) => {
        const url = new URL(request.url ?? "/", "http://127.0.0.1");
        const assetRequest =
          url.pathname.startsWith("/@") ||
          url.pathname.endsWith(".css") ||
          /\.[cm]?[jt]s$|\.svelte$/.test(url.pathname);
        if (assetRequest) {
          next();

          return;
        }

        if (request.headers["x-inertia"] === "true") {
          if (url.pathname === "/expired") {
            response.writeHead(419, { "Content-Type": "text/html" });
            response.end("<h1>Expired session</h1>");

            return;
          }

          if (request.method === "POST") {
            const csrf = request.headers["x-csrf-token"];
            if (csrf !== csrfToken) {
              response.writeHead(419, { "Content-Type": "text/html" });
              response.end("<h1>Expired session</h1>");

              return;
            }

            const chunks: Buffer[] = [];
            for await (const chunk of request) {
              chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
            }
            const body = Buffer.concat(chunks).toString();
            const contentType = String(request.headers["content-type"] ?? "");
            const email = contentType.includes("application/json")
              ? ((JSON.parse(body) as { email?: string }).email ?? "")
              : (new URLSearchParams(body).get("email") ?? "");
            const page = responsePage("/", request.headers);
            page.props.old = { email };
            page.props.errors = email.includes("@")
              ? {}
              : { email: "A valid email is required." };

            response.writeHead(200, {
              "Content-Type": "application/json",
              "X-Inertia": "true",
            });
            response.end(JSON.stringify(page));

            return;
          }

          response.writeHead(200, {
            "Content-Type": "application/json",
            "X-Inertia": "true",
          });
          response.end(
            JSON.stringify(responsePage(url.pathname, request.headers)),
          );

          return;
        }

        if (
          request.method !== "GET" ||
          url.pathname.startsWith("/node_modules/")
        ) {
          next();

          return;
        }

        const page = responsePage(url.pathname, request.headers);
        if (url.pathname === "/csr") {
          response.writeHead(200, { "Content-Type": "text/html" });
          response.end(
            `<!doctype html><html><body><script data-page="app" type="application/json">${JSON.stringify(page)}</script><div id="app"></div><script type="module" src="/main.ts"></script></body></html>`,
          );

          return;
        }

        try {
          const { renderPage } = await server.ssrLoadModule("/ssr.ts");
          const rendered = await renderPage(page);
          response.writeHead(200, { "Content-Type": "text/html" });
          response.end(
            `<!doctype html><html><head>${rendered.head.join("\n")}</head><body>${rendered.body}<script type="module" src="/main.ts"></script></body></html>`,
          );
        } catch (error) {
          next(error as Error);
        }
      });
    },
  };
}

export default defineConfig({
  root: "resources/js/e2e/fixture",
  plugins: [tailwindcss(), svelte(), inertiaFixture()],
  optimizeDeps: {
    include: ["@inertiajs/svelte", "svelte"],
  },
  server: {
    hmr: false,
  },
  resolve: {
    conditions: ["browser"],
    alias: {
      "@": new URL("./resources/js", import.meta.url).pathname,
    },
  },
});
