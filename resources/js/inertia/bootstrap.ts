import { hydrate, mount } from "svelte";

interface InertiaSetupOptions {
  el: Element | null;
  App: unknown;
  props: unknown;
}

export type InertiaBootstrapMethod = "hydrate" | "mount";

export function setupInertiaApp({
  el,
  App,
  props,
}: InertiaSetupOptions): InertiaBootstrapMethod {
  const target = el ?? document.getElementById("app");

  if (!target) {
    throw new Error("Missing Inertia mount element.");
  }

  const options = {
    target,
    props: props as Parameters<typeof hydrate>[1]["props"],
  };
  const component = App as unknown as Parameters<typeof hydrate>[0];

  if (target.hasAttribute("data-server-rendered")) {
    hydrate(component, options);
    return "hydrate";
  }

  mount(component, options);
  return "mount";
}
