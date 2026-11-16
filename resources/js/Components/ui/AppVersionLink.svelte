<script lang="ts">
  import type { AppVersionInfo } from "@/types/app";

  const repositoryUrl = "https://github.com/packio-php/packio";

  interface Props {
    version: AppVersionInfo;
  }

  let { version }: Props = $props();

  // Dev builds carry the next, not yet released version: only the commit exists on GitHub.
  let releaseUrl = $derived(
    version.channel === "stable" && version.tag
      ? `${repositoryUrl}/releases/tag/${version.tag}`
      : null,
  );
</script>

<span class="text-[10px] tracking-[0.14em] opacity-75">
  {#if releaseUrl}
    <a
      class="hover:underline"
      href={releaseUrl}
      target="_blank"
      rel="noopener noreferrer">v{version.number}</a
    >
  {:else}
    v{version.number}
  {/if}
  {#if version.sha}
    (<a
      class="hover:underline"
      href={`${repositoryUrl}/commit/${version.sha}`}
      target="_blank"
      rel="noopener noreferrer">{version.sha}</a
    >)
  {/if}
</span>
