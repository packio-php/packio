<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import { Endpoints } from "@/lib/api";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import OAuthIcon from "@/Components/ui/OAuthIcon.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { mergeBranding } from "@/lib/branding";
  import { formatDate } from "@/lib/date";
  import type { AuthPayload } from "@/types/auth";
  import type {
    ApiAccessTokenPayload,
    ProfileOAuthAccountPayload,
    ProfilePageProps,
    ProfilePayload,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    profile?: ProfilePayload | null;
    branding?: ProfilePageProps["branding"];
    oauth_accounts?: ProfileOAuthAccountPayload[];
    api_tokens?: ApiAccessTokenPayload[];
  }

  let {
    auth = {},
    profile = null,
    branding = {},
    oauth_accounts = [],
    api_tokens = [],
  }: Props = $props();

  let resolvedBranding = $derived(mergeBranding(branding ?? {}));
  const apiTokenExpirationPresets = [
    { value: "1-month", label: "1 month", months: 1 },
    { value: "3-months", label: "3 months", months: 3 },
    { value: "6-months", label: "6 months", months: 6 },
    { value: "1-year", label: "1 year", months: 12 },
  ] as const;

  let currentPassword = $state("");
  let newPassword = $state("");
  let newPasswordConfirmation = $state("");
  let newPasswordInput: HTMLInputElement | null = $state(null);
  let apiTokenName = $state("");
  let apiTokenExpiresAt = $state("");
  let apiTokenExpirationPreset = $state("");
  let apiTokenScopes = $state({
    read: true,
    write: false,
    admin: false,
  });
  let apiTokenPendingRevoke: ApiAccessTokenPayload | null = $state(null);
  let apiTokenPendingDelete: ApiAccessTokenPayload | null = $state(null);
  let apiTokenRevoking = $state(false);
  let apiTokenDeleting = $state(false);

  let hasPassword = $derived(!!profile?.["has_password"]);
  let canDisconnectAnyOAuth = $derived(
    hasPassword || oauth_accounts.length > 1,
  );
  let canIssueAdminApiToken = $derived(
    profile?.roles?.includes("admin") ?? false,
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

  function submitApiToken(): void {
    const scopes = Object.entries(apiTokenScopes)
      .filter(([, enabled]) => enabled)
      .map(([scope]) => scope)
      .filter((scope) => scope !== "admin" || canIssueAdminApiToken);

    router.post(
      Endpoints.profile.issueApiToken(),
      {
        name: apiTokenName,
        scopes,
        expires_at: apiTokenExpiresAtForRequest(),
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          apiTokenName = "";
          apiTokenExpiresAt = "";
          apiTokenExpirationPreset = "";
          apiTokenScopes = {
            read: true,
            write: false,
            admin: false,
          };
        },
      },
    );
  }

  function confirmApiTokenRevoke(): void {
    const token = apiTokenPendingRevoke;
    if (!token || apiTokenRevoking) {
      return;
    }

    apiTokenRevoking = true;
    router.post(
      Endpoints.profile.revokeApiToken(token.id),
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          apiTokenRevoking = false;
          apiTokenPendingRevoke = null;
        },
      },
    );
  }

  function confirmApiTokenDelete(): void {
    const token = apiTokenPendingDelete;
    if (!token || apiTokenDeleting) {
      return;
    }

    apiTokenDeleting = true;
    router.delete(Endpoints.profile.deleteApiToken(token.id), {
      preserveScroll: true,
      onFinish: () => {
        apiTokenDeleting = false;
        apiTokenPendingDelete = null;
      },
    });
  }

  function setApiTokenExpirationPreset(
    preset: (typeof apiTokenExpirationPresets)[number] | null,
  ): void {
    apiTokenExpirationPreset = preset?.value ?? "";

    if (!preset) {
      return;
    }

    const now = new Date();
    const expiresAt = new Date(
      now.getFullYear(),
      now.getMonth() + preset.months,
      now.getDate(),
      now.getHours(),
      now.getMinutes(),
    );
    apiTokenExpiresAt = formatDatetimeLocal(expiresAt);
  }

  function apiTokenExpiresAtForRequest(): string {
    return formatIsoMinuteValue(apiTokenExpiresAt);
  }

  function formatDatetimeLocal(date: Date): string {
    const pad = (value: number): string => value.toString().padStart(2, "0");

    return [
      `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`,
      `${pad(date.getHours())}:${pad(date.getMinutes())}`,
    ].join("T");
  }

  function formatIsoMinuteValue(value: string): string {
    return value.trim().replace("T", " ");
  }

  function scopeLabel(scope: string): string {
    if (scope === "read") {
      return "Read";
    }

    if (scope === "write") {
      return "Write";
    }

    if (scope === "admin") {
      return "Admin";
    }

    return scope;
  }
