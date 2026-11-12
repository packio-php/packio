import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { firefox, type Browser, type BrowserContext, type Page } from "playwright";

const phase = process.argv[2];
const privateDir = process.env.PRODUCT_PRIVATE_DIR!;
const appUrl = process.env.PRODUCT_APP_URL!;
const mailpitUrl = process.env.PRODUCT_MAILPIT_URL!;
const providerUrl = process.env.PRODUCT_PROVIDER_URL!;
let step = "startup";

type Source = { id?: string; url?: string; canonical_url?: string };
type AppPageData = {
  props?: {
    auth?: { user?: { id?: string } };
    organization?: { id?: string; slug?: string };
    sources?: Source[];
  };
};

function extractVerificationCode(body: string): string {
  const matches = body.match(/\b\d{6}\b/g) ?? [];
  const uniqueCodes = [...new Set(matches)];
  assert.equal(uniqueCodes.length, 1, "auth mail must contain one distinct verification code");
  return uniqueCodes[0];
}

function runMailParserSelfTest(): boolean {
  try {
    if (extractVerificationCode("Use code 123456; again 123456") !== "123456") {
      throw new Error("unexpected code result");
    }
  } catch {
    console.error("FAIL: duplicate-identical synthetic auth codes");
    return false;
  }
  try {
    extractVerificationCode("Use code 123456; alternative 654321");
    console.error("FAIL: ambiguous synthetic auth codes were accepted");
    return false;
  } catch {
    // Ambiguous values must be rejected.
  }
  try {
    extractVerificationCode("No verification code");
    console.error("FAIL: missing synthetic auth code was accepted");
    return false;
  } catch {
    // Missing values must be rejected.
  }
  console.log("PASS: synthetic auth mail parser cases");
  return true;
}

if (phase === "--mail-parser-test") {
  process.exit(runMailParserSelfTest() ? 0 : 1);
}

const browserErrorTypes = new Set(["AssertionError", "Error", "MissingAuthMailError", "RangeError",
  "ReferenceError", "SyntaxError", "TimeoutError", "TypeError", "UnknownError"]);

function safeErrorType(error: unknown): string {
  const name = error instanceof Error ? error.name : "UnknownError";
  return browserErrorTypes.has(name) ? name : "UnknownError";
}

type ResyncDiagnostic = {
  http_status?: number;
  redirect_route_match?: boolean;
  flash_visible?: boolean;
  final_route_match?: boolean;
  browser_error_count?: number;
};
type SourceDiagnostic = {
  http_status?: number;
  sources_route_match: boolean;
  form_invalid_control_present: boolean;
  browser_error_count: number;
};
let resyncDiagnostic: ResyncDiagnostic | undefined;
let sourcePageForFailure: Page | undefined;
let sourceErrorsForFailure: string[] | undefined;
let sourceHttpStatus: number | undefined;

async function sourceFailureFacts(): Promise<SourceDiagnostic | undefined> {
  if (!sourcePageForFailure || !sourceErrorsForFailure) return undefined;
  const facts: SourceDiagnostic = {
    sources_route_match: false,
    form_invalid_control_present: false,
    browser_error_count: sourceErrorsForFailure.length,
    ...(sourceHttpStatus !== undefined ? { http_status: sourceHttpStatus } : {}),
  };
  try {
    facts.sources_route_match = /^\/organization\/[^/]+\/sources\/?$/.test(
      new URL(sourcePageForFailure.url()).pathname,
    );
  } catch {
    return undefined;
  }
  try {
    facts.form_invalid_control_present = (await sourcePageForFailure.locator("input:invalid, select:invalid").count()) > 0;
  } catch {
    return undefined;
  }
  return facts;
}

