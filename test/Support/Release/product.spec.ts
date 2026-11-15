// Scenarios for product.test.sh. The script runs them one tag at a time and changes the stack in
// between (starts the worker, grants admin, recreates the worker), so the order below is the order of use.
import { randomUUID } from "node:crypto";
import { readFileSync, writeFileSync } from "node:fs";
import {
  expect,
  test,
  type APIRequestContext,
  type APIResponse,
  type Page,
} from "@playwright/test";

const stateDir = process.env.PRODUCT_STATE_DIR!;
const slug = process.env.PRODUCT_ORG_SLUG!;
const app = "https://app.fixture.test";
const registry = `https://${slug}.registry.fixture.test`;
const provider = "https://provider.fixture.test/git/private-package";
const packageName = "packio-fixture/private-package";
const session = `${stateDir}/session.json`;
const uuid = /^[0-9a-f-]{36}$/i;

const load = (name: string) =>
  JSON.parse(readFileSync(`${stateDir}/${name}.json`, "utf8"));
const save = (name: string, value: unknown) =>
  writeFileSync(`${stateDir}/${name}.json`, JSON.stringify(value));

let pageErrors: string[] = [];
test.beforeEach(({ page }) => {
  pageErrors = [];
  page.on("pageerror", (error) => pageErrors.push(error.message));
});
test.afterEach(() => {
  expect(pageErrors, "pages must run without script errors").toEqual([]);
});

