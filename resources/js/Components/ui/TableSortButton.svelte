<script lang="ts">
  let {
    label,
    field,
    currentSort = "",
    currentDirection = "desc",
    onSort = () => {},
    className = "",
  }: {
    label: string;
    field: string;
    currentSort?: string;
    currentDirection?: "asc" | "desc";
    onSort?: (field: string) => void;
    className?: string;
  } = $props();

  const active = $derived(currentSort === field);
  const icon = $derived(
    active ? (currentDirection === "asc" ? "↑" : "↓") : "↕",
  );
</script>

<button
  type="button"
  class={`inline-flex cursor-pointer items-center gap-1.5 rounded-md text-left font-inherit text-current transition-colors hover:text-[var(--accent-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color-mix(in_srgb,var(--accent)_60%,transparent)] ${className}`.trim()}
  aria-label={`Sort by ${label}`}
  onclick={() => onSort(field)}
>
  <span>{label}</span>
  <span aria-hidden="true" class="text-[0.8em] leading-none">{icon}</span>
</button>
