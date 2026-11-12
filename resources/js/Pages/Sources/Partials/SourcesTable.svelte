<script lang="ts">
  import Badge from "@/Components/ui/Badge.svelte";
  import Link from "@/Components/core/Link.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import { formatDate } from "@/lib/date";
  import type { SourceListItem } from "@/types/pages";

  interface Props {
    sources?: SourceListItem[];
    sourceDetailsUrl: (source: SourceListItem) => string;
  }

  let { sources = [], sourceDetailsUrl }: Props = $props();

  function syncTone(status: string): string {
    if (status === "failed") {
      return "danger";
    }

    return status === "success" ? "accent" : "gold";
  }

  function retentionLabel(source: SourceListItem): string {
    return source.keep_last_releases > 0
      ? `keeps latest ${source.keep_last_releases}`
      : "all releases";
  }

  function sourceState(source: SourceListItem): string {
    if (source.archived) {
      return "archived";
    }

    return source.active ? "active" : "inactive";
  }

  function lastSuccessLabel(source: SourceListItem): string {
    return source.last_successful_sync_at
      ? formatDate(source.last_successful_sync_at)
      : "not synced yet";
  }

  function lastFailureLabel(source: SourceListItem): string {
    return source.last_failed_sync_at
      ? formatDate(source.last_failed_sync_at)
      : "no failures";
  }
</script>

<TableFrame ariaLabel="Sources" tableClassName="min-w-[1040px]">
  <thead>
    <tr>
      <th class="px-4 py-3">Source</th>
      <th class="px-4 py-3">Provider</th>
      <th class="px-4 py-3">Default ref</th>
      <th class="px-4 py-3">Visibility</th>
      <th class="px-4 py-3">Status</th>
      <th class="px-4 py-3">Last sync</th>
      <th class="px-4 py-3">Retention</th>
      <th class="px-4 py-3 text-right">Action</th>
    </tr>
  </thead>
  <tbody>
    {#each sources as source (source.id)}
      <tr class="align-top">
        <td class="px-4 py-3">
          <Link
            class="ui-link-accent font-semibold"
            href={sourceDetailsUrl(source)}
          >
            {source.name}
          </Link>
          <p class="mt-1 max-w-[20rem] break-all text-xs ui-muted line-clamp-2">
            {source.url}
          </p>
        </td>
        <td class="px-4 py-3 ui-muted">
          {source.provider_label ?? source.provider ?? "unknown"}
        </td>
        <td class="px-4 py-3">
          <span class="font-mono text-xs ui-muted">{source.default_ref}</span>
        </td>
        <td class="px-4 py-3">
          <Badge
            className="whitespace-nowrap"
            tone={source.visibility === "private" ? "danger" : "accent"}
          >
            {source.visibility}
          </Badge>
        </td>
        <td class="px-4 py-3">
          <div class="flex flex-wrap gap-2">
            {#if source.archived}
              <Badge className="whitespace-nowrap" tone="muted">
                {sourceState(source)}
              </Badge>
            {:else}
              <Badge
                className="whitespace-nowrap"
                tone={source.active ? "accent" : "muted"}
              >
                {source.active ? "active" : "inactive"}
              </Badge>
              <Badge
                className="whitespace-nowrap"
                tone={syncTone(source.last_sync_status)}
              >
                {source.last_sync_status}
              </Badge>
            {/if}
          </div>
        </td>
        <td class="px-4 py-3 text-xs ui-muted">
          {#if source.archived}
            archived {source.archived_at
              ? formatDate(source.archived_at)
              : "date unknown"}
          {:else}
            <div class="grid gap-1.5">
              <p
                class="flex cursor-help items-center gap-2 whitespace-nowrap"
                title="Last successful sync"
                aria-label={`Last successful sync: ${lastSuccessLabel(source)}`}
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  aria-hidden="true"
                  style={`background-color: ${
                    source.last_successful_sync_at
                      ? "var(--accent)"
                      : "var(--page-muted)"
                  };`}
                ></span>
                {lastSuccessLabel(source)}
              </p>
              <p
                class="flex cursor-help items-center gap-2 whitespace-nowrap"
                title="Last failed sync"
                aria-label={`Last failed sync: ${lastFailureLabel(source)}`}
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  aria-hidden="true"
                  style={`background-color: ${
                    source.last_failed_sync_at
                      ? "var(--flash-error-text)"
                      : "var(--page-muted)"
                  };`}
                ></span>
                {lastFailureLabel(source)}
              </p>
            </div>
          {/if}
        </td>
        <td class="px-4 py-3 ui-muted">
          {retentionLabel(source)}
        </td>
        <td class="px-4 py-3 text-right">
          <Link
            class="ui-button-secondary inline-flex whitespace-nowrap rounded-full px-4 py-2 text-xs font-medium"
            href={sourceDetailsUrl(source)}
          >
            Open details
          </Link>
        </td>
      </tr>
    {/each}
  </tbody>
</TableFrame>
