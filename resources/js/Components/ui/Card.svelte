<script>
  let {
    as = "section",
    className = "",
    padding = "p-4 sm:p-6",
    children,
  } = $props();
</script>

<svelte:element
  this={as}
  class={`min-w-0 theme-card-shadow rounded-[var(--radius-xl)] border theme-border theme-surface ${padding} ${className}`.trim()}
>
  {@render children?.()}
</svelte:element>
