<script lang="ts">
  import { X } from "@lucide/svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import type { ProviderAuthorizationPayload } from "@/types/pages";

  interface Props {
    authorization: ProviderAuthorizationPayload;
    check: {
      has_sources: boolean;
      source_count: number;
      sources: Array<{ id: string; name: string; has_packages: boolean }>;
      other_authorizations: Array<{
        id: string;
        provider: string;
        auth_strategy: string;
        masked_value: string;
      }>;
    } | null;
    onClose: () => void;
    onSubmit: (
      event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
    ) => void;
    withOrganization: (path: string) => string;
  }

  let { authorization, check, onClose, onSubmit, withOrganization }: Props =
    $props();
</script>

<div
  class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4"
  role="dialog"
  aria-modal="true"
  tabindex="-1"
  onclick={(event) => {
    if (event.target === event.currentTarget) onClose();
  }}
  onkeydown={(e) => e.key === "Escape" && onClose()}
>
  <div class="w-full max-w-lg rounded-2xl bg-[var(--surface)] p-6 shadow-xl">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-lg font-semibold ui-title">Delete authorization</p>
        <p class="mt-1 text-sm ui-muted">
          {authorization.account_name || authorization.auth_strategy}
        </p>
        {#if authorization.masked_value}
          <p class="mt-1 text-xs font-mono ui-muted">
            {authorization.masked_value}
          </p>
        {/if}
        {#if authorization.status === "inactive"}
          <p class="mt-1 text-sm text-(--danger)">Reauthorization required</p>
          {#if authorization.auth_failure_reason}
            <p class="mt-1 text-xs ui-muted">
              Reason: {authorization.auth_failure_reason}
            </p>
          {/if}
        {/if}
      </div>
      <Button
        variant="icon"
        className="w-8 h-8 text-(--muted)"
        onclick={onClose}
      >
        <X aria-hidden="true" size={16} strokeWidth={2} />
      </Button>
    </div>

    <form
      method="POST"
      action={withOrganization(
        `/sources/authorizations/${authorization.id}/delete`,
      )}
      onsubmit={(event) => {
        event.preventDefault();
        onSubmit(
          event as SubmitEvent & {
            currentTarget: EventTarget & HTMLFormElement;
          },
        );
      }}
    >
      {#if check?.has_sources}
        <div class="mt-4 rounded-xl border border-(--panel-border) p-4">
          <p class="text-sm font-medium ui-title">
            {check.source_count} source(s) using this authorization
          </p>
          <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto">
            {#each check.sources as src (src.id)}
              <li class="text-xs ui-muted">• {src.name}</li>
            {/each}
          </ul>

          {#if check.other_authorizations.length > 0}
            <div class="mt-4">
              <label class="text-sm font-medium ui-title" for="reassign-select">
                Reassign sources to
              </label>
              <Select
                className="mt-2 w-full rounded-xl px-3 py-2 text-sm"
                name="new_authorization_id"
                id="reassign-select"
              >
                <option value="" disabled selected
                  >— Select authorization —</option
                >
                {#each check.other_authorizations as auth (auth.id)}
                  <option value={auth.id}>
                    {auth.auth_strategy} ({auth.masked_value})
                  </option>
                {/each}
              </Select>
            </div>
          {:else}
            <p class="mt-3 text-sm font-medium text-red-500">
              No other authorizations available for this provider. Delete all
              linked sources before removing this authorization.
            </p>
          {/if}
        </div>
      {:else if check !== null}
        <p class="mt-4 text-sm ui-muted">
          No sources are using this authorization. It will be permanently
          deleted.
        </p>
      {/if}

      <div class="mt-6 flex justify-end gap-3">
        <Button
          type="button"
          variant="secondary"
          className="rounded-full px-5"
          onclick={onClose}
        >
          {check?.has_sources && check.other_authorizations.length === 0
            ? "Close"
            : "Cancel"}
        </Button>
        {#if check !== null && (!check.has_sources || check.other_authorizations.length > 0)}
          <Button type="submit" variant="danger" className="rounded-full px-5">
            {check.has_sources ? "Delete & Reassign" : "Delete"}
          </Button>
        {/if}
      </div>
    </form>
  </div>
</div>
