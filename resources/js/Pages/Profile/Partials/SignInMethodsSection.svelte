<script lang="ts">
  import { Lock } from "@lucide/svelte";
  import { router } from "@inertiajs/svelte";
  import { Endpoints } from "@/lib/api";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import OAuthIcon from "@/Components/ui/OAuthIcon.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { mergeBranding } from "@/lib/branding";
  import type {
    ProfilePageProps,
    ProfileOAuthAccountPayload,
    ProfilePayload,
  } from "@/types/pages";

  interface Props {
    profile?: ProfilePayload | null;
    branding?: ProfilePageProps["branding"];
    oauth_accounts?: ProfileOAuthAccountPayload[];
  }

  let { profile = null, branding = {}, oauth_accounts = [] }: Props = $props();

  let resolvedBranding = $derived(mergeBranding(branding ?? {}));
  let currentPassword = $state("");
  let newPassword = $state("");
  let newPasswordConfirmation = $state("");
  let newPasswordInput: HTMLInputElement | null = $state(null);

  let hasPassword = $derived(!!profile?.["has_password"]);
  let canDisconnectAnyOAuth = $derived(
    hasPassword || oauth_accounts.length > 1,
  );

  function submitPassword(): void {
    const payload: Record<string, string> = {
      new_password: newPassword,
      new_password_confirmation: newPasswordConfirmation,
    };

    if (hasPassword) {
      payload.current_password = currentPassword;
    }

    router.post("/profile/password", payload, {
      preserveScroll: true,
    });
  }

  function focusNewPasswordInput(event: MouseEvent): void {
    event.preventDefault();

    const target = document.getElementById("password-settings");
    target?.scrollIntoView({ behavior: "smooth", block: "start" });

    requestAnimationFrame(() => {
      newPasswordInput?.focus();
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
  class="scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)] grid gap-6"
>
  <div
    id="password-settings"
    class="scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)]"
  >
    <Card className="rounded-[1.75rem] p-6">
      <SectionHeading
        eyebrow="Security"
        title={hasPassword ? "Change password" : "Set password"}
        description={hasPassword
          ? `Use your current password to set a new one for your ${resolvedBranding.name} account.`
          : `You currently sign in with OAuth only. Set a password to enable email and password login for your ${resolvedBranding.name} account.`}
      />

      <form
        class="mt-6 grid gap-4"
        onsubmit={(event) => {
          event.preventDefault();
          submitPassword();
        }}
      >
        <InsetCard className="grid gap-4 p-4">
          {#if hasPassword}
            <label class="grid gap-2">
              <span class="text-sm font-medium ui-title">
                Current password
              </span>
              <Input
                bind:value={currentPassword}
                name="current_password"
                type="password"
                required={hasPassword}
              />
            </label>
          {/if}

          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">New password</span>
            <Input
              bind:element={newPasswordInput}
              bind:value={newPassword}
              name="new_password"
              type="password"
              required
            />
            <span class="text-xs ui-muted">
              Min 8 characters, at least one digit and one special character
            </span>
          </label>

          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">
              Confirm new password
            </span>
            <Input
              bind:value={newPasswordConfirmation}
              name="new_password_confirmation"
              type="password"
              required
            />
          </label>
        </InsetCard>

        <Button
          className="w-fit rounded-full px-5"
          type="submit"
          variant="primary"
        >
          {hasPassword ? "Update password" : "Set password"}
        </Button>
      </form>
    </Card>
  </div>

  <Card className="rounded-[1.75rem] p-6">
    <SectionHeading
      eyebrow="Account"
      title="Sign-in methods"
      description="Manage how you sign in to your account. Keep at least one sign-in method enabled."
    />

    <div class="mt-6 grid gap-3">
      <div
        class="flex flex-col gap-4 rounded-xl border px-4 py-4 md:flex-row md:items-center md:justify-between"
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
            {hasPassword ? "Enabled" : "Not set"}
          </Badge>
          {#if !hasPassword}
            <a
              class="inline-flex h-10 items-center justify-center rounded-full border border-(--panel-border) px-4 text-sm font-medium transition hover:bg-(--surface-muted)"
              href="#password-settings"
              onclick={focusNewPasswordInput}
            >
              Set password
            </a>
          {/if}
        </div>
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
