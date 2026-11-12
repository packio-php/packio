<script>
  import CopyButton from "@/Components/ui/CopyButton.svelte";
  import Link from "@/Components/core/Link.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";

  let {
    title = "",
    content = "",
    copyContent = "",
    multiline = false,
    className = "",
    actionPlaceholder = "",
    actionHref = "",
    actionLabel = "",
  } = $props();

  const copiedContent = $derived(copyContent || content);
  const shouldRenderAction = $derived(
    Boolean(actionPlaceholder) &&
      Boolean(actionHref) &&
      content.includes(actionPlaceholder),
  );
  const contentParts = $derived(
    shouldRenderAction ? content.split(actionPlaceholder) : [content],
  );
</script>

<InsetCard className={`min-w-0 p-4 ${className}`.trim()}>
  <div class="flex items-start justify-between gap-3">
    {#if title}
      <p class="font-medium ui-title">{title}</p>
    {:else}
      <span></span>
    {/if}

    <CopyButton
      value={copiedContent}
      label={title ? `Copy ${title}` : "Copy"}
    />
  </div>

  {#if multiline}
    <pre
      class="ui-code mt-3 min-w-0 overflow-x-auto rounded-xl p-3 text-xs">{#if shouldRenderAction}{contentParts[0]}<Link
          class="ui-button-secondary inline-flex h-6 items-center rounded-lg px-2 text-[0.7rem] font-medium"
          href={actionHref}>{actionLabel}</Link
        >{contentParts
          .slice(1)
          .join(actionPlaceholder)}{:else}{content}{/if}</pre>
  {:else}
    <code
      class="ui-code mt-3 block min-w-0 overflow-x-auto rounded-xl px-3 py-2 text-xs"
      >{#if shouldRenderAction}{contentParts[0]}<Link
          class="ui-button-secondary inline-flex h-6 items-center rounded-lg px-2 text-[0.7rem] font-medium"
          href={actionHref}>{actionLabel}</Link
        >{contentParts
          .slice(1)
          .join(actionPlaceholder)}{:else}{content}{/if}</code
    >
  {/if}
</InsetCard>
