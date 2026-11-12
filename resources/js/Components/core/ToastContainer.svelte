<script lang="ts">
  import { toasts, toast } from "@/lib/toast";
  import { fromStore } from "svelte/store";
  import Toast from "./Toast.svelte";
  import type { ToastPosition } from "@/types/toast";

  const positions: ToastPosition[] = [
    "top-left",
    "top-right",
    "bottom-left",
    "bottom-right",
  ];
  const toastState = fromStore(toasts);

  function getPositionClasses(position: ToastPosition): string {
    const base = "pointer-events-none fixed z-[100] flex flex-col gap-3 p-4";
    switch (position) {
      case "top-left":
        return `${base} top-20 left-0`;
      case "top-right":
        return `${base} top-20 right-0`;
      case "bottom-left":
        return `${base} bottom-0 left-0`;
      case "bottom-right":
        return `${base} bottom-0 right-0`;
    }
  }

  function getToastsByPosition(position: ToastPosition) {
    return toastState.current.filter((toast) => toast.position === position);
  }
</script>

{#each positions as position (position)}
  {#if getToastsByPosition(position).length > 0}
    <div class={getPositionClasses(position)}>
      {#each getToastsByPosition(position) as t (t.id)}
        <Toast toast={t} onClose={toast.dismiss} />
      {/each}
    </div>
  {/if}
{/each}
