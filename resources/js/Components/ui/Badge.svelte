<script>
  const tones = {
    accent: "ui-badge-accent",
    danger: "ui-badge-danger",
    muted: "ui-badge-muted",
    neutral: "ui-badge-neutral",
    gold: "ui-badge-gold",
  };

  let {
    tone = "accent",
    className = "",
    title = undefined,
    children,
  } = $props();
</script>

<span
  {title}
  class={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${tones[tone] ?? tones.accent} ${className}`.trim()}
>
  {@render children?.()}
</span>