// Full load, so the Inertia page data in the document is the server's answer for this URL.
async function open(page: Page, url?: string) {
  const response = url
    ? await page.goto(url, { waitUntil: "domcontentloaded" })
    : await page.reload({ waitUntil: "domcontentloaded" });
  await page
    .locator("html:not([data-theme-booting])")
    .waitFor({ state: "attached" });
  return response!;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- page props differ per page
async function props(page: Page): Promise<any> {
  const data = await page
    .locator('script[data-page="app"]')
    .evaluate((element) => JSON.parse(element.textContent ?? "{}"));
  return data.props ?? {};
}

async function mailCode(email: string, timeout: number): Promise<string> {
  const deadline = Date.now() + timeout;
  while (Date.now() < deadline) {
    const list = await (
      await fetch("http://mailpit:8025/api/v1/messages")
    ).json();
    const message = list.messages?.find(
      (item: { To?: { Address?: string }[] }) =>
        item.To?.some((to) => to.Address === email),
    );
    if (message) {
      const detail = await (
        await fetch(`http://mailpit:8025/api/v1/message/${message.ID}`)
      ).json();
      const codes = new Set((detail.Text as string).match(/\b\d{6}\b/g));
      expect(codes.size, "the mail carries exactly one verification code").toBe(
        1,
      );
      return [...codes][0];
    }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
  throw new Error("authentication mail did not arrive");
}

async function register(page: Page, email: string) {
  const response = await open(page, `${app}/auth/register`);
  expect(response.status()).toBe(200);
  expect(await response.text(), "the form is rendered on the server").toMatch(
    /<input[^>]*name=["']email["']/i,
  );
  await page.locator('input[name="display_name"]').fill("Fixture User");
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill("Fixture!Password2026");
  await page
    .locator('input[name="password_confirmation"]')
    .fill("Fixture!Password2026");
  await page.getByRole("button", { name: /create account/i }).click();
  await page.waitForURL((url) => url.pathname === "/auth/verification");
  await expect(page.locator('input[name="code"]')).toHaveCount(1);
}

test("authentication mail is delivered only by the worker @no-worker", async ({
  page,
}) => {
  const email = `stopped-${randomUUID()}@example.invalid`;
  await register(page, email);
  await expect(mailCode(email, 5_000)).rejects.toThrow("did not arrive");
});

test("registration, mail verification and no admin access @identity", async ({
  page,
}) => {
  const email = `user-${randomUUID()}@example.invalid`;
  await register(page, email);
  await page.locator('input[name="code"]').fill(await mailCode(email, 90_000));
  await page.getByRole("button", { name: "Verify" }).click();
  await page.waitForURL((url) => url.pathname === "/organizations");
  await open(page);
  expect((await props(page)).auth?.user?.id).toMatch(uuid);

  const denied = await open(page, `${app}/admin`);
  expect(
    denied.status() === 403 || new URL(page.url()).pathname !== "/admin",
    "an ordinary user is kept out",
  ).toBe(true);

  await page.context().storageState({ path: session });
  save("user", { email });
});

test.describe("signed in", () => {
  test.use({ storageState: session });

  async function addSource(page: Page, repository: string): Promise<string> {
    await open(page, `${app}/organization/${slug}/sources`);
    await page.getByRole("button", { name: "Add source" }).click();
    await page.getByRole("button", { name: /Generic Git/ }).click();
    await page.locator('input[name="repository_url"]').fill(repository);
    await page
      .locator('input[name="composer_json_path"]')
      .fill("composer.json");
    await page.locator('select[name="visibility"]').selectOption("private");
    await page.getByRole("button", { name: /^Connect 1 source$/ }).click();
    await page.waitForURL((url) => url.pathname.endsWith("/sources"));
    let id: string | undefined;
    await expect(async () => {
      await open(page);
      const sources: { id: string; url?: string; canonical_url?: string }[] =
        (await props(page)).sources ?? [];
      id = sources.find(
        (item) => item.url === repository || item.canonical_url === repository,
      )?.id;
      expect(id).toMatch(uuid);
    }).toPass({ timeout: 90_000 });
    return id!;
  }

  // Posts as the signed-in user with the CSRF token of the page that is open.
  async function post(
    page: Page,
    path: string,
    form: Record<string, string> = {},
  ) {
    const response = await page.request.post(app + path, {
      form,
      headers: { "X-CSRF-Token": (await props(page)).csrf.token, Origin: app },
      maxRedirects: 0,
    });
    expect(response.status(), `POST ${path}`).toBe(302);
  }

  async function issueToken(page: Page, organization: string, name: string) {
    const path = `/organization/${organization}/tokens`;
    await open(page, app + path);
    await post(page, path, { name });
    await open(page);
    const tokens: { id: string; name: string; plain_token?: string }[] =
      (await props(page)).tokens ?? [];
    const token = tokens.find((item) => item.name === name);
    expect(
      token?.plain_token,
      `token ${name} is shown once after it is issued`,
    ).toBeTruthy();
    return { id: token!.id, value: token!.plain_token! };
  }

  test("admin access, organization and private source @organization", async ({
    page,
  }) => {
    const admin = await open(page, `${app}/admin`);
    expect(admin.status(), "user:make-admin grants the admin area").toBe(200);
    await expect(
      page.getByText(/global packio controls|system overview/i).first(),
    ).toBeVisible();

    await open(page, `${app}/organizations`);
    await page.locator('input[name="name"]').fill("Fixture Org");
    await page
      .getByRole("button", { name: "Create organization", exact: true })
      .click();
    await page.waitForURL(
      (url) => url.pathname === `/organization/${slug}/packages`,
    );

    save("sources", {
      failed: await addSource(
        page,
        `https://provider.fixture.test/git/missing-${randomUUID()}`,
      ),
      private: await addSource(page, provider),
    });
  });

  test("re-sync from the source page @resync", async ({ page }) => {
    const path = `/organization/${slug}/sources/${load("sources").private}`;
    await open(page, app + path);
    const [response] = await Promise.all([
      page.waitForResponse(
        (candidate) => candidate.url() === `${app}${path}/resync`,
      ),
      page.getByRole("button", { name: "Re-sync source" }).click(),
    ]);
    expect(response.status()).toBe(302);
    await expect(
      page.getByText(
        "Re-sync scheduled. Refresh this page to see the latest sync status.",
      ),
    ).toBeVisible();
    expect(new URL(page.url()).pathname).toBe(path);
  });

  test("registry serves the private package only to its own tokens @registry", async ({
    page,
    request,
  }) => {
    const get = (path: string, token?: string) =>
      request.get(registry + path, {
        headers: token
          ? {
              Authorization: `Basic ${Buffer.from(`token:${token}`).toString("base64")}`,
            }
          : {},
      });
    const denied = async (response: APIResponse, status: number) => {
      expect(response.status()).toBe(status);
      expect(await response.text()).not.toContain(packageName);
    };

    await open(page, `${app}/organizations`);
    await post(page, "/organizations", { name: "Foreign Org" });
    await open(page);
    const organizations: { name: string; slug: string }[] = (await props(page))
      .organizations;
    const foreignSlug = organizations.find(
      (item) => item.name === "Foreign Org",
    )!.slug;
    expect(foreignSlug).not.toBe(slug);

    const install = await issueToken(page, slug, "install");
    const revoked = await issueToken(page, slug, "revoked");
    const foreign = await issueToken(page, foreignSlug, "foreign");

    const metadataPath = `/p2/${packageName}`;
    const metadata = await get(metadataPath, install.value);
    expect(metadata.status()).toBe(200);
    const release = (await metadata.json()).packages[packageName][0];
    expect(release.version).toBe("1.0.0");
    expect(
      release.dist.url.startsWith(`${registry}/`),
      "dist is served by the organization's registry host",
    ).toBe(true);
    const distPath = new URL(release.dist.url).pathname;

    const list = await get("/private/packages.json", install.value);
    expect(list.status()).toBe(200);
    expect((await list.json())["available-packages"]).toContain(packageName);

    const dist = await get(distPath, install.value);
    expect(dist.status()).toBe(200);
    expect(
      (await dist.body()).subarray(0, 4).toString("latin1"),
      "dist is a zip archive",
    ).toBe("PK\u0003\u0004");

    for (const path of [metadataPath, distPath]) {
      await denied(await get(path), 401);
      await denied(await get(path, foreign.value), 403);
      expect(
        (await get(path, revoked.value)).status(),
        "the token works until it is revoked",
      ).toBe(200);
    }
    await open(page, `${app}/organization/${slug}/tokens`);
    await post(page, `/organization/${slug}/tokens/${revoked.id}/revoke`);
    for (const path of [metadataPath, distPath]) {
      await denied(await get(path, revoked.value), 401);
    }

    save("tokens", {
      install: install.value,
      revoked: revoked.value,
      foreign: foreign.value,
    });
  });
});

// The edge is docs/examples/reverse-proxy/Caddyfile; these requests come from a client that lies in its headers.
test.describe("documented edge @edge", () => {
  const forged = {
    "X-Forwarded-Proto": "http",
    "X-Forwarded-Host": "evil.example",
    "X-Forwarded-Port": "81",
    Forwarded: "for=198.51.100.7;proto=http;host=evil.example",
  };

  async function loginPage(
    request: APIRequestContext,
    origin = app,
    headers: Record<string, string> = {},
  ) {
    const response = await request.get(`${origin}/auth/login`, { headers });
    const html = await response.text();
    const data = /<script\b[^>]*data-page="app"[^>]*>(.*?)<\/script>/s.exec(
      html,
    );
    expect(data, "the response carries an Inertia page").not.toBeNull();
    return { response, html, page: JSON.parse(data![1]) };
  }

  const login = (
    request: APIRequestContext,
    csrf: string,
    headers: Record<string, string>,
    origin = app,
  ) =>
    request.post(`${origin}/auth/login`, {
      form: {
        email: `edge-${randomUUID()}@example.invalid`,
        password: randomUUID(),
      },
      headers: { "X-CSRF-Token": csrf, ...headers },
      maxRedirects: 0,
    });

  test("security headers, server rendering and session cookie", async ({
    request,
  }) => {
    const { response, html } = await loginPage(request);
    expect(response.status()).toBe(200);
    const headers = response.headers();
    expect(headers["strict-transport-security"]).toMatch(/max-age=\d+/);
    // The app and the edge both send these two, so the values arrive joined.
    expect(headers["x-content-type-options"]).toContain("nosniff");
    expect(headers["x-frame-options"]).toContain("DENY");
    expect(headers["content-security-policy-report-only"]).toBeTruthy();
    expect(
      headers["content-security-policy"],
      "CSP is not enforced yet",
    ).toBeUndefined();
    expect(html, "the page root is rendered on the server").toMatch(
      /<div[^>]+id="app"[^>]*>\s*</,
    );
    const cookie =
      response
        .headersArray()
        .find((header) => header.name.toLowerCase() === "set-cookie")?.value ??
      "";
    expect(cookie).toMatch(/;\s*secure/i);
    expect(cookie).toMatch(/;\s*httponly/i);
    expect(cookie).toMatch(/;\s*samesite=lax/i);
  });

  test("registry URLs ignore forged forwarding headers", async ({
    request,
  }) => {
    const authorization = `Basic ${Buffer.from(`token:${load("tokens").install}`).toString("base64")}`;
    const response = await request.get(`${registry}/private/packages.json`, {
      headers: { ...forged, Authorization: authorization },
    });
    expect(response.status()).toBe(200);
    const body = await response.text();
    expect(body).toContain(`${registry}/p2/%package%`);
    expect(body).not.toContain("evil.example");
    expect(body).not.toContain("http://");
  });

  test("foreign origins fail CSRF whatever the forwarding headers say", async ({
    request,
  }) => {
    const { page } = await loginPage(request);
    const csrf = page.props.csrf.token;
    const lies = {
      "X-Forwarded-Proto": "https",
      "X-Forwarded-Host": "evil.example",
      "X-Forwarded-Port": "443",
    };
    for (const [origin, status] of [
      ["http://app.fixture.test", 419],
      ["https://app.fixture.test:444", 403],
      ["https://evil.example", 403],
    ] as const) {
      // CORS answers a foreign Origin first; a Referer alone reaches the CSRF check.
      expect(
        (await login(request, csrf, { ...lies, Origin: origin })).status(),
        `Origin ${origin}`,
      ).toBe(status);
      expect(
        (
          await login(request, csrf, {
            ...lies,
            Referer: `${origin}/auth/login`,
          })
        ).status(),
      ).toBe(419);
    }
  });

  test("login throttling counts the real client, not a forged address", async ({
    request,
  }) => {
    let { page } = await loginPage(request);
    for (const attempt of [1, 2]) {
      const response = await login(request, page.props.csrf.token, {
        ...forged,
        Origin: app,
        "X-Forwarded-For": `198.51.100.${attempt}, 203.0.113.${attempt}`,
        "CF-Connecting-IP": `192.0.2.${attempt}`,
        "X-Real-IP": `192.0.2.1${attempt}`,
      });
      expect(response.status()).toBe(302);
      ({ page } = await loginPage(request));
      expect(
        page.props.errors?.email,
        `attempt ${attempt} reaches authentication`,
      ).toEqual(["Invalid credentials."]);
    }
    const blocked = await login(request, page.props.csrf.token, {
      ...forged,
      Origin: app,
      "X-Forwarded-For": "203.0.113.99",
      "CF-Connecting-IP": "192.0.2.99",
      "X-Real-IP": "192.0.2.98",
    });
    expect(blocked.status()).toBe(302);
    expect(blocked.headers()["retry-after"]).toBe("900");
    ({ page } = await loginPage(request));
    expect(page.flash?.error, "the third attempt is throttled").toBeTruthy();
    expect(
      page.props.errors?.email,
      "the third attempt never reaches authentication",
    ).toBeUndefined();
  });

  test("a client that bypasses the edge cannot pose as HTTPS", async ({
    request,
  }) => {
    // This container is not the trusted proxy, so the app must ignore what it claims.
    const direct = "http://app";
    const host = { Host: "app.fixture.test" };
    const { response, page } = await loginPage(request, direct, host);
    expect(response.status()).toBe(200);
    const forgedLogin = await login(
      request,
      page.props.csrf.token,
      {
        ...host,
        Origin: app,
        "X-Forwarded-Proto": "https",
        "X-Forwarded-Host": "app.fixture.test",
        "X-Forwarded-Port": "443",
      },
      direct,
    );
    expect(forgedLogin.status()).toBe(419);
  });
});

test("login page hydrates and loads Turnstile without CSP violations @turnstile", async ({
  page,
}) => {
  await page.addInitScript(() => {
    const violations: string[] = [];
    Object.assign(window, { cspViolations: violations });
    document.addEventListener("securitypolicyviolation", (event) => {
      violations.push(`${event.effectiveDirective} ${event.blockedURI}`);
    });
  });
  const response = await open(page, `${app}/auth/login`);
  expect(response.status()).toBe(200);
  expect(
    await response.text(),
    "the login form is rendered on the server",
  ).toMatch(/<input\b[^>]*type="email"/i);
  await expect(page.locator("#cloudflare-turnstile-script")).toHaveAttribute(
    "src",
    /^https:\/\/challenges\.cloudflare\.com\/turnstile\/v0\/api\.js/,
    { timeout: 30_000 },
  );
  await expect(page.locator('input[name="cf-turnstile-response"]')).toHaveValue(
    /.+/,
    { timeout: 60_000 },
  );
  expect(
    await page.evaluate(
      () => (window as unknown as { cspViolations: string[] }).cspViolations,
    ),
  ).toEqual([]);

  await page.locator('a[href*="/auth/register"]').first().click();
  await page.waitForURL((url) => url.pathname === "/auth/register");
  await expect(page.locator('input[type="email"]')).toBeVisible();
});
