<script lang="ts">
  import PackageCard from "./PackageCard.svelte";
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
</script>

<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
  {#each sections as section (section.name)}
    <PackageCard
      {section}
      {anonymousAccessEnabled}
      {withOrganization}
      {packageDetailsUrl}
    />
  {/each}
</section>
