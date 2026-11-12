import { createInertiaApp } from "@inertiajs/svelte";
import type { Page } from "@inertiajs/core";
import { render } from "svelte/server";
import * as Home from "./Home.svelte";
import * as Next from "./Next.svelte";
import * as Shell from "./Shell.svelte";

const components = { Home, Next, Shell };

export async function renderPage(page: Page): Promise<{
  body: string;
  head: string[];
}> {
  const rendered = await createInertiaApp({
    page,
    resolve: (name) => components[name as keyof typeof components],
    setup: ({ App, props }) => render(App, { props }),
  });

  if (!rendered || typeof rendered === "function") {
    throw new Error("Expected the Inertia SSR renderer.");
  }

  return rendered;
}
