<script lang="ts">
  import Badge from "@/Components/ui/Badge.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Link from "@/Components/core/Link.svelte";
  import { sourceIssue } from "../packageSource";
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
  let issue = $derived(section.source ? sourceIssue(section.source) : null);
</script>

<Card as="article" className="flex h-full flex-col rounded-[1.75rem] p-6">
  <p class="ui-title text-2xl font-semibold truncate">
    <Link class="ui-link-accent" href={packageDetailsUrl(section)}>
      {section.name}
    </Link>
  </p>

  <p class="ui-muted mt-2 text-sm leading-6 line-clamp-2">
    {section.description ?? "No description provided."}
  </p>

  <div class="mt-4 flex flex-wrap gap-2">
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

  <div class="mt-4 grid gap-2 text-sm ui-muted">
    <p class="truncate">
      <span class="font-medium ui-title">Latest:</span>
      {section.latest_version ?? "No available version"}
    </p>
    <p>
      <span class="font-medium ui-title">Downloads:</span>
      {section.download_count}
    </p>
  </div>

  <div class="mt-auto flex flex-wrap items-center gap-2 pt-4 text-sm">
    {#if section.source}
      <Link
        class="ui-link-accent font-medium break-all"
        href={withOrganization(`/sources/${section.source.id}`)}
      >
        {section.source.name}
      </Link>
      {#if issue}
        <Badge className="whitespace-nowrap" tone="danger">{issue}</Badge>
      {/if}
    {:else}
      <span class="ui-muted">Manual</span>
    {/if}
  </div>
</Card>
