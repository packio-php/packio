<script lang="ts">
  import { X } from "@lucide/svelte";
  import type { PackioFlashPayload } from "@/types/flash";

  let {
    flash = {},
    className = "",
  }: { flash?: PackioFlashPayload; className?: string } = $props();

  let dismissedSuccess = $state(false);
  let dismissedError = $state(false);

  $effect(() => {
    // Reset dismissal whenever a new flash payload arrives (page navigation).
    void flash?.success;
    void flash?.error;
    dismissedSuccess = false;
    dismissedError = false;
  });
</script>

{#if flash?.success && !dismissedSuccess}
  <div
    class={`flash-message flex items-start justify-between gap-3 rounded-2xl border px-4 py-3 text-sm shadow-sm ${className}`.trim()}
    style={`background-color: var(--flash-success-bg); border-color: var(--flash-success-border); color: var(--flash-success-text);`}
  >
    <span class="min-w-0 flex-1">{flash.success}</span>
    <button
      aria-label="Dismiss message"
      class="cursor-pointer rounded-full p-1 opacity-70 transition hover:opacity-100"
      type="button"
      onclick={() => (dismissedSuccess = true)}
    >
      <X aria-hidden="true" class="h-4 w-4 shrink-0" strokeWidth={2} />
    </button>
  </div>
{/if}

{#if flash?.error && !dismissedError}
  <div
    class={`flash-message flex items-start justify-between gap-3 rounded-2xl border px-4 py-3 text-sm shadow-sm ${className}`.trim()}
    style={`background-color: var(--flash-error-bg); border-color: var(--flash-error-border); color: var(--flash-error-text);`}
  >
    <span class="min-w-0 flex-1">{flash.error}</span>
    <button
      aria-label="Dismiss message"
      class="cursor-pointer rounded-full p-1 opacity-70 transition hover:opacity-100"
      type="button"
      onclick={() => (dismissedError = true)}
    >
      <X aria-hidden="true" class="h-4 w-4 shrink-0" strokeWidth={2} />
    </button>
  </div>
{/if}

<style>
  .flash-message {
    max-width: 100%;
    min-width: 0;
    overflow-wrap: anywhere;
    word-break: break-word;
  }
</style>
