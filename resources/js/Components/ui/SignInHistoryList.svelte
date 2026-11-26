<script lang="ts">
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import { browserTimeZone, formatDate } from "@/lib/date";
  import { describeUserAgent } from "@/lib/userAgent";
  import type { SignInHistoryEntry } from "@/types/pages";

  let { entries }: { entries: SignInHistoryEntry[] } = $props();

  const zoneLabel = `Local time (${browserTimeZone()})`;
  // One DOM for both layouts: below md the table collapses into cards via CSS,
  // so every row is rendered (and read by screen readers) once.
  const cell =
    "px-4 py-1.5 max-md:flex max-md:gap-3 max-md:before:w-20 max-md:before:shrink-0 max-md:before:font-semibold max-md:before:content-[attr(data-label)] md:py-3";
</script>

{#if entries.length === 0}
  <p class="text-sm ui-muted">No sign-ins recorded yet.</p>
{:else}
  <!-- Single caption visible at every width: the thead is hidden below md. -->
  <p class="mb-2 text-xs ui-muted">{zoneLabel}</p>
  <TableFrame
    ariaLabel="Sign-in history"
    tableClassName="max-md:block [&_tbody]:max-md:block [&_tr]:max-md:block"
  >
    <thead class="max-md:hidden">
      <tr>
        <th class="px-4 py-3">Time</th>
        <th class="px-4 py-3">Method</th>
        <th class="px-4 py-3">IP address</th>
        <th class="px-4 py-3">Browser</th>
      </tr>
    </thead>
    <tbody>
      {#each entries as entry (entry.id)}
        <tr class="max-md:py-2">
          <td class={`${cell} md:whitespace-nowrap`} data-label="Time">
            {formatDate(entry.occurred_at)}
          </td>
          <td class={cell} data-label="Method">{entry.method_label}</td>
          <td class={`${cell} font-mono text-xs`} data-label="IP address">
            <span class="break-all">{entry.ip ?? "—"}</span>
          </td>
          <td class={cell} data-label="Browser" title={entry.user_agent ?? ""}>
            {describeUserAgent(entry.user_agent)}
          </td>
        </tr>
      {/each}
    </tbody>
  </TableFrame>
{/if}
