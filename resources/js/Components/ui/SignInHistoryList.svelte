<script lang="ts">
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import { browserTimeZone, formatDate } from "@/lib/date";
  import { describeUserAgent } from "@/lib/userAgent";
  import type { SignInHistoryEntry } from "@/types/pages";

  let { entries }: { entries: SignInHistoryEntry[] } = $props();

  const zoneLabel = `Local time (${browserTimeZone()})`;
</script>

{#if entries.length === 0}
  <p class="text-sm ui-muted">No sign-ins recorded yet.</p>
{:else}
  <p class="mb-2 text-xs ui-muted">{zoneLabel}</p>
  <TableFrame ariaLabel="Sign-in history" tableClassName="whitespace-nowrap">
    <thead>
      <tr>
        <th class="px-4 py-3">Time</th>
        <th class="px-4 py-3">Method</th>
        <th class="px-4 py-3">IP address</th>
        <th class="px-4 py-3">Browser</th>
      </tr>
    </thead>
    <tbody>
      {#each entries as entry (entry.id)}
        <tr>
          <td class="px-4 py-3">{formatDate(entry.occurred_at)}</td>
          <td class="px-4 py-3">{entry.method_label}</td>
          <td class="px-4 py-3 font-mono text-xs">{entry.ip ?? "—"}</td>
          <td class="px-4 py-3" title={entry.user_agent ?? ""}>
            {describeUserAgent(entry.user_agent)}
          </td>
        </tr>
      {/each}
    </tbody>
  </TableFrame>
{/if}
