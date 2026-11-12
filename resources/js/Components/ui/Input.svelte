<script lang="ts">
  import type { HTMLInputAttributes } from "svelte/elements";

  const baseClassName =
    "ui-button-secondary min-w-0 w-full rounded-2xl px-4 py-3 text-sm outline-none transition-colors";

  let {
    value = $bindable(),
    invalid = false,
    valid = false,
    className = "",
    element = $bindable(),
    ...restProps
  }: {
    value?: string | number;
    invalid?: boolean;
    valid?: boolean;
    className?: string;
    element?: HTMLInputElement | null;
  } & Omit<HTMLInputAttributes, "value"> = $props();

  const classes = $derived(
    `${baseClassName} ${
      invalid
        ? "border-[var(--flash-error-border)] ring-1 ring-[var(--flash-error-border)]"
        : valid
          ? "border-[var(--flash-success-border)] ring-1 ring-[var(--flash-success-border)]"
          : ""
    } ${className}`.trim(),
  );
</script>

<input
  bind:value
  bind:this={element}
  class={classes}
  aria-invalid={invalid}
  {...restProps}
/>
