import { expect, test, type Page } from "@playwright/test";

function captureRuntimeErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on("console", (message: { type(): string; text(): string }) => {
    if (message.type() === "error") {
      errors.push(`console: ${message.text()}`);
    }
  });
  page.on("pageerror", (error: Error) => {
    errors.push(`pageerror: ${error.message}`);
  });

  return errors;
}

async function expectNoRuntimeErrors(
  page: Page,
  errors: string[],
): Promise<void> {
  await page.evaluate(
    () =>
      new Promise<void>((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
      }),
  );
  expect(errors).toEqual([]);
}

async function captureBootstrapMethods(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const target = window as Window & {
      __inertiaBootstrapMethods?: ("hydrate" | "mount")[];
    };
    target.__inertiaBootstrapMethods = [];
    window.addEventListener("inertia:bootstrap", (event) => {
      target.__inertiaBootstrapMethods?.push(
        (event as CustomEvent<"hydrate" | "mount">).detail,
      );
    });
  });
}

async function expectBootstrapMethod(
  page: Page,
  method: "hydrate" | "mount",
): Promise<void> {
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          (
            window as Window & {
              __inertiaBootstrapMethods?: ("hydrate" | "mount")[];
            }
          ).__inertiaBootstrapMethods ?? [],
      ),
    )
    .toContain(method);
}

test("shared production bootstrap mounts an empty CSR root without runtime errors", async ({
  page,
}) => {
  const errors = captureRuntimeErrors(page);
  await captureBootstrapMethods(page);

  await page.goto("/csr");

  await expect(page.getByRole("heading", { name: "Home" })).toBeVisible();
  await expect(page.locator("#app")).toHaveCount(1);
  await expect(page.locator('[data-server-rendered="true"]')).toHaveCount(0);
  await expectNoRuntimeErrors(page, errors);
  await expectBootstrapMethod(page, "mount");
});

test("CSR initial load mounts the v3 mount without runtime errors", async ({
  page,
}) => {
  const errors = captureRuntimeErrors(page);
  await captureBootstrapMethods(page);

  await page.goto("/csr");

  await expect(page.getByRole("heading", { name: "Home" })).toBeVisible();
  await expect(page.locator("#app")).toHaveCount(1);
  await expect(page.locator('[data-server-rendered="true"]')).toHaveCount(0);
  await expectBootstrapMethod(page, "mount");
  await expectNoRuntimeErrors(page, errors);
});

test("SSR initial load hydrates rendered markup without console errors", async ({
  page,
}) => {
  const errors = captureRuntimeErrors(page);
  await captureBootstrapMethods(page);

  await page.goto("/");

  await expect(page.getByRole("heading", { name: "Home" })).toBeVisible();
  await expect(page.locator('#app[data-server-rendered="true"]')).toHaveCount(
    1,
  );
  await expectBootstrapMethod(page, "hydrate");
  await expectNoRuntimeErrors(page, errors);
});

test("Inertia navigation changes the page without a document reload", async ({
  page,
}) => {
  let documentRequests = 0;
  page.on("request", (request) => {
    if (request.isNavigationRequest()) {
      documentRequests += 1;
    }
  });

  await page.goto("/csr");
  await page.getByTestId("next-link").click();

  await expect(page.getByRole("heading", { name: "Next" })).toBeVisible();
  await expect(page).toHaveURL(/\/next$/);
  expect(documentRequests).toBe(1);
});

test("back and forward retain the persistent sidebar and synchronize the page URL", async ({
  page,
}) => {
  await page.goto("/csr");
  await page.getByTestId("sidebar-toggle").click();
  await expect(page.getByTestId("sidebar")).toBeVisible();

  await page.getByTestId("next-link").click();
  await expect(page.getByTestId("page-url")).toHaveText("/next");

  await page.goBack();
  await expect(page.getByTestId("page-url")).toHaveText("/csr");
  await expect(page.getByTestId("sidebar")).toBeVisible();

  await page.goForward();
  await expect(page.getByTestId("page-url")).toHaveText("/next");
  await expect(page.getByTestId("sidebar")).toBeVisible();
});

test("native router submissions reuse the session CSRF token and render validation old input", async ({
  page,
}) => {
  const csrfTokens: string[] = [];
  page.on("request", (request) => {
    if (request.method() === "POST" && request.url().endsWith("/form")) {
      csrfTokens.push(request.headers()["x-csrf-token"] ?? "");
    }
  });

  await page.goto("/csr");
  await page.getByTestId("email").fill("valid@example.test");
  await page.getByTestId("submit-form").click();
  await expect(page.getByTestId("page-url")).toHaveText("/");
  await expect(page.getByTestId("email")).toHaveValue("valid@example.test");

  await page.getByTestId("email").fill("invalid-email");
  await page.getByTestId("submit-form").click();
  await expect(page.getByTestId("email-error")).toHaveText(
    "A valid email is required.",
  );
  await expect(page.getByTestId("email")).toHaveValue("invalid-email");
  expect(csrfTokens).toEqual(["csrf-0", "csrf-0"]);
});

