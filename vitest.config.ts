import { svelte } from "@sveltejs/vite-plugin-svelte";
import { defineConfig } from "vitest/config";

export default defineConfig({
  plugins: [svelte()],
  resolve: {
    alias: {
      "@": "/resources/js",
    },
    conditions: ["browser"],
  },
  test: {
    environment: "jsdom",
    globals: false,
    include: ["resources/js/**/*.test.ts"],
    exclude: ["resources/js/ssr.smoke.test.ts"],
    setupFiles: ["resources/js/test/setup.ts"],
    unstubGlobals: true,
  },
});
