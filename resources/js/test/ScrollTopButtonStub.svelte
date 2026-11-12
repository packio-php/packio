<script lang="ts">
  let {
    threshold = 320,
    label = "↑ Top",
    container = null,
    referenceSelector = null,
  }: {
    threshold?: number;
    label?: string;
    container?: HTMLElement | null;
    referenceSelector?: string | null;
  } = $props();
</script>

<div
  data-testid="scroll-top-button-stub"
  data-container-class={container?.className ?? ""}
  data-reference-selector={referenceSelector ?? ""}
  data-threshold={threshold}
  data-label={label}
></div>