test("Jobs partial reload requests only jobs and updates only that prop", async ({
  page,
}) => {
  let partialData = "";
  page.on("request", (request) => {
    if (request.headers()["x-inertia-partial-data"]) {
      partialData = request.headers()["x-inertia-partial-data"];
    }
  });

  await page.goto("/csr");
  await page.getByTestId("reload-jobs").click();

  await expect(page.getByTestId("jobs")).toHaveText("reloaded-job");
  expect(partialData).toBe("jobs");
});

test("419 HTTP exceptions reload the current location", async ({ page }) => {
  await page.goto("/csr");
  await page.getByTestId("expired-session").click();

  await expect(page).toHaveURL(/\/csr$/);
  await expect(page.getByRole("heading", { name: "Home" })).toBeVisible();
});

test("rapid section clicks interrupt pending visits and leave the UI interactive", async ({
  page,
}) => {
  const errors = captureRuntimeErrors(page);
  await page.goto("/csr");
  await expect(page.getByRole("heading", { name: "Home" })).toBeVisible();

  let interruptedVisits = 0;
  page.on("requestfailed", (request) => {
    if (request.headers()["x-inertia"]) interruptedVisits++;
  });
  await page.route("**/*", async (route) => {
    if (route.request().headers()["x-inertia"]) {
      await new Promise((resolve) => setTimeout(resolve, 150));
    }
    await route.continue();
  });

  for (let round = 0; round < 5; round++) {
    for (const section of ["next", "home", "csr", "next"]) {
      await page.getByTestId(`section-${section}`).click();
    }
    await expect(page.getByRole("heading", { name: "Next" })).toBeVisible();
    await expect(page).toHaveURL(/\/next$/);
  }

  expect(interruptedVisits).toBeGreaterThan(0);
  await page.getByTestId("sidebar-toggle").click();
  await expect(page.getByTestId("sidebar")).toBeVisible();
  await page.getByTestId("section-home").click();
  await expect(page.getByRole("heading", { name: "Home" })).toBeVisible();
  await expectNoRuntimeErrors(page, errors);
});

for (const sectionPaths of [
  ["/admin/users", "/admin/packages", "/admin/jobs"],
  [
    "/organization/test/packages",
    "/organization/test/sources",
    "/organization/test/tokens",
  ],
]) {
  test(`moving the pressed mouse over navigation does not start a native drag: ${sectionPaths[0]}`, async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(sectionPaths[0]);
    await expect(page.getByTestId("shell-url")).toHaveText(sectionPaths[0]);
    await page.getByTestId("shell-check").click();
    await expect(page.getByTestId("shell-check")).toHaveText("Clicks: 1");
    await page.evaluate(() => {
      document.addEventListener("dragstart", () => {
        document.documentElement.dataset.navigationDragStarted = "true";
      });
    });

    const link = page.locator(`a[href$="${sectionPaths[1]}"]:visible`).first();
    const box = await link.boundingBox();
    expect(box).not.toBeNull();
    if (!box) throw new Error("Navigation link has no bounding box");
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    await page.mouse.move(
      box.x + box.width / 2 + 40,
      box.y + box.height / 2 + 40,
      { steps: 10 },
    );
    await page.mouse.move(
      box.x + box.width / 2 + 80,
      box.y + box.height / 2 + 80,
      { steps: 10 },
    );
    await page.mouse.up();

    await expect(page.locator("html")).not.toHaveAttribute(
      "data-navigation-drag-started",
      "true",
    );
    await page.locator(`a[href$="${sectionPaths[2]}"]:visible`).first().click();
    await expect(page.getByTestId("shell-url")).toHaveText(sectionPaths[2]);
    await page.getByTestId("shell-check").click();
    await expect(page.getByTestId("shell-check")).toHaveText("Clicks: 1");
  });

  test(`rapid navigation in production shell ${sectionPaths[0]}`, async ({
    page,
  }) => {
    const errors = captureRuntimeErrors(page);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(sectionPaths[0]);
    await expect(page.getByTestId("shell-url")).toHaveText(sectionPaths[0]);

    for (let round = 0; round < 10; round++) {
      for (const path of sectionPaths) {
        await page.locator(`a[href$="${path}"]:visible`).first().click();
      }
    }
    await expect(page.getByTestId("shell-url")).toHaveText(sectionPaths[2]);
    await page.getByTestId("shell-check").click();
    await expect(page.getByTestId("shell-check")).toHaveText("Clicks: 1");
    await expectNoRuntimeErrors(page, errors);
  });
}
