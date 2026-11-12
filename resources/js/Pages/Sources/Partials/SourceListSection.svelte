<script lang="ts">
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import SourcesGrid from "./SourcesGrid.svelte";
  import SourcesTable from "./SourcesTable.svelte";
  import type { ListViewMode } from "@/lib/listViewMode";
  import type { SourceListItem } from "@/types/pages";

  interface Props {
    title: string;
    sources?: SourceListItem[];
    total?: number;
    emptyAllMessage: string;
    emptyPageMessage: string;
    mode?: ListViewMode;
    sourceDetailsUrl: (source: SourceListItem) => string;
  }

  let {
    title,
    sources = [],
    total = 0,
    emptyAllMessage,
    emptyPageMessage,
    mode = "grid",
    sourceDetailsUrl,
  }: Props = $props();
</script>

<div class="grid gap-4">
  <SectionHeading eyebrow={title} />
  {#if total === 0}
    <p class="text-sm ui-muted">{emptyAllMessage}</p>
  {:else if sources.length === 0}
    <p class="text-sm ui-muted">{emptyPageMessage}</p>
  {:else if mode === "grid"}
    <SourcesGrid {sources} {sourceDetailsUrl} />
  {:else}
    <SourcesTable {sources} {sourceDetailsUrl} />
  {/if}
</div>
