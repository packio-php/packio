<script lang="ts">
  import Link from "@/Components/core/Link.svelte";
  import { mergeBranding } from "@/lib/branding";
  import type { Branding } from "@/types/branding";

  type ServerErrorProps = {
    status?: number;
    title?: string;
    message?: string;
    requestPath?: string;
    debug?: boolean;
    exceptionClass?: string;
    file?: string;
    line?: number;
    trace?: string;
  };

  interface Props {
    status?: ServerErrorProps["status"];
    title?: ServerErrorProps["title"];
    message?: ServerErrorProps["message"];
    requestPath?: ServerErrorProps["requestPath"];
    debug?: ServerErrorProps["debug"];
    exceptionClass?: ServerErrorProps["exceptionClass"];
    file?: ServerErrorProps["file"];
    line?: ServerErrorProps["line"];
    trace?: ServerErrorProps["trace"];
    branding?: Partial<Branding>;
  }

  let {
    status = 500,
    title = "Server error",
    message = "Something went wrong while processing your request.",
    requestPath = "",
    debug = false,
    exceptionClass = "",
    file = "",
    line = 0,
    trace = "",
    branding = undefined,
  }: Props = $props();

  let brand = $derived(mergeBranding(branding));
</script>

<svelte:head>
  <title>{status} {title}</title>
</svelte:head>

<div class="error-page">
  <main class="error-panel" aria-labelledby="error-title">
    <p class="error-eyebrow">{brand.name} · {brand.domain}</p>
    <p class="error-code">{status}</p>
    <h1 class="error-title" id="error-title">{title}</h1>
    <p class="error-message">{message}</p>

    {#if requestPath}
      <p class="error-request-path">
        Request path: <code>{requestPath}</code>
      </p>
    {/if}

    {#if debug}
      <section class="error-debug">
        <h2>Debug information</h2>

        {#if exceptionClass}
          <p><strong>Exception:</strong> {exceptionClass}</p>
        {/if}

        {#if file}
          <p><strong>File:</strong> {file}:{line}</p>
        {/if}

        {#if trace}
          <details>
            <summary>Stack trace</summary>
            <pre>{trace}</pre>
          </details>
        {/if}
      </section>
    {/if}

    <div class="error-actions">
      <button
        class="error-back-button"
        type="button"
        onclick={() =>
          window.history.length > 1
            ? window.history.back()
            : window.location.assign("/")}
      >
        Go back
      </button>
      <Link class="error-home-link" href="/">Go to homepage</Link>
    </div>
  </main>
</div>

<style>
  .error-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 32px 20px;
    background:
      radial-gradient(circle at top left, var(--accent-soft), transparent 34%),
      radial-gradient(circle at right 12%, var(--gold-soft), transparent 30%),
      linear-gradient(
        180deg,
        color-mix(in srgb, var(--surface) 80%, var(--page-bg)) 0%,
        var(--page-bg) 100%
      );
    color: var(--page-ink);
    font-family: "Plus Jakarta Sans", "Segoe UI", sans-serif;
  }

  .error-panel {
    width: min(100%, 680px);
    border: 1px solid var(--panel-border);
    border-radius: 8px;
    background: var(--panel);
    box-shadow: var(--shadow-card);
    padding: clamp(28px, 6vw, 48px);
  }

  .error-eyebrow {
    margin: 0 0 18px;
    color: var(--page-muted);
    font-family: "JetBrains Mono", "SFMono-Regular", monospace;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }

  .error-code {
    margin: 0 0 12px;
    color: var(--accent-ink);
    font-size: clamp(52px, 12vw, 96px);
    font-weight: 800;
    line-height: 0.95;
  }

  .error-title {
    margin: 0;
    max-width: 560px;
    font-size: clamp(26px, 5vw, 40px);
    font-weight: 800;
    line-height: 1.1;
  }

  .error-message {
    margin: 18px 0 0;
    max-width: 560px;
    color: var(--page-muted);
    font-size: 16px;
    line-height: 1.7;
  }

  .error-request-path,
  .error-debug {
    margin-top: 1.25rem;
    color: var(--page-muted);
  }

  .error-request-path code {
    word-break: break-all;
    padding: 0.15rem 0.35rem;
    border-radius: 0.35rem;
    background: var(--accent-soft);
  }

  .error-debug {
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--panel-border);
    background: var(--surface-muted);
  }

  .error-debug h2 {
    margin: 0 0 0.75rem;
    font-size: 1rem;
  }

  .error-debug p {
    margin: 0.35rem 0;
  }

  details {
    margin-top: 0.75rem;
  }

  summary {
    cursor: pointer;
    color: var(--accent);
  }

  pre {
    margin: 0.75rem 0 0;
    overflow: auto;
    padding: 1rem;
    border-radius: 8px;
    background: var(--surface);
    border: 1px solid var(--panel-border);
    color: var(--page-ink);
    white-space: pre-wrap;
    word-break: break-word;
  }

  .error-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 28px;
  }

  :global(.error-home-link) {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: var(--button-primary-bg);
    color: var(--button-primary-text);
    font-weight: 700;
    padding: 0 18px;
    text-decoration: none;
    transition:
      background-color 0.18s ease,
      transform 0.18s ease;
  }

  .error-back-button {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--panel-border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--page-ink);
    cursor: pointer;
    font: inherit;
    font-weight: 700;
    padding: 0 18px;
    transition:
      border-color 0.18s ease,
      transform 0.18s ease;
  }

  .error-back-button:hover {
    border-color: var(--accent);
    transform: translateY(-1px);
  }

  :global(.error-home-link:hover) {
    background: var(--button-primary-hover);
    transform: translateY(-1px);
  }
</style>
