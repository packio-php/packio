<script lang="ts">
  import SourceCard from "./SourceCard.svelte";
  import type { SourceListItem } from "@/types/pages";

  interface Props {
    sources?: SourceListItem[];
    sourceDetailsUrl: (source: SourceListItem) => string;
  }

  let { sources = [], sourceDetailsUrl }: Props = $props();
</script>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
  {#each sources as source (source.id)}
    <SourceCard {source} {sourceDetailsUrl} />
  {/each}
</div>
