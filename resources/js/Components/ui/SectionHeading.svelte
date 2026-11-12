<script>
  let {
    eyebrow = "",
    title = "",
    description = "",
    titleTag = "h2",
    titleClass = "text-2xl font-semibold",
    className = "",
  } = $props();
</script>

<div class={className}>
  {#if eyebrow}
    <div
      class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--page-muted)]"
    >
      {eyebrow}
    </div>
  {/if}

  <svelte:element
    this={titleTag}
    class={titleClass}
    style="color: var(--page-ink);"
  >
    {title}
  </svelte:element>

  {#if description}
    <p class="mt-3 text-[15px] leading-7 text-[var(--page-muted)]">
      {description}
    </p>
  {/if}
</div>
