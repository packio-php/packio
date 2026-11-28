<script lang="ts">
  import { Trash2 } from "@lucide/svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import OAuthIcon from "@/Components/ui/OAuthIcon.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import type {
    SourceProviderPayload,
    SourceProviderInstancePayload,
    ProviderAuthorizationPayload,
    UserOAuthAccountPayload,
  } from "@/types/pages";
  import {
    authorizationCardClass,
    authorizationHealthLabel,
    isAuthorizationReconnectRequired,
  } from "./authorizationStatus";

  interface Props {
    selectedProvider: string;
    selectedProviderInstanceId: string;
    selectedProviderMeta: SourceProviderPayload | null;
    selectedProviderNoun: string;
    selectedProviderIsSelfHosted: boolean;
    providerNeedsInstance: boolean;
    manageableProviderInstances: SourceProviderInstancePayload[];
    manageableAuthorizations: ProviderAuthorizationPayload[];
    user_oauth_accounts: UserOAuthAccountPayload[];
    returnTo: string;
    // Set when access mode was opened from the connect wizard (auto-return, used later).
    accessOrigin?: "connect" | null;
    providerName: (providerKey: string) => string;
    withOrganization: (path: string) => string;
    onSelectProviderInstance: (providerInstanceId: string) => void;
    onDeleteAuthorization: (auth: ProviderAuthorizationPayload) => void;
    onAccessSubmit: (
      event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
    ) => void;
    // OAuth with a known login account / without one; the popup lives in the shell.
    onOAuthRedirect: (provider: string) => void;
    onOAuthConnect: (provider: string) => void;
    onOpenConnect: () => void;
    onClose: () => void;
  }

  let {
    selectedProvider,
    selectedProviderInstanceId,
    selectedProviderMeta,
    selectedProviderNoun,
    selectedProviderIsSelfHosted,
    providerNeedsInstance,
    manageableProviderInstances,
    manageableAuthorizations,
    user_oauth_accounts,
    returnTo,
    providerName,
    withOrganization,
    onSelectProviderInstance,
    onDeleteAuthorization,
    onAccessSubmit,
    onOAuthRedirect,
    onOAuthConnect,
    onOpenConnect,
    onClose,
  }: Props = $props();

  let instanceUrl = $state("");

  // Group OAuth accounts by provider
  let oauthAccountsByProvider = $derived(
    user_oauth_accounts.reduce(
      (acc, account) => {
        if (!acc[account.provider]) acc[account.provider] = [];
        acc[account.provider].push(account);
        return acc;
      },
      {} as Record<string, UserOAuthAccountPayload[]>,
    ),
  );
  let selectedOAuthProvider = $derived(
    selectedProviderMeta?.oauth_provider ?? null,
  );
  let selectedProviderInstance = $derived(
    manageableProviderInstances.find(
      (instance) => instance.id === selectedProviderInstanceId,
    ) ?? null,
  );
  let providerSupportsSelectedInstanceOAuth = $derived(
    !providerNeedsInstance ||
      selectedProviderInstance?.auth_mode === "oauth2" ||
      selectedProviderInstance?.auth_mode === "oauth2_and_token",
  );
  let selectedProviderSupportsOAuth = $derived(
    selectedProviderMeta?.oauth_available === true &&
      null !== selectedOAuthProvider &&
      providerSupportsSelectedInstanceOAuth,
  );
  let selectedOAuthAccounts = $derived(
    null !== selectedOAuthProvider
      ? (oauthAccountsByProvider[selectedOAuthProvider] ?? [])
      : [],
  );
  let selectedOAuthIconProvider = $derived(selectedOAuthProvider ?? "github");
  // Check if we have OAuth for selected provider
  let hasOAuthForSelected = $derived(selectedOAuthAccounts.length > 0);
  let providerSupportsSelectedInstanceToken = $derived(
    !providerNeedsInstance ||
      selectedProviderInstance?.auth_mode === "token" ||
      selectedProviderInstance?.auth_mode === "oauth2_and_token",
  );