async function recordFailure(failureStep: string, errorType: string): Promise<void> {
  let source: SourceDiagnostic | undefined;
  if (phase === "failed-provider-source" || phase === "private-source") {
    try {
      source = await sourceFailureFacts();
    } catch {
      // Sampling must never replace the original browser failure.
    }
  }
  const diagnosticField = resyncDiagnosticSteps[failureStep];
  const includeResync = phase === "resync" && diagnosticField && resyncDiagnostic?.[diagnosticField] !== undefined;
  const failure = { phase, step: failureStep, error_type: errorType,
    ...(includeResync ? { resync: resyncDiagnostic } : {}),
    ...(source ? { source } : {}) };
  await writeFile(path.join(privateDir, "browser-failure.json"),
    JSON.stringify(failure), { mode: 0o600 });
}

let browser: Browser;
let failureRecorded = false;
const resyncDiagnosticSteps: Record<string, keyof ResyncDiagnostic> = {
  worker_recreation_resync_http_status: "http_status",
  worker_recreation_resync_redirect: "redirect_route_match",
  worker_recreation_resync_flash: "flash_visible",
  worker_recreation_resync_final_route: "final_route_match",
  worker_recreation_resync_browser_errors: "browser_error_count",
};
try {
  step = "browser_connect";
  browser = await firefox.connect(process.env.PRODUCT_BROWSER_ENDPOINT!);
} catch (error) {
  const errorType = safeErrorType(error);
  await recordFailure("browser_connect", errorType);
  console.error(`FAIL: product browser ${phase} at browser_connect (${errorType})`);
  process.exit(1);
}

function appPageProps(page: Page): Promise<AppPageData> {
  return page.locator('script[data-page="app"]').evaluate((element: HTMLScriptElement) => JSON.parse(element.textContent ?? "{}"));
}

async function openContext(state?: string): Promise<{ context: BrowserContext; page: Page; errors: string[] }> {
  const context = await browser.newContext({ ...(state ? { storageState: state } : {}) });
  const page = await context.newPage();
  page.setDefaultTimeout(15000);
  const errors: string[] = [];
  page.on("pageerror", (error: Error) => errors.push(error.message));
  return { context, page, errors };
}

async function loadHydrated(page: Page, url?: string) {
  const response = url
    ? await page.goto(url, { waitUntil: "domcontentloaded" })
    : await page.reload({ waitUntil: "domcontentloaded" });
  await page.locator("html:not([data-theme-booting])").waitFor({ state: "attached" });
  return response;
}

class MissingAuthMailError extends Error {}

