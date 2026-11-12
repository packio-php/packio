<script lang="ts">
  import type { Snippet } from "svelte";

  let { title = "", children }: { title?: string; children?: Snippet } =
    $props();
</script>

<main data-testid="test-shell" data-title={title}>
  {@render children?.()}
</main>
