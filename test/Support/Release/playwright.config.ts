import { defineConfig } from "@playwright/test";

// Runs in a container on the fixture network; product.test.sh picks the scenarios with --grep.
export default defineConfig({
  testDir: ".",
  outputDir: `${process.env.PRODUCT_STATE_DIR}/test-results`,
  timeout: 180_000,
  workers: 1,
  reporter: "line",
  use: {
    baseURL: "https://app.fixture.test",
    // The fixture CA is disposable; Git in the worker and Composer verify it for real.
    ignoreHTTPSErrors: true,
    actionTimeout: 15_000,
  },
});
