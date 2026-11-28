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

  const label = $derived(copyLabel || (title ? `Copy ${title}` : "Copy"));
  const copiedContent = $derived(copyContent || content);
</script>

<InsetCard className={`min-w-0 p-4 ${className}`.trim()}>
  {#if multiline}
    <div class="flex items-start justify-between gap-3">
      {#if title}
        <p class="font-medium ui-title">{title}</p>
      {:else}
        <span></span>
      {/if}

      <div class="flex items-center gap-2">
        {@render actions?.()}
        <CopyButton value={copiedContent} {label} text={copyText} />
      </div>
    </div>
    <pre
      class="ui-code mt-3 min-w-0 overflow-x-auto rounded-xl p-3 text-xs">{content}</pre>
  {:else}
    {#if title}
      <p class="mb-3 font-medium ui-title">{title}</p>
    {/if}
    <div
      class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:gap-2"
    >
      <code
        class="ui-code block min-w-0 overflow-x-auto rounded-xl px-3 py-2 text-xs sm:flex-1"
      >
        {content}
      </code>
      <div class="flex shrink-0 items-center gap-2 self-end sm:self-auto">
        {@render actions?.()}
        <CopyButton value={copiedContent} {label} text={copyText} />
      </div>
    </div>
  {/if}
</InsetCard>
