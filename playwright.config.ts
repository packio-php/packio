import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./resources/js/e2e",
  timeout: 30_000,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? "http://127.0.0.1:4173",
    trace: "retain-on-failure",
  },
  webServer: process.env.E2E_BASE_URL
    ? undefined
    : {
        command:
          "vite --config vite.e2e.config.ts --host 127.0.0.1 --port 4173",
        reuseExistingServer: !process.env.CI,
        url: "http://127.0.0.1:4173/csr",
      },
});
