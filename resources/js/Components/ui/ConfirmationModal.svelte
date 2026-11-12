<script lang="ts">
  import { X } from "@lucide/svelte";
  import { type Snippet, tick } from "svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";

  type ConfirmVariant = "danger" | "primary";

  let {
    open = false,
    title = "Confirm action",
    description = "",
    eyebrow = "Confirmation",
    confirmLabel = "Confirm",
    cancelLabel = "Cancel",
    variant = "danger",
    confirming = false,
    confirmDisabled = false,
    onConfirm = () => {},
    onCancel = () => {},
    children,
  }: {
    open?: boolean;
    title?: string;
    description?: string;
    eyebrow?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: ConfirmVariant;
    confirming?: boolean;
    confirmDisabled?: boolean;
    onConfirm?: () => void | Promise<void>;
    onCancel?: () => void;
    children?: Snippet;
  } = $props();

  const confirmVariant = $derived(variant === "danger" ? "danger" : "primary");

  let actions: HTMLElement | undefined = $state();

  // shortcut: no Tab trap, add one if dialogs get more interactive content
  $effect(() => {
    if (!open) {
      return;
    }

    const previous = document.activeElement;
    void tick().then(() => {
      actions
        ?.querySelector<HTMLElement>('[data-variant="secondary"]')
        ?.focus();
    });

    return () => {
      if (previous instanceof HTMLElement && previous.isConnected) {
        previous.focus();
      }
    };
  });

  function cancel(): void {
    if (confirming) {
      return;
    }

    onCancel();
  }

  function handleKeydown(event: KeyboardEvent): void {
    if (!open || event.key !== "Escape") {
      return;
    }

    cancel();
  }
</script>

<svelte:window onkeydown={handleKeydown} />

{#if open}
  <div
    class="fixed inset-0 z-50 overflow-y-auto bg-(--page-bg)/75 p-3 backdrop-blur sm:p-4"
    role="presentation"
    onclick={(event) => event.currentTarget === event.target && cancel()}
  >
    <div
      class="mx-auto flex min-h-full w-full max-w-lg items-start py-3 sm:items-center sm:py-4"
      aria-modal="true"
      aria-labelledby="confirmation-modal-title"
      role="dialog"
    >
      <Card
        className="max-h-[calc(100dvh-1.5rem)] w-full overflow-y-auto rounded-[1.4rem] p-0 sm:max-h-[calc(100dvh-2rem)]"
      >
        <div class="grid gap-5 p-5 sm:p-6">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              {#if eyebrow}
                <p class="ui-eyebrow">{eyebrow}</p>
              {/if}
              <h2
                id="confirmation-modal-title"
                class="ui-title mt-2 text-xl font-semibold"
              >
                {title}
              </h2>
              {#if description}
                <p class="mt-2 text-sm leading-6 ui-muted">{description}</p>
              {/if}
            </div>

            <Button
              type="button"
              variant="iconSmall"
              aria-label="Close confirmation"
              disabled={confirming}
              onclick={cancel}
            >
              <X aria-hidden="true" class="h-4 w-4" strokeWidth={2} />
            </Button>
          </div>

          {@render children?.()}

          <div
            bind:this={actions}
            class="grid gap-3 sm:flex sm:flex-wrap sm:justify-end"
          >
            <Button
              type="button"
              variant="secondary"
              className="w-full rounded-full px-5 sm:w-auto"
              disabled={confirming}
              onclick={cancel}
            >
              {cancelLabel}
            </Button>
            <Button
              type="button"
              variant={confirmVariant}
              className="w-full rounded-full px-5 sm:w-auto"
              disabled={confirming || confirmDisabled}
              onclick={onConfirm}
            >
              {confirming ? "Working..." : confirmLabel}
            </Button>
          </div>
        </div>
      </Card>
    </div>
  </div>
{/if}