async function mailCode(email: string, timeout = 90000): Promise<string> {
  const deadline = Date.now() + timeout;
  while (Date.now() < deadline) {
    const response = await fetch(`${mailpitUrl}/api/v1/messages`);
    assert.equal(response.status, 200);
    const page = await response.json() as { messages?: Array<{ ID: string; To?: Array<{ Address?: string }> }> };
    const message = page.messages?.find(item => item.To?.some(to => to.Address === email));
    if (message) {
      const detailResponse = await fetch(`${mailpitUrl}/api/v1/message/${encodeURIComponent(message.ID)}`);
      assert.equal(detailResponse.status, 200);
      const detail = await detailResponse.json() as { Text?: string; HTML?: string };
      const body = detail.Text || (detail.HTML ?? "").replace(/<[^>]*>/g, " ");
      return extractVerificationCode(body);
    }
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  throw new MissingAuthMailError("authentication mail did not arrive before the bounded deadline");
}

async function register(page: Page, email: string): Promise<void> {
  step = "registration_ssr_response";
  const response = await loadHydrated(page, `${appUrl}/auth/register`);
  assert.equal(response?.status(), 200, "registration route must return an SSR page");
  const html = await response!.text();
  assert.match(html, /<input[^>]*name=["']email["']/i, "registration email control must be present in the server response");
  assert.match(html, /<input[^>]*name=["']password["']/i, "registration password control must be present in the server response");
  assert.equal(await page.locator('input[name="email"]').count(), 1, "registration form must hydrate in the browser");
  step = "registration_form_fill";
  await page.locator('input[name="display_name"]').fill("E2 Fixture User");
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill("Fixture!Password2026");
  await page.locator('input[name="password_confirmation"]').fill("Fixture!Password2026");
  const submit = page.getByRole("button", { name: /create account/i });
  await submit.waitFor({ state: "visible" });
  assert.equal(await submit.isDisabled(), false, "fixture registration must not require an external Turnstile challenge");
  step = "registration_submit";
  await submit.click();
  step = "registration_redirect";
  await page.waitForURL(url => url.pathname === "/auth/verification");
  assert.equal(await page.locator('input[name="code"]').count(), 1, "registration must lead to the real verification page");
}

async function selectProvider(page: Page, repo: string): Promise<void> {
  step = "add_source_click";
  await page.getByRole("button", { name: "Add source" }).click();
  step = "generic_git_select";
  await page.getByRole("button", { name: /Generic Git/ }).click();
  step = "repository_url_fill";
  await page.locator('input[name="repository_url"]').fill(repo);
  step = "composer_json_path_fill";
  await page.locator('input[name="composer_json_path"]').fill("composer.json");
  step = "visibility_select";
  await page.locator('select[name="visibility"]').selectOption("private");
  const connect = page.getByRole("button", { name: /^Connect 1 source$/ });
  step = "connect_response_wait_click";
  const connectResponse = page.waitForResponse(response => response.url().endsWith("/sources/connect"))
    .then(response => {
      sourceHttpStatus = response.status();
      return response;
    });
  await Promise.all([
    connectResponse,
    connect.click(),
  ]);
  step = "sources_route_wait";
  await page.waitForURL(url => url.pathname.endsWith("/sources"));
}

async function waitForSource(page: Page, repo: string): Promise<Source> {
  step = "persisted_source_wait";
  const deadline = Date.now() + 90000;
  while (Date.now() < deadline) {
    await loadHydrated(page);
    const props = await appPageProps(page);
    const source = (props.props?.sources ?? []).find(item => item.url === repo || item.canonical_url === repo);
    if (source) return source;
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  throw new Error("persisted source did not appear before the bounded deadline");
}

async function closeAndAssert(errors: string[], context: BrowserContext, sourceFlow = false): Promise<void> {
  if (sourceFlow) step = "source_browser_errors_assert";
  assert.deepEqual(errors, [], "real SSR/CSR flow must not raise browser runtime errors");
  if (sourceFlow) step = "source_browser_close";
  await context.close();
}

try {
  if (phase === "tls-negative-control") {
    step = "browser_tls_negative_control";
    const context = await browser.newContext();
    const page = await context.newPage();
    await assert.rejects(
      page.goto(`${appUrl}/auth/login`, { waitUntil: "domcontentloaded", timeout: 10000 }),
      error => error instanceof Error && error.message.includes("SEC_ERROR_UNKNOWN_ISSUER"),
      "untrusted fixture CA must be rejected by strict Firefox TLS",
    );
    await context.close();
  } else if (phase === "identity") {
    step = "registration_ssr";
    const { context, page, errors } = await openContext();
    const email = `e2-${randomUUID()}@example.invalid`;
    await register(page, email);
    step = "authentication_mail";
    const code = await mailCode(email);
    step = "email_verification";
    await page.locator('input[name="code"]').fill(code);
    await page.getByRole("button", { name: "Verify" }).click();
    await page.waitForURL(url => url.pathname === "/organizations");
    await loadHydrated(page);
    const identity = await appPageProps(page);
    assert.match(identity.props?.auth?.user?.id ?? "", /^[0-9a-f-]{36}$/i, "verification must establish a signed-in user");
    step = "ordinary_user_admin_denial";
    const denied = await loadHydrated(page, `${appUrl}/admin`);
    const deniedPath = new URL(page.url()).pathname;
    assert.ok(denied?.status() === 403 || (denied?.status() === 200 && deniedPath !== "/admin"),
      "ordinary verified user must receive an authorization denial for the admin route");
    await context.storageState({ path: path.join(privateDir, "verified-user.json") });
    await writeFile(path.join(privateDir, "flow-state.json"), JSON.stringify({ email, user_id: identity.props.auth.user.id }), { mode: 0o600 });
    await closeAndAssert(errors, context);
  } else if (phase === "stopped-worker-control") {
    step = "stopped_worker_registration";
    const { context, page, errors } = await openContext();
    const email = `e2-stopped-${randomUUID()}@example.invalid`;
    await register(page, email);
    // A stopped worker leaves the queued auth operation undelivered; the real Mailpit
    // detector below owns the short bounded wait and expected failure.
    assert.equal(await page.locator('input[name="code"]').count(), 1, "without worker delivery the verification page remains incomplete");
    step = "stopped_worker_mail_detector";
    await assert.rejects(mailCode(email, 5000), MissingAuthMailError,
      "the real bounded auth-mail detector must fail when the worker is stopped");
    await closeAndAssert(errors, context);
  } else if (phase === "admin-and-org") {
    step = "admin_access_after_role_change";
    const { context, page, errors } = await openContext(path.join(privateDir, "verified-user.json"));
    const admin = await loadHydrated(page, `${appUrl}/admin`);
    assert.equal(admin?.status(), 200, "existing make-admin CLI must grant an HTTP 200 admin response");
    assert.ok(await page.getByText(/global packio controls|system overview/i).count() > 0,
      "admin response must render real admin content");
    step = "organization_page_render";
    await loadHydrated(page, `${appUrl}/organizations`);
    const nameInput = page.locator('input[name="name"]');
    assert.equal(await nameInput.count(), 1, "real organization creation page must render");
    step = "organization_form_fill";
    await nameInput.fill(`E2 ${randomUUID().slice(0, 8)}`);
    step = "organization_submit";
    await page.getByRole("button", { name: "Create organization", exact: true }).click();
    step = "organization_redirect";
    await page.waitForURL(url => url.pathname.includes("/organization/") && url.pathname.endsWith("/packages"));
    step = "organization_props";
    await loadHydrated(page);
    const orgPage = await appPageProps(page);
    const org = orgPage.props?.organization;
    assert.match(org?.id ?? "", /^[0-9a-f-]{36}$/i, "organization must be persisted through the UI flow");
    await writeFile(path.join(privateDir, "organization-state.json"), JSON.stringify({ user_id: orgPage.props.auth.user.id, organization_id: org.id, slug: org.slug }), { mode: 0o600 });
    await closeAndAssert(errors, context);
  } else if (phase === "failed-provider-source" || phase === "private-source") {
    step = phase === "failed-provider-source" ? "provider_failure_source_creation" : "private_source_creation";
    const org = JSON.parse(await readFile(path.join(privateDir, "organization-state.json"), "utf8"));
    const { context, page, errors } = await openContext(path.join(privateDir, "verified-user.json"));
    sourcePageForFailure = page;
    sourceErrorsForFailure = errors;
    const repo = phase === "failed-provider-source" ? `${providerUrl}/missing-e2-${randomUUID()}` : providerUrl;
    step = "source_page_goto";
    const sourcePageResponse = await loadHydrated(page, `${appUrl}/organization/${org.slug}/sources`);
    if (sourcePageResponse) sourceHttpStatus = sourcePageResponse.status();
    step = "source_page_hydration";
    assert.equal(await page.getByRole("button", { name: "Add source" }).count(), 1, "real source page must hydrate");
    await selectProvider(page, repo);
    const source = await waitForSource(page, repo);
    step = "source_entry_check";
    if (phase === "failed-provider-source") {
      const id = source?.id;
      assert.match(id ?? "", /^[0-9a-f-]{36}$/i, "provider failure control source must be persisted");
      await writeFile(path.join(privateDir, "failure-state.json"), JSON.stringify({ source_id: id }), { mode: 0o600 });
    } else {
      const id = source?.id;
      assert.match(id ?? "", /^[0-9a-f-]{36}$/i, "private source must be persisted");
      await writeFile(path.join(privateDir, "dataset.json"), JSON.stringify({
        user_id: org.user_id,
        organization_id: org.organization_id,
        source_id: id,
        package_name: "packio-fixture/private-package",
        package_version: "1.0.0",
        registry_host: `${org.slug}.registry.fixture.test:${new URL(appUrl).port}`,
      }), { mode: 0o600 });
    }
    await closeAndAssert(errors, context, true);
  } else if (phase === "resync") {
    resyncDiagnostic = {};
    step = "worker_recreation_resync";
    const org = JSON.parse(await readFile(path.join(privateDir, "organization-state.json"), "utf8"));
    const dataset = JSON.parse(await readFile(path.join(privateDir, "dataset.json"), "utf8"));
    const { context, page, errors } = await openContext(path.join(privateDir, "verified-user.json"));
    await loadHydrated(page, `${appUrl}/organization/${org.slug}/sources/${dataset.source_id}`);
    const resyncPath = `/organization/${org.slug}/sources/${dataset.source_id}/resync`;
    const [response] = await Promise.all([
      page.waitForResponse(candidate => {
        const url = new URL(candidate.url());
        return url.origin === new URL(appUrl).origin
          && url.pathname === resyncPath
          && candidate.request().method() === "POST";
      }),
      page.getByRole("button", { name: "Re-sync source" }).click(),
    ]);
    step = "worker_recreation_resync_http_status";
    const httpStatus = response.status();
    resyncDiagnostic.http_status = httpStatus;
    assert.equal(httpStatus, 302, "source re-sync must return its authenticated redirect response");
    step = "worker_recreation_resync_redirect";
    const expectedSourcePath = resyncPath.replace(/\/resync$/, "");
    resyncDiagnostic.redirect_route_match = false;
    const redirectPath = new URL(response.headers().location ?? "", appUrl).pathname;
    resyncDiagnostic.redirect_route_match = redirectPath === expectedSourcePath;
    assert.equal(redirectPath, expectedSourcePath,
      "source re-sync must redirect back to its detail page");
    step = "worker_recreation_resync_flash";
    resyncDiagnostic.flash_visible = false;
    await page.getByText("Re-sync scheduled. Refresh this page to see the latest sync status.", { exact: true })
      .waitFor({ state: "visible" });
    resyncDiagnostic.flash_visible = true;
    step = "worker_recreation_resync_final_route";
    resyncDiagnostic.final_route_match = false;
    const finalPath = new URL(page.url()).pathname;
    resyncDiagnostic.final_route_match = finalPath === expectedSourcePath;
    assert.equal(finalPath, expectedSourcePath,
      "source re-sync redirect must settle on the source detail page");
    step = "worker_recreation_resync_browser_errors";
    resyncDiagnostic.browser_error_count = errors.length;
    await closeAndAssert(errors, context);
  } else {
    throw new Error("unknown product browser phase");
  }
  console.log(`PASS: product browser ${phase}`);
} catch (error) {
  const errorType = safeErrorType(error);
  await recordFailure(step, errorType);
  failureRecorded = true;
  console.error(`FAIL: product browser ${phase} at ${step} (${errorType})`);
  process.exitCode = 1;
} finally {
  step = "browser_close";
  try {
    await browser.close();
  } catch (error) {
    if (!failureRecorded) {
      const errorType = safeErrorType(error);
      await recordFailure("browser_close", errorType);
      console.error(`FAIL: product browser ${phase} at browser_close (${errorType})`);
    }
    process.exitCode = 1;
  }
}
