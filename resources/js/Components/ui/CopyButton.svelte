<script>
  import { Check, Copy } from "@lucide/svelte";
  import Button from "@/Components/ui/Button.svelte";

  let { value = "", label = "Copy" } = $props();

  let copied = $state(false);
  let resetTimeout = null;

  async function copy() {
    if (!value) {
      return;
    }

    try {
      await navigator.clipboard.writeText(value);
      copied = true;

      if (resetTimeout) {
        clearTimeout(resetTimeout);
      }

      resetTimeout = setTimeout(() => {
        copied = false;
      }, 1500);
    } catch {
      copied = false;
    }
  }
</script>

<Button
  type="button"
  variant="iconSmall"
  className="shrink-0"
  onclick={copy}
  aria-label={copied ? "Copied" : label}
  title={copied ? "Copied" : label}
>
  {#if copied}
    <Check aria-hidden="true" class="h-4 w-4" strokeWidth={2} />
  {:else}
    <Copy aria-hidden="true" class="h-4 w-4" strokeWidth={1.8} />
  {/if}
</Button>
