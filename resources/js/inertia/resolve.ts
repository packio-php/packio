import type { ResolvedComponent } from "@inertiajs/svelte";

const pages = import.meta.glob<ResolvedComponent>("../Pages/**/*.svelte");

export function resolvePage(name: string): Promise<ResolvedComponent> {
  const page = pages[`../Pages/${name}.svelte`];

  if (!page) {
    throw new Error(`Unknown Inertia page: ${name}`);
  }

  return page();
}
