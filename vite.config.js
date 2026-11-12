import { defineConfig } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ command, isSsrBuild, mode }) => {
  const ssrBuild = isSsrBuild || mode === "ssr";
  const clientProductionBuild = command === "build" && !ssrBuild;

  return {
    base: clientProductionBuild ? "/build/" : "/",
    plugins: [tailwindcss(), svelte()],
    publicDir: false,
    ssr: ssrBuild
      ? {
          noExternal: true,
        }
      : undefined,
    resolve: {
      alias: {
        "@": "/resources/js",
      },
    },
    server: {
      host: "127.0.0.1",
      port: 5173,
      strictPort: true,
      origin: "http://127.0.0.1:5173",
      watch: {
        ignored: [
          "**/.docker/**",
          "**/.git/**",
          "**/.github/**",
          "**/.idea/**",
          "**/bin/**",
          "**/config/**",
          "**/data/**",
          "**/docs/**",
          "**/src/**",
          "**/templates/**",
          "**/test/**",
          "**/tests/**",
          "**/tools/**",
          "**/vendor/**",
          "**/runtime/**",
          "**/*.php",
          "**/*.md",
          "**/*.yaml",
          "**/*.yml",
          "**/*.neon",
          "**/composer.json",
          "**/composer.lock",
        ],
      },
    },
    build: ssrBuild
      ? {
          outDir: "data/ssr",
          emptyOutDir: true,
          minify: "esbuild",
          ssr: true,
          rollupOptions: {
            input: "resources/js/ssr.ts",
            output: {
              entryFileNames: "ssr.mjs",
            },
          },
        }
      : {
          manifest: true,
          outDir: "public/build",
          emptyOutDir: true,
          minify: "esbuild",
          rollupOptions: {
            input: "resources/js/app.ts",
          },
        },
  };
});
