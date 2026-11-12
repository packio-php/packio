<script lang="ts">
  import Link from "@/Components/core/Link.svelte";

  let {
    href = "#",
    label = "Back",
  }: {
    href?: string;
    label?: string;
  } = $props();
</script>

<div class="flex items-center gap-4">
  <Link
    {href}
    class="inline-flex h-10 items-center justify-center rounded-full bg-(--panel-bg) border border-(--panel-border) px-5 text-xs font-semibold ui-title hover:bg-(--panel-bg-hover) transition-colors"
  >
    <span class="mr-1.5 flex items-center opacity-60">←</span>
    {label}
  </Link>
</div>
