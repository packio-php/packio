<script lang="ts">
  import { providerIconKey } from "@/lib/providerPresentation";
  import { siBitbucket, siCodeberg, siGithub, siGitlab } from "simple-icons";

  const providerIcons = {
    bitbucket: siBitbucket,
    codeberg: siCodeberg,
    github: siGithub,
    gitlab: siGitlab,
  };

  let {
    provider,
    className = "size-5",
    title = "",
  }: {
    provider: string;
    className?: string;
    title?: string;
  } = $props();

  const resolvedProvider = $derived(providerIconKey(provider));
  const icon = $derived(
    providerIcons[resolvedProvider as keyof typeof providerIcons],
  );
</script>

{#if icon}
  <svg
    viewBox="0 0 24 24"
    class={className}
    aria-hidden={title ? "false" : "true"}
    role={title ? "img" : undefined}
  >
    {#if title}
      <title>{title}</title>
    {/if}
    <path fill="currentColor" d={icon.path} />
  </svg>
{:else}
  <svg
    viewBox="0 0 24 24"
    class={className}
    aria-hidden={title ? "false" : "true"}
    role={title ? "img" : undefined}
  >
    {#if title}
      <title>{title}</title>
    {/if}
    <circle
      cx="12"
      cy="12"
      r="9"
      fill="none"
      stroke="currentColor"
      stroke-width="1.75"
    />
    <path
      fill="none"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
      stroke-width="1.75"
      d="M8 12h8M12 8v8"
    />
  </svg>
{/if}
