<script lang="ts">
  import Badge from "@/Components/ui/Badge.svelte";
  import Link from "@/Components/core/Link.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import { formatDate } from "@/lib/date";
  import type { SourceListItem } from "@/types/pages";
  import {
    sourceStateLabel,
    sourceStateTone,
    syncSummary,
  } from "../sourceStatus";

  interface Props {
    sources?: SourceListItem[];
    sourceDetailsUrl: (source: SourceListItem) => string;
  }

  let { sources = [], sourceDetailsUrl }: Props = $props();
</script>

<TableFrame ariaLabel="Sources" tableClassName="min-w-[720px]">
  <thead>
    <tr>
      <th class="w-full px-4 py-3">Source</th>
      <th class="w-px whitespace-nowrap px-4 py-3">Provider</th>
      <th class="w-px whitespace-nowrap px-4 py-3">State</th>
      <th class="w-px whitespace-nowrap px-4 py-3">Visibility</th>
      <th class="w-px whitespace-nowrap px-4 py-3 text-right">Last sync</th>
    </tr>
  </thead>
  <tbody>
    {#each sources as source (source.id)}
      {@const sync = syncSummary(source)}
      <tr class="align-top">
        <td class="w-full max-w-0 px-4 py-3">
          <Link
            class="ui-link-accent block truncate font-semibold"
            href={sourceDetailsUrl(source)}
            title={source.name}
          >
            {source.name}
          </Link>
        </td>
        <td class="w-px whitespace-nowrap px-4 py-3 ui-muted">
          {source.provider_label ?? source.provider ?? "unknown"}
        </td>
        <td class="w-px whitespace-nowrap px-4 py-3">
          <Badge className="whitespace-nowrap" tone={sourceStateTone(source)}>
            {sourceStateLabel(source)}
          </Badge>
        </td>
        <td class="w-px whitespace-nowrap px-4 py-3">
          <Badge
            className="whitespace-nowrap"
            tone={source.visibility === "private" ? "danger" : "accent"}
          >
            {source.visibility}
          </Badge>
        </td>
        <td class="w-px px-4 py-3">
          <div class="flex items-center justify-end gap-2 whitespace-nowrap">
            <Badge className="whitespace-nowrap" tone={sync.tone}>
              {sync.label}
            </Badge>
            {#if sync.at}
              <span class="text-[11px] ui-muted">{formatDate(sync.at)}</span>
            {/if}
          </div>
        </td>
      </tr>
    {/each}
  </tbody>
</TableFrame>
