<script lang="ts">
  let {
    threshold = 320,
    label = "↑ Top",
    container = null,
  }: {
    threshold?: number;
    label?: string;
    container?: HTMLElement | null;
  } = $props();
</script>

<div
  data-testid="scroll-top-button-stub"
  data-container-class={container?.className ?? ""}
  data-threshold={threshold}
  data-label={label}
></div>
