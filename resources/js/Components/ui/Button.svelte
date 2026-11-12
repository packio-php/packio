<script lang="ts">
  import type { Snippet } from "svelte";
  import type { HTMLButtonAttributes } from "svelte/elements";

  type ButtonType = "button" | "reset" | "submit";
  type ButtonVariant =
    | "chip"
    | "chipDanger"
    | "icon"
    | "iconSmall"
    | "primary"
    | "secondary"
    | "soft"
    | "successSoft"
    | "dangerSoft"
    | "danger";

  type ButtonProps = {
    type?: ButtonType;
    variant?: ButtonVariant;
    disabled?: boolean;
    className?: string;
    children?: Snippet;
  } & HTMLButtonAttributes;

  const variants: Record<ButtonVariant, string> = {
    primary:
      "inline-flex h-11 cursor-pointer items-center justify-center rounded-lg px-5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60",
    secondary:
      "inline-flex h-11 cursor-pointer items-center justify-center rounded-lg border px-5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60",
    danger:
      "inline-flex h-11 cursor-pointer items-center justify-center rounded-lg px-5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60",
    soft: "inline-flex cursor-pointer items-center rounded-xl border px-3 py-2 text-sm font-medium transition hover:border-[var(--accent)] hover:text-[var(--accent)] disabled:cursor-not-allowed disabled:opacity-60",
    successSoft:
      "inline-flex cursor-pointer items-center rounded-xl border px-3 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60",
    dangerSoft:
      "inline-flex cursor-pointer items-center rounded-xl border px-3 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60",
    icon: "inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border transition hover:scale-[1.02]",
    iconSmall:
      "inline-flex cursor-pointer shrink-0 items-center justify-center rounded-lg border text-lg w-9 h-9 transition hover:border-[var(--accent)] hover:text-[var(--accent)] disabled:cursor-not-allowed disabled:opacity-60",
    chip: "cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium transition hover:border-[var(--accent)] hover:text-[var(--accent)] disabled:cursor-not-allowed disabled:opacity-60",
    chipDanger:
      "cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-60",
  };

  let {
    type = "button",
    variant = "soft",
    disabled = false,
    className = "",
    children,
    ...restProps
  }: ButtonProps = $props();

  const classes = $derived(
    `${variants[variant] ?? variants.soft} ${className}`.trim(),
  );
</script>

<button {type} {disabled} class={classes} data-variant={variant} {...restProps}>
  {@render children?.()}
</button>

<style>
  button[data-variant="primary"] {
    background-color: var(--button-primary-bg);
    color: var(--button-primary-text);
  }

  button[data-variant="primary"]:hover:not(:disabled) {
    background-color: var(--button-primary-hover);
  }

  button[data-variant="secondary"],
  button[data-variant="soft"],
  button[data-variant="icon"],
  button[data-variant="iconSmall"],
  button[data-variant="chip"] {
    border-color: var(--button-secondary-border);
    background-color: var(--button-secondary-bg);
    color: var(--button-secondary-text);
  }

  button[data-variant="secondary"]:hover:not(:disabled),
  button[data-variant="soft"]:hover:not(:disabled),
  button[data-variant="icon"]:hover:not(:disabled),
  button[data-variant="iconSmall"]:hover:not(:disabled),
  button[data-variant="chip"]:hover:not(:disabled) {
    border-color: var(--button-secondary-hover);
    background-color: var(--button-secondary-hover);
  }

  button[data-variant="successSoft"] {
    border-color: var(--flash-success-border);
    background-color: var(--flash-success-bg);
    color: var(--flash-success-text);
  }

  button[data-variant="successSoft"]:hover:not(:disabled) {
    border-color: color-mix(
      in srgb,
      var(--flash-success-border) 70%,
      var(--flash-success-text)
    );
    background-color: color-mix(
      in srgb,
      var(--flash-success-bg) 82%,
      var(--flash-success-text)
    );
  }

  button[data-variant="danger"] {
    background-color: var(--button-danger-bg);
    color: var(--button-danger-text);
  }

  button[data-variant="danger"]:hover:not(:disabled) {
    background-color: var(--button-danger-hover);
  }

  button[data-variant="chipDanger"] {
    border-color: var(--button-danger-soft-border);
    background-color: var(--button-danger-soft-bg);
    color: var(--button-danger-soft-text);
  }

  button[data-variant="dangerSoft"] {
    border-color: var(--button-danger-soft-border);
    background-color: var(--button-danger-soft-bg);
    color: var(--button-danger-soft-text);
  }

  button[data-variant="chipDanger"]:hover:not(:disabled),
  button[data-variant="dangerSoft"]:hover:not(:disabled) {
    border-color: color-mix(
      in srgb,
      var(--button-danger-soft-border) 65%,
      var(--button-danger-hover)
    );
    background-color: color-mix(
      in srgb,
      var(--button-danger-soft-bg) 70%,
      var(--button-danger-hover)
    );
  }

  button[data-variant="iconSmall"] {
    line-height: 0;
  }

  button[data-variant]:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--accent) 60%, transparent);
    outline-offset: 2px;
  }
</style>
