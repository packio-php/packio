<script lang="ts">
  import Card from "@/Components/ui/Card.svelte";
  import Link from "@/Components/core/Link.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import { formatDate } from "@/lib/date";
  import type { SourceListItem } from "@/types/pages";

  interface Props {
    source: SourceListItem;
    sourceDetailsUrl: (source: SourceListItem) => string;
  }

  let { source, sourceDetailsUrl }: Props = $props();

  let sourceState = $derived(
    source.archived ? "archived" : source.active ? "active" : "inactive",
  );
</script>

<Card as="article" className="flex h-full flex-col rounded-[1.75rem] p-6">
  <p class="ui-title text-2xl font-semibold truncate">
    <Link class="ui-link-accent" href={sourceDetailsUrl(source)}>
      {source.name}
    </Link>
  </p>

  <p class="ui-muted mt-3 text-sm leading-6 line-clamp-2 min-h-[3rem]">
    {source.url}
  </p>

  <div class="mt-4 grid gap-2 text-sm ui-muted">
    <p class="truncate">
      <span class="font-medium ui-title">Provider:</span>
      {source.provider_label ?? source.provider ?? "unknown"}
    </p>
    <p class="truncate">
      <span class="font-medium ui-title">Default ref:</span>
      {source.default_ref}
    </p>
  </div>

  <div class="mt-4 flex flex-wrap gap-2">
    {#if source.archived}
      <span
        class={`whitespace-nowrap rounded-full px-3 py-1 text-xs font-medium ui-badge-muted`}
      >
        {sourceState}
      </span>
    {:else}
      <span
        class={`whitespace-nowrap rounded-full px-3 py-1 text-xs font-medium ${
          source.active ? "ui-badge-accent" : "ui-badge-muted"
        }`}
      >
        {source.active ? "active" : "inactive"}
      </span>
    {/if}
    <span
      class={`whitespace-nowrap rounded-full px-3 py-1 text-xs font-medium ${
        source.visibility === "private" ? "ui-badge-danger" : "ui-badge-accent"
      }`}
    >
      {source.visibility}
    </span>
    {#if source.keep_last_releases > 0}
      <span
        class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-medium ui-badge-muted"
      >
        keeps latest {source.keep_last_releases}
      </span>
    {/if}
  </div>

  {#if source.archived}
    <InsetCard className="mt-4 p-4 text-sm ui-muted">
      <p class="font-medium ui-title">Archived</p>
      <p class="mt-1">
        {source.archived_at ? formatDate(source.archived_at) : "date unknown"}
      </p>
    </InsetCard>
  {:else}
    <InsetCard className="mt-4 p-4 text-sm ui-muted">
      <p class="font-medium ui-title">
        Sync status: {source.last_sync_status}
      </p>
      <div class="mt-1 grid gap-1">
        <p
          class:invisible={!source.last_successful_sync_at}
          aria-hidden={!source.last_successful_sync_at}
        >
          last success {source.last_successful_sync_at
            ? formatDate(source.last_successful_sync_at)
            : "not synced yet"}
        </p>
        <p
          class:invisible={!source.last_failed_sync_at}
          aria-hidden={!source.last_failed_sync_at}
        >
          last failure {source.last_failed_sync_at
            ? formatDate(source.last_failed_sync_at)
            : "no failures"}
        </p>
      </div>
      <p
        class="mt-2 min-h-[2.5rem] break-all line-clamp-2"
        class:invisible={!source.last_sync_error}
        aria-hidden={!source.last_sync_error}
        style="color: var(--flash-error-text);"
      >
        {source.last_sync_error ?? "no sync error"}
      </p>
    </InsetCard>
  {/if}

  <div class="mt-4">
    <Link
      class="ui-button-secondary inline-flex rounded-full px-4 py-2 text-xs font-medium"
      href={sourceDetailsUrl(source)}
    >
      Open source details
    </Link>
  </div>
</Card>
