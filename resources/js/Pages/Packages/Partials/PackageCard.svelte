<script lang="ts">
  import Badge from "@/Components/ui/Badge.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import Link from "@/Components/core/Link.svelte";
  import { formatDate } from "@/lib/date";
  import type { PackagesIndexSection } from "@/types/pages";

  interface Props {
    section: PackagesIndexSection;
    anonymousAccessEnabled?: boolean;
    withOrganization: (path: string) => string;
    packageDetailsUrl: (section: PackagesIndexSection) => string;
  }

  let {
    section,
    anonymousAccessEnabled = false,
    withOrganization,
    packageDetailsUrl,
  }: Props = $props();

  let tokenRequired = $derived(
    section.visibility === "private" || !anonymousAccessEnabled,
  );
</script>

<Card as="article" className="flex h-full flex-col rounded-[1.75rem] p-6">
  <div class="flex items-center justify-between gap-3">
    <p class="ui-eyebrow">{section.visibility}</p>
    <div class="flex flex-wrap gap-2">
      <Badge
        className="whitespace-nowrap"
        tone={tokenRequired ? "danger" : "accent"}
      >
        {tokenRequired ? "Token required" : "Public"}
      </Badge>
      <Badge
        className="whitespace-nowrap"
        tone={section.active ? "accent" : "muted"}
      >
        {section.active ? "Listed" : "Hidden"}
      </Badge>
    </div>
  </div>

  <p class="ui-title mt-3 text-2xl font-semibold truncate">
    <Link class="ui-link-accent" href={packageDetailsUrl(section)}>
      {section.name}
    </Link>
  </p>

  <p class="ui-muted mt-3 text-sm leading-6 line-clamp-2 min-h-[3rem]">
    {section.description}
  </p>

  <div class="mt-4 grid gap-2 text-sm ui-muted">
    <p>
      <span class="font-medium ui-title">Downloads:</span>
      {section.download_count}
    </p>
    <p class="truncate">
      <span class="font-medium ui-title">Latest:</span>
      {section.latest_version ?? "not published yet"}
    </p>
  </div>

  {#if section.source}
    <InsetCard className="mt-4 p-4 text-sm ui-muted">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="font-medium ui-title">Managed by source</p>
          <p class="mt-2 truncate text-sm ui-title">
            {section.source.name}
          </p>
          <p
            class="mt-1 break-all text-xs ui-muted line-clamp-2 min-h-[2.25rem]"
          >
            {section.source.url}
          </p>
        </div>
        <Badge
          className="shrink-0 whitespace-nowrap"
          tone={section.source.last_sync_status === "failed"
            ? "danger"
            : section.source.last_sync_status === "success"
              ? "accent"
              : "gold"}
        >
          {section.source.last_sync_status}
        </Badge>
      </div>

      <div class="mt-3 flex flex-wrap gap-2">
        <Badge
          className="whitespace-nowrap"
          tone={section.source.active ? "accent" : "muted"}
        >
          {section.source.active ? "Source active" : "Source inactive"}
        </Badge>
        <Badge
          className="whitespace-nowrap"
          tone={section.source.visibility === "private" ? "danger" : "accent"}
        >
          {section.source.visibility}
        </Badge>
      </div>

      <div class="mt-3 grid gap-1 text-xs ui-muted">
        <p
          class:invisible={!section.source.last_successful_sync_at}
          aria-hidden={!section.source.last_successful_sync_at}
        >
          last success {section.source.last_successful_sync_at
            ? formatDate(section.source.last_successful_sync_at)
            : "not synced yet"}
        </p>
        <p
          class:invisible={!section.source.last_failed_sync_at}
          aria-hidden={!section.source.last_failed_sync_at}
        >
          last failure {section.source.last_failed_sync_at
            ? formatDate(section.source.last_failed_sync_at)
            : "no failures"}
        </p>
      </div>

      <Link
        class="ui-button-secondary mt-4 inline-flex rounded-full px-4 py-2 text-xs font-medium"
        href={withOrganization(`/sources/${section.source.id}`)}
      >
        Open source
      </Link>
    </InsetCard>
  {:else}
    <div aria-hidden="true">
      <InsetCard className="invisible mt-4 p-4 text-sm ui-muted">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="font-medium ui-title">Managed by source</p>
            <p class="mt-2 truncate text-sm ui-title">source/repository</p>
            <p
              class="mt-1 break-all text-xs ui-muted line-clamp-2 min-h-[2.25rem]"
            >
              https://example.com/source/repository
            </p>
          </div>
          <Badge className="shrink-0 whitespace-nowrap">success</Badge>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
          <Badge className="whitespace-nowrap">Source active</Badge>
          <Badge className="whitespace-nowrap">public</Badge>
        </div>

        <div class="mt-3 grid gap-1 text-xs ui-muted">
          <p>last success not synced yet</p>
          <p>last failure no failures</p>
        </div>

        <span
          class="ui-button-secondary mt-4 inline-flex rounded-full px-4 py-2 text-xs font-medium"
        >
          Open source
        </span>
      </InsetCard>
    </div>
  {/if}
</Card>
