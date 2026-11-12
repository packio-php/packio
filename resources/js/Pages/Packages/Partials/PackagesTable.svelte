<script lang="ts">
  import Badge from "@/Components/ui/Badge.svelte";
  import Link from "@/Components/core/Link.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
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

  function syncTone(status: string): string {
    if (status === "failed") {
      return "danger";
    }

    return status === "success" ? "accent" : "gold";
  }
</script>

<TableFrame ariaLabel="Packages" tableClassName="min-w-[900px]">
  <thead>
    <tr>
      <th class="px-4 py-3">Package</th>
      <th class="px-4 py-3">Access</th>
      <th class="px-4 py-3">Listed</th>
      <th class="px-4 py-3">Latest</th>
      <th class="px-4 py-3 text-right">Downloads</th>
      <th class="px-4 py-3">Source</th>
      <th class="px-4 py-3 text-right">Action</th>
    </tr>
  </thead>
  <tbody>
    {#each sections as section (section.name)}
      <tr class="align-top">
        <td class="px-4 py-3">
          <Link
            class="ui-link-accent font-semibold"
            href={packageDetailsUrl(section)}
          >
            {section.name}
          </Link>
          <p class="mt-1 line-clamp-2 max-w-[22rem] text-sm ui-muted">
            {section.description ?? "No description provided."}
          </p>
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
        <td class="px-4 py-3 ui-muted">
          {section.latest_version ?? "not published yet"}
        </td>
        <td class="px-4 py-3 text-right tabular-nums">
          {section.download_count}
        </td>
        <td class="px-4 py-3">
          {#if section.source}
            <div class="grid gap-2">
              <Link
                class="ui-link-accent font-medium"
                href={withOrganization(`/sources/${section.source.id}`)}
              >
                {section.source.name}
              </Link>
              <p class="max-w-[18rem] break-all text-xs ui-muted line-clamp-2">
                {section.source.url}
              </p>
              <div class="flex flex-wrap gap-2">
                <Badge
                  className="whitespace-nowrap"
                  tone={syncTone(section.source.last_sync_status)}
                >
                  {section.source.last_sync_status}
                </Badge>
                <Badge
                  className="whitespace-nowrap"
                  tone={section.source.active ? "accent" : "muted"}
                >
                  {section.source.active ? "Source active" : "Source inactive"}
                </Badge>
              </div>
            </div>
          {:else}
            <span class="text-sm ui-muted">manual</span>
          {/if}
        </td>
        <td class="px-4 py-3 text-right">
          <Link
            class="ui-button-secondary inline-flex whitespace-nowrap rounded-full px-4 py-2 text-xs font-medium"
            href={packageDetailsUrl(section)}
          >
            Open details
          </Link>
        </td>
      </tr>
    {/each}
  </tbody>
</TableFrame>