</script>

<svelte:head>
  <title>Profile</title>
</svelte:head>

<AppShell title="Profile" {auth}>
  <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
    <Card className="rounded-[1.75rem] p-6">
      <SectionHeading
        eyebrow={`${resolvedBranding.name} account`}
        title={profile?.display_name ?? "Profile"}
        description={profile?.email ?? ""}
      />
      <div class="mt-4 flex flex-wrap gap-2">
        {#each profile?.roles ?? [] as role (role)}
          <Badge tone={role === "admin" ? "danger" : "accent"}>
            {role}
          </Badge>
        {/each}
      </div>
      <div class="mt-6 grid gap-3 text-sm ui-muted">
        <p>
          <span class="font-medium ui-title">Created at:</span>
          {formatDate(profile?.created_at)}
        </p>
        <p>
          <span class="font-medium ui-title">Updated at:</span>
          {formatDate(profile?.updated_at)}
        </p>
      </div>
    </Card>

    <div id="password-settings">
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
                <span class="text-sm font-medium ui-title"
                  >Current password</span
                >
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
              <span class="text-sm font-medium ui-title"
                >Confirm new password</span
              >
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

    <Card className="rounded-[1.75rem] p-6 xl:col-span-2">
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
            <span
              class="grid h-10 w-10 place-items-center rounded-full border border-(--panel-border) bg-(--surface-muted) text-sm font-semibold ui-title"
            >
              •••
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

    <div class="grid gap-6 xl:col-span-2 xl:grid-cols-[1.1fr_0.9fr]">
      <Card className="rounded-[1.75rem] p-6">
        <SectionHeading
          eyebrow="API"
          title="API tokens"
          description="Create personal tokens for REST API automation."
        />
        <a
          class="mt-4 inline-flex w-fit items-center rounded-full border px-4 py-2 text-sm font-medium transition hover:border-[var(--accent)] hover:text-[var(--accent)] theme-border ui-title"
          href="/api/doc"
          rel="external noopener noreferrer"
          target="_blank"
        >
          Open API documentation
        </a>

        <div class="mt-6 grid gap-3">
          {#if api_tokens.length === 0}
            <div class="rounded-xl border border-dashed px-4 py-6">
              <p class="text-sm ui-muted">No API tokens issued yet.</p>
            </div>
          {:else}
            {#each api_tokens as token (token.id)}
              <InsetCard as="article" className="p-4">
                <div
                  class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                >
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="font-semibold ui-title">{token.name}</p>
                      <Badge tone={token.revoked ? "danger" : "accent"}>
                        {token.revoked ? "Revoked" : "Active"}
                      </Badge>
                    </div>

                    <div
                      class="mt-3 flex min-w-0 items-center rounded-xl border px-3 py-2 theme-border"
                      style="background-color: var(--surface-muted);"
                    >
                      <code class="min-w-0 truncate font-mono text-xs ui-muted">
                        {token.token_prefix}...
                      </code>
                    </div>
                  </div>

                  {#if token.revoked}
                    <Button
                      className="w-fit"
                      variant="chipDanger"
                      type="button"
                      onclick={() => (apiTokenPendingDelete = token)}
                    >
                      Delete
                    </Button>
                  {:else}
                    <Button
                      className="w-fit"
                      variant="chipDanger"
                      type="button"
                      onclick={() => (apiTokenPendingRevoke = token)}
                    >
                      Revoke
                    </Button>
                  {/if}
                </div>

                <div
                  class="mt-4 flex flex-wrap items-start justify-between gap-3"
                >
                  <div class="grid gap-1 text-xs ui-muted">
                    <p>Created {formatDate(token.created_at)}</p>
                    <p>
                      {token.last_used_at
                        ? `Last used ${formatDate(token.last_used_at)}`
                        : "Never used"}
                    </p>
                    {#if token.expires_at}
                      <p>Expires {formatDate(token.expires_at)}</p>
                    {/if}
                    {#if token.revoked_at}
                      <p>Revoked {formatDate(token.revoked_at)}</p>
                    {/if}
                  </div>

                  <div class="flex flex-wrap gap-1.5">
                    {#each token.scopes as scope (scope)}
                      <span
                        class="ui-code rounded-full px-2.5 py-1 text-xs font-medium"
                      >
                        {scopeLabel(scope)}
                      </span>
                    {/each}
                  </div>
                </div>
              </InsetCard>
            {/each}
          {/if}
        </div>
      </Card>

      <Card className="rounded-[1.75rem] p-6">
        <SectionHeading
          eyebrow="Issue token"
          title="Create API token"
          description="Create a new personal access token for REST API automation."
        />

        <form
          class="mt-6 grid gap-4"
          onsubmit={(event) => {
            event.preventDefault();
            submitApiToken();
          }}
        >
          <div class="rounded-2xl border px-4 py-3 theme-border">
            <p class="text-sm font-medium ui-title">Store the token now</p>
            <p class="mt-1 text-xs leading-5 ui-muted">
              For security, the full API token is displayed only once when it is
              created.
            </p>
          </div>

          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">Token name</span>
            <Input
              bind:value={apiTokenName}
              name="name"
              placeholder="CI automation"
              required
            />
          </label>

          <div class="grid gap-2">
            <label
              class="text-sm font-medium ui-title"
              for="api-token-expires-at"
            >
              Expiration date
            </label>
            <Input
              id="api-token-expires-at"
              bind:value={apiTokenExpiresAt}
              name="expires_at"
              type="datetime-local"
              oninput={() => (apiTokenExpirationPreset = "")}
            />
            <span class="text-xs ui-muted">
              Leave blank to create a token that never expires.
            </span>
            <span class="flex flex-wrap gap-2">
              {#each apiTokenExpirationPresets as preset (preset.value)}
                <label
                  class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium theme-border ui-muted"
                >
                  <input
                    checked={apiTokenExpirationPreset === preset.value}
                    type="checkbox"
                    onchange={(event) =>
                      setApiTokenExpirationPreset(
                        event.currentTarget.checked ? preset : null,
                      )}
                  />
                  {preset.label}
                </label>
              {/each}
            </span>
          </div>

          <fieldset class="grid gap-3">
            <legend class="text-sm font-medium ui-title">Scopes</legend>
            <label
              class="flex items-start gap-3 rounded-xl border px-4 py-3 theme-border"
            >
              <input
                bind:checked={apiTokenScopes.read}
                class="mt-1"
                name="scopes"
                type="checkbox"
                value="read"
              />
              <span class="grid gap-1">
                <span class="text-sm font-medium ui-title">Read</span>
                <span class="text-xs ui-muted">List and inspect API data.</span>
              </span>
            </label>

            <label
              class="flex items-start gap-3 rounded-xl border px-4 py-3 theme-border"
            >
              <input
                bind:checked={apiTokenScopes.write}
                class="mt-1"
                name="scopes"
                type="checkbox"
                value="write"
              />
              <span class="grid gap-1">
                <span class="text-sm font-medium ui-title">Write</span>
                <span class="text-xs ui-muted">
                  Create, update, sync, and revoke API resources.
                </span>
              </span>
            </label>

            {#if canIssueAdminApiToken}
              <label
                class="flex items-start gap-3 rounded-xl border px-4 py-3 theme-border"
              >
                <input
                  bind:checked={apiTokenScopes.admin}
                  class="mt-1"
                  name="scopes"
                  type="checkbox"
                  value="admin"
                />
                <span class="grid gap-1">
                  <span class="text-sm font-medium ui-title">Admin</span>
                  <span class="text-xs ui-muted">
                    Access administrator API endpoints.
                  </span>
                </span>
              </label>
            {/if}
          </fieldset>

          <Button
            className="w-fit rounded-full px-5"
            type="submit"
            variant="primary"
          >
            Issue API token
          </Button>
        </form>
      </Card>
    </div>
  </section>

  <ConfirmationModal
    open={apiTokenPendingRevoke !== null}
    eyebrow="API token"
    title="Revoke token?"
    description="This immediately removes REST API access for this token."
    confirmLabel="Revoke token"
    cancelLabel="Keep token"
    confirming={apiTokenRevoking}
    onCancel={() => (apiTokenPendingRevoke = null)}
    onConfirm={confirmApiTokenRevoke}
  >
    {#if apiTokenPendingRevoke}
      <div class="ui-inset grid gap-2 rounded-xl border p-4">
        <p class="font-medium ui-title">{apiTokenPendingRevoke.name}</p>
        <p class="break-all font-mono text-xs ui-muted">
          {apiTokenPendingRevoke.token_prefix}...
        </p>
      </div>
    {/if}
  </ConfirmationModal>

  <ConfirmationModal
    open={apiTokenPendingDelete !== null}
    eyebrow="API token"
    title="Delete revoked token?"
    description="This permanently removes the revoked API token record from your profile."
    confirmLabel="Delete token"
    cancelLabel="Keep record"
    confirming={apiTokenDeleting}
    onCancel={() => (apiTokenPendingDelete = null)}
    onConfirm={confirmApiTokenDelete}
  >
    {#if apiTokenPendingDelete}
      <div class="ui-inset grid gap-2 rounded-xl border p-4">
        <p class="font-medium ui-title">{apiTokenPendingDelete.name}</p>
        <p class="break-all font-mono text-xs ui-muted">
          {apiTokenPendingDelete.token_prefix}...
        </p>
      </div>
    {/if}
  </ConfirmationModal>
</AppShell>
