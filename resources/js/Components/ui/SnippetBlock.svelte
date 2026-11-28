<script>
  import CopyButton from "@/Components/ui/CopyButton.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";

  let {
    title = "",
    content = "",
    copyContent = "",
    copyLabel = "",
    copyText = "",
    multiline = false,
    className = "",
    actions = undefined,
  } = $props();

  const copiedContent = $derived(copyContent || content);
</script>

<InsetCard className={`min-w-0 p-4 ${className}`.trim()}>
  <div class="flex items-start justify-between gap-3">
    {#if title}
      <p class="font-medium ui-title">{title}</p>
    {:else}
      <span></span>
    {/if}

    <div class="flex items-center gap-2">
      {@render actions?.()}
      <CopyButton
        value={copiedContent}
        label={copyLabel || (title ? `Copy ${title}` : "Copy")}
        text={copyText}
      />
    </div>
  </div>

  {#if multiline}
    <pre
      class="ui-code mt-3 min-w-0 overflow-x-auto rounded-xl p-3 text-xs">{content}</pre>
  {:else}
    <code
      class="ui-code mt-3 block min-w-0 overflow-x-auto rounded-xl px-3 py-2 text-xs"
    >
      {content}
    </code>
  {/if}
</InsetCard>
