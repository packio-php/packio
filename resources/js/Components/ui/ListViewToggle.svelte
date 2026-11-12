<script lang="ts">
  import { Grid3X3, Table2 } from "@lucide/svelte";
  import type { ListViewMode } from "@/lib/listViewMode";

  const buttonBaseClass =
    "inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border text-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color-mix(in_srgb,var(--accent)_60%,transparent)]";
  const activeButtonClass =
    "border-[var(--accent)] bg-[var(--accent-soft)] text-[var(--accent-ink)]";
  const inactiveButtonClass =
    "border-transparent text-[var(--page-muted)] hover:bg-[var(--surface-strong)] hover:text-[var(--accent-ink)]";

  let {
    mode = "grid",
    onChange = () => {},
    className = "",
  }: {
    mode?: ListViewMode;
    onChange?: (mode: ListViewMode) => void;
    className?: string;
  } = $props();

  function selectMode(nextMode: ListViewMode): void {
    if (nextMode !== mode) {
      onChange(nextMode);
    }
  }
</script>

<div
  class={`inline-flex items-center gap-1 rounded-xl border theme-border theme-surface-muted p-1 ${className}`.trim()}
  role="group"
  aria-label="List view mode"
>
  <button
    type="button"
    class={`${buttonBaseClass} ${mode === "grid" ? activeButtonClass : inactiveButtonClass}`}
    aria-label="Show as grid"
    aria-pressed={mode === "grid"}
    title="Show as grid"
    onclick={() => selectMode("grid")}
  >
    <Grid3X3 aria-hidden="true" size={18} strokeWidth={2} />
  </button>
  <button
    type="button"
    class={`${buttonBaseClass} ${mode === "table" ? activeButtonClass : inactiveButtonClass}`}
    aria-label="Show as table"
    aria-pressed={mode === "table"}
    title="Show as table"
    onclick={() => selectMode("table")}
  >
    <Table2 aria-hidden="true" size={18} strokeWidth={2} />
  </button>
</div>
