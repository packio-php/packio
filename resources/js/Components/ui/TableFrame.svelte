<script lang="ts">
  import type { Snippet } from "svelte";

  let {
    ariaLabel,
    className = "",
    tableClassName = "",
    children,
  }: {
    ariaLabel: string;
    className?: string;
    tableClassName?: string;
    children?: Snippet;
  } = $props();
</script>

<div
  class={`ui-table-frame min-w-0 overflow-x-auto rounded-xl border theme-border theme-surface ${className}`.trim()}
>
  <table
    aria-label={ariaLabel}
    class={`ui-data-table w-full border-collapse text-left text-sm ${tableClassName}`.trim()}
  >
    {@render children?.()}
  </table>
</div>

<style>
  .ui-table-frame {
    background-color: var(--surface);
  }

  .ui-table-frame :global(.ui-data-table thead) {
    background-color: var(--surface);
    color: var(--page-muted);
  }

  .ui-table-frame :global(.ui-data-table thead tr) {
    border-bottom: 1px solid var(--panel-border);
  }

  .ui-table-frame :global(.ui-data-table thead th) {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  .ui-table-frame :global(.ui-data-table tbody tr) {
    border-top: 1px solid var(--panel-border);
  }

  .ui-table-frame :global(.ui-data-table tbody tr:first-child) {
    border-top-width: 0;
  }

  .ui-table-frame :global(.ui-data-table tbody tr:nth-child(odd)) {
    background-color: var(--surface);
  }

  .ui-table-frame :global(.ui-data-table tbody tr:nth-child(even)) {
    background-color: color-mix(
      in srgb,
      var(--surface-muted) 58%,
      var(--surface)
    );
  }
</style>
