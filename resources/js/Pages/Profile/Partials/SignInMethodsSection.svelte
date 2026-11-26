<script lang="ts">
  import { Lock } from "@lucide/svelte";
  import { router, usePage } from "@inertiajs/svelte";
  import { tick } from "svelte";
  import { Endpoints } from "@/lib/api";
  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import OAuthIcon from "@/Components/ui/OAuthIcon.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { hasFieldErrors } from "@/lib/validationErrors";
  import type { AppPageProps } from "@/types/app";
  import type {
    ProfileOAuthAccountPayload,
    ProfilePayload,
    ValidationErrors,
  } from "@/types/pages";

  interface Props {
    profile?: ProfilePayload | null;
    oauth_accounts?: ProfileOAuthAccountPayload[];
  }

  let { profile = null, oauth_accounts = [] }: Props = $props();

  const page = usePage<AppPageProps>();
  let errors = $derived(
    (page.props?.errors ?? {}) as unknown as ValidationErrors,
  );

  let hasPassword = $derived(!!profile?.["has_password"]);
  let canDisconnectAnyOAuth = $derived(
    hasPassword || oauth_accounts.length > 1,
  );

  // Open from the start when the page already carries password errors (state not preserved).
  let formOpen = $state(
    ["current_password", "new_password", "new_password_confirmation"].some(
      (field) => hasFieldErrors(errors, field),
    ),
  );
  let currentPassword = $state("");
  let newPassword = $state("");
  let newPasswordConfirmation = $state("");
  let submitting = $state(false);
  let form = $state<HTMLFormElement | null>(null);

  async function openForm(): Promise<void> {
    formOpen = true;
    await tick();
    form?.querySelector("input")?.focus();
  }

  function resetForm(): void {
    formOpen = false;
    currentPassword = "";
    newPassword = "";
    newPasswordConfirmation = "";
  }

  async function closeForm(): Promise<void> {
    resetForm();
    await tick();
    document.getElementById("password-action")?.focus();
  }

  function submitPassword(): void {
    if (submitting) return;

    const payload: Record<string, string> = {
      new_password: newPassword,
      new_password_confirmation: newPasswordConfirmation,
    };

    if (hasPassword) {
      payload.current_password = currentPassword;
    }

    router.post("/profile/password", payload, {
      preserveScroll: true,
      preserveState: "errors",
      onStart: () => (submitting = true),
      onFinish: () => (submitting = false),
      onSuccess: closeForm,
    });
  }

  function disconnectOAuthAccount(accountId: string): void {
    router.post(
      Endpoints.profile.disconnectOAuth(accountId),
      {},
      {
        preserveScroll: true,
      },
    );
  }
</script>

<div
  id="sign-in-methods"
  class="scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)]"
>
  <Card className="rounded-[1.75rem] p-6">
    <SectionHeading
      eyebrow="Account"
      title="Sign-in methods"
      description="Manage how you sign in to your account. Keep at least one sign-in method enabled."
    />

    <div class="mt-6 grid gap-3">
      <div class="rounded-xl border px-4 py-4">
        <div
          class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
        >
          <div class="flex items-center gap-3">
            <span class="text-(--accent)">
              <Lock class="size-6" aria-hidden="true" />
            </span>
            <div class="grid gap-0.5">
              <span class="text-sm font-medium ui-title">Password</span>
              <span class="text-xs ui-muted">
                {#if hasPassword}
                  You can sign in with your email and password.
                {:else}
                  You have not set a password yet.
                {/if}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <Badge tone={hasPassword ? "accent" : "muted"}>
              {hasPassword ? "Set" : "Not set"}
            </Badge>
            <Button
              id="password-action"
              className="rounded-full px-4 py-2 text-sm"
              type="button"
              variant="soft"
              onclick={openForm}
            >
              {hasPassword ? "Change password" : "Set password"}
            </Button>
          </div>
        </div>

        {#if formOpen}
          <form
            bind:this={form}
            class="mt-4 grid gap-4"
            onsubmit={(event) => {
              event.preventDefault();
              submitPassword();
            }}
          >
            {#if hasPassword}
              <div class="grid gap-2">
                <label
                  class="text-sm font-medium ui-title"
                  for="password-current"
                >
                  Current password
                </label>
                <Input
                  bind:value={currentPassword}
                  id="password-current"
                  name="current_password"
                  type="password"
                  autocomplete="current-password"
                  invalid={hasFieldErrors(errors, "current_password")}
                />
                <FieldErrors {errors} field="current_password" />
              </div>
            {/if}

            <div class="grid gap-2">
              <label class="text-sm font-medium ui-title" for="password-new">
                New password
              </label>
              <Input
                bind:value={newPassword}
                id="password-new"
                name="new_password"
                type="password"
                autocomplete="new-password"
                invalid={hasFieldErrors(errors, "new_password")}
              />
              <span class="text-xs ui-muted">
                Min 8 characters, at least one digit and one special character
              </span>
              <FieldErrors {errors} field="new_password" />
            </div>

            <div class="grid gap-2">
              <label
                class="text-sm font-medium ui-title"
                for="password-confirm"
              >
                Confirm new password
              </label>
              <Input
                bind:value={newPasswordConfirmation}
                id="password-confirm"
                name="new_password_confirmation"
                type="password"
                autocomplete="new-password"
                invalid={hasFieldErrors(errors, "new_password_confirmation")}
              />
              <FieldErrors {errors} field="new_password_confirmation" />
            </div>

            <div class="flex flex-wrap gap-3">
              <Button
                className="w-fit rounded-full px-5"
                type="submit"
                variant="primary"
                disabled={submitting}
              >
                {hasPassword ? "Save password" : "Set password"}
              </Button>
              <Button type="button" variant="secondary" onclick={closeForm}>
                Cancel
              </Button>
            </div>
          </form>
        {/if}
      </div>

      {#if oauth_accounts.length > 0}
        {#each oauth_accounts as account (account.id)}
          <div
            class="flex flex-col gap-4 rounded-xl border px-4 py-4 md:flex-row md:items-center md:justify-between"
          >
            <div class="flex items-center gap-3">
              <span class="text-(--accent)">
                <OAuthIcon provider={account.provider} className="size-6" />
              </span>
              <div class="grid gap-0.5">
                <span class="text-sm font-medium ui-title">
                  {account.provider_label}
                </span>
                <span class="text-xs ui-muted">
                  {account.account_label}
                </span>
              </div>
            </div>

            <div class="flex flex-col items-start gap-2 md:items-end">
              <Badge tone="accent">Connected</Badge>

              <Button
                className="rounded-full px-4 py-2 text-sm"
                type="button"
                variant="soft"
                disabled={!canDisconnectAnyOAuth}
                onclick={() => disconnectOAuthAccount(account.id)}
              >
                Disconnect
              </Button>

              {#if !canDisconnectAnyOAuth}
                <p class="text-xs ui-muted">
                  Set a password or connect another provider before
                  disconnecting your only sign-in method.
                </p>
              {/if}
            </div>
          </div>
        {/each}
      {:else}
        <div class="rounded-xl border border-dashed px-4 py-6 text-center">
          <p class="text-sm ui-muted">No OAuth sign-in methods connected.</p>
        </div>
      {/if}
    </div>
  </Card>
</div>
