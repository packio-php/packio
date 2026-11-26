<script lang="ts">
  import Button from "@/Components/ui/Button.svelte";

  interface Props {
    name: string;
    token: string;
    ondone: () => void;
  }

  let { name, token, ondone }: Props = $props();

  let status = $state("");

  async function copyToken(): Promise<void> {
    try {
      await navigator.clipboard.writeText(token);
      status = "Copied";
    } catch {
      status = "Copy failed. Select the token and copy it manually.";
    }
  }
</script>

<div class="mt-6 grid gap-3 rounded-2xl border px-4 py-4 theme-border">
  <p class="text-sm font-medium ui-title">
    Token "{name}" created. It is shown only once.
  </p>
  <code class="break-all rounded-xl px-3 py-2 font-mono text-xs select-all">
    {token}
  </code>
  <div class="flex flex-wrap items-center gap-3">
    <Button type="button" variant="primary" onclick={copyToken}>
      Copy token
    </Button>
    <Button type="button" variant="secondary" onclick={ondone}>Done</Button>
    <span class="text-sm ui-muted" aria-live="polite">{status}</span>
  </div>
</div>
