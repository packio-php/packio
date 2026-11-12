<script lang="ts">
  import type { HTMLSelectAttributes } from "svelte/elements";
  import type { Snippet } from "svelte";

  const baseClassName =
    "ui-button-secondary min-w-0 cursor-pointer rounded-2xl px-4 py-3 text-sm outline-none transition-colors";

  let {
    value = $bindable(),
    className = "",
    children,
    ...restProps
  }: {
    value?: string | number;
    className?: string;
    children?: Snippet;
  } & Omit<HTMLSelectAttributes, "value"> = $props();

  const classes = $derived(`${baseClassName} ${className}`.trim());
</script>

<select bind:value class={classes} {...restProps}>
  {@render children?.()}
</select>
