<script lang="ts">
  import Badge from "@/Components/ui/Badge.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Link from "@/Components/core/Link.svelte";
  import { formatDate } from "@/lib/date";
  import type { SourceListItem } from "@/types/pages";
  import {
    sourceStateLabel,
    sourceStateTone,
    syncSummary,
  } from "../sourceStatus";

  interface Props {
    source: SourceListItem;
    sourceDetailsUrl: (source: SourceListItem) => string;
  }

  let { source, sourceDetailsUrl }: Props = $props();

  let sync = $derived(syncSummary(source));
</script>

<Card as="article" className="flex h-full flex-col rounded-[1.75rem] p-5">
  <p class="ui-title text-xl font-semibold truncate" title={source.name}>
    <Link class="ui-link-accent" href={sourceDetailsUrl(source)}>
      {source.name}
    </Link>
  </p>

  <p class="mt-1 truncate text-sm ui-muted">
    {source.provider_label ?? source.provider ?? "unknown"}
  </p>

  <div class="mt-3 flex flex-wrap gap-2">
    <Badge className="whitespace-nowrap" tone={sourceStateTone(source)}>
      {sourceStateLabel(source)}
    </Badge>
    <Badge
      className="whitespace-nowrap"
      tone={source.visibility === "private" ? "danger" : "accent"}
    >
      {source.visibility}
    </Badge>
  </div>

  <div class="mt-auto flex flex-wrap items-center gap-2 pt-3 text-xs ui-muted">
    <Badge className="whitespace-nowrap" tone={sync.tone}>{sync.label}</Badge>
    {#if sync.at}
      <span class="whitespace-nowrap text-[11px]">{formatDate(sync.at)}</span>
    {/if}
  </div>
</Card>
