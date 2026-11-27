<script lang="ts">
  import Badge from "@/Components/ui/Badge.svelte";
  import Link from "@/Components/core/Link.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import { sourceIssue } from "../packageSource";
  import type { PackagesIndexSection } from "@/types/pages";

  interface Props {
    sections?: PackagesIndexSection[];
    anonymousAccessEnabled?: boolean;
    withOrganization: (path: string) => string;
    packageDetailsUrl: (section: PackagesIndexSection) => string;
  }

  let {
    sections = [],
    anonymousAccessEnabled = false,
    withOrganization,
    packageDetailsUrl,
  }: Props = $props();

  function accessLabel(section: PackagesIndexSection): string {
    return section.visibility === "private" || !anonymousAccessEnabled
      ? "Token required"
      : "Public";
  }

  function accessTone(section: PackagesIndexSection): string {
    return section.visibility === "private" || !anonymousAccessEnabled
      ? "danger"
      : "accent";
  }
</script>

<TableFrame ariaLabel="Packages" tableClassName="min-w-[720px]">
  <thead>
    <tr>
      <th class="px-4 py-3">Package</th>
      <th class="px-4 py-3">Latest</th>
      <th class="px-4 py-3">Access</th>
      <th class="px-4 py-3">Listed</th>
      <th class="px-4 py-3 text-right">Downloads</th>
      <th class="px-4 py-3">Source</th>
    </tr>
  </thead>
  <tbody>
    {#each sections as section (section.name)}
      {@const issue = section.source ? sourceIssue(section.source) : null}
      <tr class="align-top">
        <td class="px-4 py-3">
          <Link
            class="ui-link-accent font-semibold"
            href={packageDetailsUrl(section)}
          >
            {section.name}
          </Link>
          <p class="mt-1 line-clamp-1 max-w-[22rem] text-sm ui-muted">
            {section.description ?? "No description provided."}
          </p>
        </td>
        <td class="px-4 py-3 ui-muted">
          {section.latest_version ?? "No available version"}
        </td>
        <td class="px-4 py-3">
          <Badge className="whitespace-nowrap" tone={accessTone(section)}>
            {accessLabel(section)}
          </Badge>
        </td>
        <td class="px-4 py-3">
          <Badge
            className="whitespace-nowrap"
            tone={section.active ? "accent" : "muted"}
          >
            {section.active ? "Listed" : "Hidden"}
          </Badge>
        </td>
        <td class="px-4 py-3 text-right tabular-nums">
          {section.download_count}
        </td>
        <td class="px-4 py-3">
          {#if section.source}
            <div class="flex flex-wrap items-center gap-2">
              <Link
                class="ui-link-accent font-medium break-all"
                href={withOrganization(`/sources/${section.source.id}`)}
              >
                {section.source.name}
              </Link>
              {#if issue}
                <Badge className="whitespace-nowrap" tone="danger">
                  {issue}
                </Badge>
              {/if}
            </div>
          {:else}
            <span class="text-sm ui-muted">Manual</span>
          {/if}
        </td>
      </tr>
    {/each}
  </tbody>
</TableFrame>
