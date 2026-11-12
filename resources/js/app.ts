import { createInertiaApp, http, router } from "@inertiajs/svelte";
import { browserBranding, mergeBranding } from "@/lib/branding";
import { setupInertiaApp } from "@/inertia/bootstrap";
import { redirectExpiredInertiaSession } from "@/inertia/httpException";
import { resolvePage } from "@/inertia/resolve";
import { toast } from "@/lib/toast";
import { csrfStore } from "@/lib/api";
import { registerInertiaCsrfHandlers } from "@/lib/api/inertiaCsrf";
import { finishThemeBoot } from "@/lib/theme";
import type { Branding } from "@/types/branding";
import type { PackioFlashPayload } from "@/types/flash";
import "../css/app.css";

function showToasts(toasts?: PackioFlashPayload["toasts"]): void {
  if (toasts?.length) {
    for (const t of toasts) {
      toast[t.type](t.message, {
        duration: t.duration ?? undefined,
        position: t.position ?? undefined,
      });
    }
  }
}

const deliveredFlash = new WeakSet<PackioFlashPayload>();

export function deliverFlash(flash?: PackioFlashPayload): void {
  if (!flash || deliveredFlash.has(flash)) {
    return;
  }

  deliveredFlash.add(flash);
  showToasts(flash.toasts);
}

function activateDocumentScripts(): void {
  for (const script of Array.from(document.scripts)) {
    const replacement = document.createElement("script");

    for (const attribute of Array.from(script.attributes)) {
      replacement.setAttribute(attribute.name, attribute.value);
    }

    replacement.text = script.text;
    script.replaceWith(replacement);
  }
}

function replaceDocumentWithHtml(html: string): void {
  const nextDocument = new DOMParser().parseFromString(html, "text/html");
  const nextRoot = nextDocument.documentElement;

  document.documentElement.replaceWith(document.adoptNode(nextRoot));
  activateDocumentScripts();
}

router.on("flash", (event) => {
  deliverFlash(event.detail.flash);
});

router.on("httpException", (event) => {
  if (redirectExpiredInertiaSession(event)) {
    return;
  }

  const response = event.detail?.response;

  if (!response) {
    return;
  }

  event.preventDefault();

  if (response.status >= 400) {
    const contentType = String(response.headers?.["content-type"] ?? "");
    const html = typeof response.data === "string" ? response.data : "";

    if (contentType.includes("text/html") && html.trim().length > 0) {
      replaceDocumentWithHtml(html);

      return;
    }

    window.location.assign(window.location.href);
  }
});

registerInertiaCsrfHandlers(http);

createInertiaApp({
  title: (title) => {
    const branding = browserBranding();
    return title ? `${title} | ${branding.name}` : branding.name;
  },
  resolve: resolvePage,
  setup({ el, App, props }) {
    if (typeof window !== "undefined") {
      const initialPage = props.initialPage as
        | {
            props?: {
              branding?: Partial<Branding>;
              csrf?: {
                token?: string | null;
              };
            };
            flash?: PackioFlashPayload;
          }
        | undefined;

      window.__appBranding = mergeBranding(initialPage?.props?.branding ?? {});

      // Initialize CSRF store from the first response before the first Inertia navigation
      const initialCsrfToken = initialPage?.props?.csrf?.token;
      if (typeof initialCsrfToken === "string" && initialCsrfToken.length > 0) {
        csrfStore.set(initialCsrfToken);
      }

      deliverFlash(initialPage?.flash);
    }

    setupInertiaApp({ el, App, props });
    finishThemeBoot();
  },
  progress: {
    color: "#5856d6",
    delay: 150,
    showSpinner: false,
  },
});
