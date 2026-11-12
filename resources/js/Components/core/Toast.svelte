<script lang="ts">
  import { X } from "@lucide/svelte";
  import { onMount, onDestroy } from "svelte";
  import { fly, fade } from "svelte/transition";
  import type { Toast } from "@/types/toast";

  let { toast, onClose }: { toast: Toast; onClose: (id: string) => void } =
    $props();

  let remaining = $state(0);
  let progress = $state(100);
  let interval: ReturnType<typeof setInterval> | undefined = $state();
  let startTime = $state(0);

  const icons: Record<Toast["type"], string> = {
    success: "✓",
    error: "✕",
    info: "i",
    warning: "!",
  };

  function startTimer() {
    startTime = Date.now();
    interval = setInterval(() => {
      const elapsed = Date.now() - startTime;
      remaining = Math.max(0, toast.duration - elapsed);
      progress = (remaining / toast.duration) * 100;
      if (remaining <= 0) {
        clearTimer();
        onClose(toast.id);
      }
    }, 50);
  }

  function pauseTimer() {
    clearTimer();
  }

  function resumeTimer() {
    startTime = Date.now() - (toast.duration - remaining);
    interval = setInterval(() => {
      const elapsed = Date.now() - startTime;
      remaining = Math.max(0, toast.duration - elapsed);
      progress = (remaining / toast.duration) * 100;
      if (remaining <= 0) {
        clearTimer();
        onClose(toast.id);
      }
    }, 50);
  }

  onMount(() => {
    remaining = toast.duration;
    startTimer();
  });

  onDestroy(clearTimer);

  function clearTimer(): void {
    if (interval !== undefined) {
      clearInterval(interval);
      interval = undefined;
    }
  }

  function handleClose() {
    clearTimer();
    onClose(toast.id);
  }
</script>

<div
  class="pointer-events-auto flex w-80 items-start gap-3 rounded-2xl border px-4 py-3 shadow-lg"
  style={`background-color: var(--toast-${toast.type}-bg); border-color: var(--toast-${toast.type}-border); color: var(--toast-${toast.type}-text);`}
  transition:fly={{
    y: toast.position.startsWith("top") ? -20 : 20,
    duration: 300,
    opacity: 0,
  }}
  onmouseenter={pauseTimer}
  onmouseleave={resumeTimer}
  role="alert"
>
  <span
    class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
    style={`background-color: color-mix(in srgb, var(--toast-${toast.type}-text) 12%, transparent); color: var(--toast-${toast.type}-text);`}
  >
    {icons[toast.type]}
  </span>

  <p class="flex-1 text-sm leading-6">{toast.message}</p>

  <button
    type="button"
    class="shrink-0 cursor-pointer rounded-lg p-1 text-sm leading-none opacity-60 transition hover:opacity-100"
    style="color: inherit;"
    onclick={handleClose}
    aria-label="Close notification"
  >
    <X aria-hidden="true" size={14} strokeWidth={2} />
  </button>

  <div
    class="absolute bottom-0 left-0 h-0.5 rounded-full transition-none"
    style={`width: ${progress}%; background-color: var(--toast-${toast.type}-text); opacity: 0.3;`}
    transition:fade
  ></div>
</div>

<style>
  div[role="alert"] {
    position: relative;
    overflow: hidden;
  }
</style>
