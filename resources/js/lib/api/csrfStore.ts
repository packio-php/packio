import { router } from "@inertiajs/svelte";
import type { AppPageProps } from "@/types/app";

let token = "";

/** Keep the session token in sync with successful Inertia page props. */
router.on("success", (event) => {
  const token = (event.detail?.page?.props as AppPageProps | undefined)?.csrf
    ?.token;
  if (typeof token === "string" && token.length > 0) {
    set(token);
  }
});

/**
 * Synchronous getter for imperative code outside Svelte context.
 */
function set(nextToken: string): void {
  token = nextToken;
}

function current(): string {
  return token;
}

export const csrfStore = {
  set,
  current,
};
