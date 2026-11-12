<script>
  let { as = "section", className = "", padding = "p-4", children } = $props();
</script>

<svelte:element
  this={as}
  class={`min-w-0 theme-surface-muted rounded-xl border theme-border ${padding} ${className}`.trim()}
>
  {@render children?.()}
</svelte:element>