</script>

<div class="grid gap-5">
  {#if providerNeedsInstance}
    <InsetCard className="p-5">
      <label class="grid gap-2">
        <span class="text-sm font-medium ui-title">
          {selectedProviderNoun} instance
        </span>
        <Select
          className="rounded-2xl px-4 py-3 text-sm w-full h-14"
          value={selectedProviderInstanceId}
          onchange={(event) =>
            onSelectProviderInstance(event.currentTarget.value)}
        >
          {#each manageableProviderInstances as instance (instance.id)}
            <option value={instance.id}>
              {instance.label} ({instance.base_url})
            </option>
          {/each}
        </Select>
      </label>
    </InsetCard>
  {/if}

  <InsetCard className="p-5">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="font-medium ui-title">
          Saved {providerName(selectedProvider)} access
        </p>
        <p class="mt-1 text-sm ui-muted">
          {manageableAuthorizations.length > 0
            ? `${manageableAuthorizations.length} authorization${manageableAuthorizations.length === 1 ? "" : "s"} managed for this provider.`
            : `No saved ${providerName(selectedProvider)} authorization yet.`}
        </p>
      </div>
    </div>
    <div class="mt-4 grid gap-3">
      {#if manageableAuthorizations.length === 0}
        <p class="text-sm ui-muted">
          Authorize this provider once, then source connect flows can reuse it
          automatically.
        </p>
      {:else}
        {#each manageableAuthorizations as authorization (authorization.id)}
          <div
            class={`rounded-xl border p-3 text-sm ${authorizationCardClass(
              authorization,
              false,
            )}`}
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium ui-title">
                    {authorization.account_name || authorization.auth_strategy}
                  </p>
                  {#if authorizationHealthLabel(authorization)}
                    <span
                      class={`rounded-full px-2.5 py-1 text-[11px] font-medium ${
                        isAuthorizationReconnectRequired(authorization)
                          ? "ui-badge-danger"
                          : "ui-badge-accent"
                      }`}
                    >
                      {authorizationHealthLabel(authorization)}
                    </span>
                  {/if}
                </div>
                {#if authorization.masked_value}
                  <p class="mt-1 text-xs font-mono ui-muted">
                    {authorization.masked_value}
                  </p>
                {/if}
                <p class="mt-1 text-xs ui-muted">
                  {authorization.status}
                </p>
                {#if isAuthorizationReconnectRequired(authorization)}
                  <p class="mt-1 text-xs text-(--danger)">
                    Reauthorization required
                  </p>
                  {#if authorization.auth_failure_reason}
                    <p class="mt-0.5 text-xs ui-muted">
                      {authorization.auth_failure_reason}
                    </p>
                  {/if}
                {/if}
              </div>
              <Button
                type="button"
                variant="icon"
                className="w-8 h-8 text-(--muted)"
                aria-label="Delete authorization"
                onclick={() => onDeleteAuthorization(authorization)}
              >
                <Trash2 aria-hidden="true" size={14} strokeWidth={2} />
              </Button>
            </div>
          </div>
        {/each}
      {/if}
    </div>
  </InsetCard>

  <!-- OAuth Section -->
  {#if selectedProviderSupportsOAuth}
    <InsetCard className="p-5">
      <p class="font-medium ui-title">Authorize via OAuth</p>
      <p class="mt-1 text-sm ui-muted">
        Connect source access for {providerName(selectedProvider)}. Login
        accounts are shown only as a reference.
      </p>
      <div class="mt-4 grid gap-3">
        {#if hasOAuthForSelected}
          {#each selectedOAuthAccounts as account (account.id)}
            <Button
              type="button"
              variant="secondary"
              className="rounded-3xl px-5 flex items-center gap-3 justify-start h-auto py-3"
              onclick={() => onOAuthRedirect(account.provider)}
            >
              <OAuthIcon provider={selectedOAuthIconProvider} />
              <div class="flex flex-col items-start leading-tight">
                <span>Connect source access</span>
                <span class="text-xs text-muted">
                  Signed in as {account.provider_username ??
                    account.provider_email ??
                    providerName(account.provider)}
                </span>
              </div>
            </Button>
          {/each}
        {:else}
          <Button
            type="button"
            variant="secondary"
            className="rounded-full px-5 inline-flex items-center gap-2 justify-start"
            onclick={() =>
              selectedOAuthProvider && onOAuthConnect(selectedOAuthProvider)}
          >
            <OAuthIcon provider={selectedOAuthIconProvider} />
            <span>
              Connect with {providerName(selectedProvider)}
            </span>
          </Button>
        {/if}
      </div>
    </InsetCard>

    {#if providerSupportsSelectedInstanceToken}
      <div class="relative py-2">
        <div class="absolute inset-0 flex items-center">
          <div class="w-full border-t border-(--panel-border)"></div>
        </div>
        <div class="relative flex justify-center">
          <span class="bg-(--surface) px-3 text-sm ui-muted">
            or enter token manually
          </span>
        </div>
      </div>
    {/if}
  {/if}

  {#if providerSupportsSelectedInstanceToken}
    <form
      class="grid gap-4"
      method="POST"
      action={withOrganization("/sources/authorizations")}
      onsubmit={(event) => {
        event.preventDefault();
        onAccessSubmit(
          event as SubmitEvent & {
            currentTarget: EventTarget & HTMLFormElement;
          },
        );
      }}
    >
      <input type="hidden" name="provider" value={selectedProvider} />
      {#if returnTo}
        <input type="hidden" name="return_to" value={returnTo} />
      {/if}
      {#if providerNeedsInstance}
        <input
          type="hidden"
          name="provider_instance_id"
          value={selectedProviderInstanceId}
        />
      {/if}
      {#if selectedProviderIsSelfHosted && !providerNeedsInstance}
        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title">
            {selectedProviderNoun} instance URL
          </span>
          <Input
            className="w-full h-14"
            name="instance_url"
            type="url"
            autocomplete="off"
            placeholder={selectedProvider === "gitlab_self_hosted"
              ? "https://gitlab.mycompany.com"
              : "https://git.mycompany.com"}
            bind:value={instanceUrl}
          />
        </label>
      {/if}
      <label class="grid gap-2">
        <span class="text-sm font-medium ui-title">
          {providerName(selectedProvider)} authorization token
        </span>
        <Input
          className="w-full h-14"
          name="authorization_token"
          type="password"
          autocomplete="off"
          placeholder="Paste a provider token with repository read access"
          required
        />
      </label>
      {#if selectedProvider === "generic_git"}
        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title"> Auth scheme </span>
          <Select
            className="rounded-2xl px-4 py-3 text-sm w-full h-14"
            name="auth_scheme"
          >
            <option value="bearer">Bearer token</option>
            <option value="basic"> Basic auth (username:token) </option>
          </Select>
        </label>
      {/if}
      <div class="flex flex-wrap gap-3">
        <Button className="rounded-full px-5" type="submit" variant="primary">
          Save provider access
        </Button>
        {#if !returnTo}
          <Button
            className="rounded-full px-5"
            type="button"
            variant="secondary"
            onclick={onOpenConnect}
          >
            Continue to add source
          </Button>
        {/if}
      </div>
    </form>
  {:else}
    <InsetCard className="p-5">
      <p class="font-medium ui-title">Token authorization</p>
      <p class="mt-1 text-sm ui-muted">
        This {selectedProviderNoun} instance is configured for OAuth only.
      </p>
    </InsetCard>
  {/if}
  {#if returnTo}
    <p class="text-sm ui-muted">
      Access is saved for the whole organization and can be used by other
      sources.
    </p>
    <div>
      <Button
        className="rounded-full px-5"
        type="button"
        variant="secondary"
        onclick={onClose}
      >
        Done
      </Button>
    </div>
  {/if}
</div>
