<script lang="ts">
  import Link from "@/Components/core/Link.svelte";
  let { children }: { children?: import("svelte").Snippet } = $props();

  let sidebarOpen = $state(false);
</script>

<nav aria-label="Sections">
  <Link href="/" data-testid="section-home">Home section</Link>
  <Link href="/next" data-testid="section-next">Next section</Link>
  <Link href="/csr" data-testid="section-csr">CSR section</Link>
</nav>

<button
  type="button"
  data-testid="sidebar-toggle"
  aria-expanded={sidebarOpen}
  onclick={() => (sidebarOpen = !sidebarOpen)}
>
  Toggle sidebar
</button>

{#if sidebarOpen}
  <aside data-testid="sidebar">Persistent sidebar</aside>
{/if}

{@render children?.()}
