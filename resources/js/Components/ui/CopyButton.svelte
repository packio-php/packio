<script>
  import { Check, Copy, X } from "@lucide/svelte";
  import Button from "@/Components/ui/Button.svelte";

  // `text` renders a visible label instead of the icon.
  let { value = "", label = "", text = "" } = $props();

  let status = $state("idle");
  let resetTimeout = null;

  const failedLabel = "Copy failed";
  let name = $derived(
    status === "copied"
      ? "Copied"
      : status === "failed"
        ? failedLabel
        : label || "Copy",
  );

  async function copy() {
    if (!value) {
      return;
    }

    try {
      await navigator.clipboard.writeText(value);
      status = "copied";
    } catch {
      status = "failed";
    }

    if (resetTimeout) {
      clearTimeout(resetTimeout);
    }

    resetTimeout = setTimeout(() => {
      status = "idle";
    }, 1500);
  }
</script>

{#if text}
  <Button
    type="button"
    variant="soft"
    className="shrink-0 !px-3 !py-1.5"
    onclick={copy}
    aria-label={label || status !== "idle" ? name : undefined}
    title={status === "idle" ? label || text : name}
  >
    {status === "idle" ? text : name}
  </Button>
{:else}
  <Button
    type="button"
    variant="iconSmall"
    className="shrink-0"
    onclick={copy}
    aria-label={name}
    title={name}
  >
    {#if status === "copied"}
      <Check aria-hidden="true" class="h-4 w-4" strokeWidth={2} />
    {:else if status === "failed"}
      <X aria-hidden="true" class="h-4 w-4" strokeWidth={2} />
    {:else}
      <Copy aria-hidden="true" class="h-4 w-4" strokeWidth={1.8} />
    {/if}
  </Button>
{/if}
