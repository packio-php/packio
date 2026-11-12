import { spawn, type ChildProcess } from "node:child_process";
import { once } from "node:events";
import { afterAll, beforeAll, expect, test } from "vitest";

const port = 13715;
const baseUrl = `http://127.0.0.1:${port}`;
let server: ChildProcess;
let stderr = "";

async function waitForHealth(): Promise<void> {
  for (let attempt = 0; attempt < 50; attempt += 1) {
    try {
      const response = await fetch(`${baseUrl}/health`);
      if (response.ok) {
        return;
      }
    } catch {
      // The runtime has not bound its port yet.
    }

    await new Promise((resolve) => setTimeout(resolve, 100));
  }

  throw new Error("SSR runtime did not become healthy.");
}

beforeAll(async () => {
  server = spawn("node", ["data/ssr/ssr.mjs"], {
    env: {
      ...process.env,
      INERTIA_SSR_HOST: "127.0.0.1",
      INERTIA_SSR_PORT: String(port),
    },
    stdio: "pipe",
  });
  server.stderr?.setEncoding("utf8");
  server.stderr?.on("data", (chunk: string) => {
    stderr += chunk;
  });
  await waitForHealth();
});

afterAll(async () => {
  if (server.exitCode === null) {
    server.kill("SIGTERM");
    await once(server, "exit");
  }
});

test("renders a representative v3 page through the built SSR runtime", async () => {
  const response = await fetch(`${baseUrl}/render`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      component: "Landing",
      props: {
        product_name: "Packio",
        tagline: "Private packages",
      },
      url: "/",
      version: null,
    }),
  });

  expect(response.status).toBe(200);
  const rendered = (await response.json()) as {
    body?: string;
    head?: string[];
  };
  expect(rendered.body).toEqual(expect.any(String));
  expect(rendered.head).toEqual(expect.any(Array));

  const pageScripts = [
    ...rendered.body!.matchAll(
      /<script\s+data-page="app"\s+type="application\/json">(?<page>.*?)<\/script>/gs,
    ),
  ];
  expect(pageScripts).toHaveLength(1);
  expect(JSON.parse(pageScripts[0].groups!.page)).toMatchObject({
    component: "Landing",
    props: { product_name: "Packio" },
    url: "/",
    version: null,
  });
  expect(
    rendered.body!.match(/<div\s+data-server-rendered="true"\s+id="app">/g),
  ).toHaveLength(1);
  expect(rendered.body).toContain("Private packages");
  expect(rendered.head!.join("\n")).toContain(
    "<title>Packio - Private packages</title>",
  );
});

test("renders an admin page with the app-shell structure through the built SSR runtime", async () => {
  const response = await fetch(`${baseUrl}/render`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      component: "Admin/Users",
      props: {
        auth: {},
        users: [],
      },
      url: "/admin/users",
      version: null,
    }),
  });

  expect(response.status).toBe(200);
  const rendered = (await response.json()) as {
    body?: string;
    head?: string[];
  };
  expect(rendered.body).toContain('class="admin-content-scroll');
  expect(rendered.body).toContain('class="admin-content-inner');
  expect(rendered.body).toContain("max-w-[80rem]");
  expect(rendered.head!.join("\n")).toContain("<title>Admin Users</title>");
});

test("failed renders log the page component and URL without the request body", async () => {
  const post = (body: string) =>
    fetch(`${baseUrl}/render`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body,
    });
  const waitForLog = async (text: string) => {
    for (let i = 0; i < 50 && !stderr.includes(text); i += 1) {
      await new Promise((resolve) => setTimeout(resolve, 50));
    }
  };

  const failed = await post(
    JSON.stringify({
      component: "Missing/Page",
      props: { auth: { user: { email: "leak@example.test" } } },
      url: "/x",
      version: null,
    }),
  );
  expect(failed.status).toBe(500);
  expect(await failed.json()).toEqual({ error: "SSR render failed" });
  await waitForLog("Missing/Page");
  expect(stderr).toContain("SSR render failed.");
  expect(stderr).toContain("Missing/Page");
  expect(stderr).toContain("/x");
  expect(stderr).not.toContain("leak@example.test");

  const invalid = await post("not json");
  expect(invalid.status).toBe(500);
  expect(await invalid.json()).toEqual({ error: "SSR render failed" });
  await new Promise((resolve) => setTimeout(resolve, 200));
  expect(stderr).not.toContain("not json");
});

test("server-rendered app shell highlights the current section", async () => {
  const acme = { slug: "acme", name: "Acme" };
  const response = await fetch(`${baseUrl}/render`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      component: "Packages/Index",
      props: {
        auth: {
          check: true,
          user: { id: "u", email: "a@b.c", display_name: "A", roles: [] },
        },
        organization: acme,
        organizations: [acme],
        current_membership: { role: "owner" },
        sections: [],
      },
      url: "/organization/acme/packages?page=2",
      version: null,
    }),
  });

  expect(response.status).toBe(200);
  const { body } = (await response.json()) as { body: string };
  const linkTo = (href: string) =>
    [...body.matchAll(/<a\b[^>]*>/g)]
      .map((match) => match[0])
      .filter((tag) => tag.includes(`href="${href}"`));
  const packages = linkTo("/organization/acme/packages");
  const sources = linkTo("/organization/acme/sources");
  expect(packages.length).toBeGreaterThan(0);
  expect(sources.length).toBeGreaterThan(0);
  for (const tag of packages) {
    expect(tag).toContain("background-color: var(--accent-soft)");
  }
  for (const tag of sources) {
    expect(tag).not.toContain("background-color: var(--accent-soft)");
  }
});
