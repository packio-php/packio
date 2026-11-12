<script lang="ts">
  import { onDestroy, onMount } from "svelte";

  let {
    siteKey = "",
    action = "submit",
    token = $bindable(""),
    ontoken,
  }: {
    siteKey?: string;
    action?: string;
    token?: string;
    ontoken?: (token: string) => void;
  } = $props();

  const TURNSTILE_SCRIPT_URL =
    "https://challenges.cloudflare.com/turnstile/v0/api.js";
  const TURNSTILE_SCRIPT_ID = "cloudflare-turnstile-script";

  let container: HTMLDivElement | null = $state(null);
  let widgetId: string | number | null = $state(null);

  interface TurnstileWidget {
    render(
      el: HTMLElement,
      options: {
        sitekey: string;
        callback: (token: string) => void;
        "error-callback": () => void;
        "expired-callback": () => void;
        theme: string;
        action: string;
        appearance: "interaction-only";
      },
    ): string | number;
    remove(id: string | number): void;
    reset(id: string | number): void;
  }

  interface TurnstileWindow extends Window {
    turnstile?: TurnstileWidget;
  }

  function updateToken(nextToken: string): void {
    token = nextToken;
    ontoken?.(nextToken);
  }

  function currentWindow(): TurnstileWindow {
    return window as TurnstileWindow;
  }

  function renderWidget(): void {
    if (!container || !siteKey) {
      updateToken("");
      return;
    }

    const win = currentWindow();
    if (!win.turnstile) {
      updateToken("");
      return;
    }

    if (widgetId !== null) {
      win.turnstile.remove(widgetId);
      widgetId = null;
    }

    widgetId = win.turnstile.render(container, {
      sitekey: siteKey,
      callback: (nextToken: string) => {
        updateToken(nextToken);
      },
      "error-callback": () => {
        updateToken("");
      },
      "expired-callback": () => {
        updateToken("");
      },
      theme: "auto",
      action,
      appearance: "interaction-only",
    });
  }

  export function reset(): void {
    updateToken("");

    if (widgetId === null) {
      return;
    }

    currentWindow().turnstile?.reset(widgetId);
  }

  onMount(() => {
    if (!siteKey) {
      updateToken("");
      return;
    }

    const existingScript = document.getElementById(
      TURNSTILE_SCRIPT_ID,
    ) as HTMLScriptElement | null;
    const onLoad = (): void => {
      renderWidget();
    };

    if (currentWindow().turnstile) {
      renderWidget();

      return;
    }

    if (existingScript) {
      existingScript.addEventListener("load", onLoad, { once: true });

      return () => {
        existingScript.removeEventListener("load", onLoad);
      };
    }

    const script = document.createElement("script");
    script.id = TURNSTILE_SCRIPT_ID;
    script.src = `${TURNSTILE_SCRIPT_URL}?render=explicit`;
    script.async = true;
    script.defer = true;
    script.addEventListener("load", onLoad, { once: true });
    script.addEventListener(
      "error",
      () => {
        updateToken("");
      },
      { once: true },
    );

    document.head.appendChild(script);

    return () => {
      script.removeEventListener("load", onLoad);
    };
  });

  onDestroy(() => {
    if (widgetId !== null) {
      currentWindow().turnstile?.remove(widgetId);
    }
  });
</script>

<div bind:this={container} class="turnstile-container"></div>
