import { createInertiaApp, http, router } from "@inertiajs/svelte";
import { csrfStore } from "@/lib/api/csrfStore";
import { registerInertiaCsrfHandlers } from "@/lib/api/inertiaCsrf";
import { redirectExpiredInertiaSession } from "@/inertia/httpException";
import { setupInertiaApp } from "@/inertia/bootstrap";
import * as Home from "./Home.svelte";
import * as Next from "./Next.svelte";
import * as Shell from "./Shell.svelte";
import "../../../css/app.css";

const components = { Home, Next, Shell };

router.on("httpException", redirectExpiredInertiaSession);

registerInertiaCsrfHandlers(http);

const initialPage = document.querySelector<HTMLScriptElement>(
  'script[data-page="app"]',
);
const initialToken = initialPage
  ? (
      JSON.parse(initialPage.textContent ?? "{}") as {
        props?: { csrf?: { token?: string } };
      }
    ).props?.csrf?.token
  : "";
csrfStore.set(initialToken ?? "");

createInertiaApp({
  resolve: (name) => components[name as keyof typeof components],
  setup(options) {
    const method = setupInertiaApp(options);

    window.dispatchEvent(
      new CustomEvent("inertia:bootstrap", { detail: method }),
    );
  },
});
