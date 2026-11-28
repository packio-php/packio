<script lang="ts">
  import { Trash2 } from "@lucide/svelte";
  import { untrack } from "svelte";
  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import OAuthIcon from "@/Components/ui/OAuthIcon.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import type {
    SourceProviderPayload,
    SourceProviderInstancePayload,
    ProviderAuthorizationPayload,
    UserOAuthAccountPayload,
    ValidationErrors,
  } from "@/types/pages";
  import {
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
    formId: string;
    // Validation errors of the last failed save, and a counter bumped on every successful save.
    errors?: ValidationErrors;
    saved?: number;
    // True while the token form is shown; the shell then renders its submit button in the footer.
    tokenFormOpen?: boolean;
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
    formId,
    providerName,
    withOrganization,
    onSelectProviderInstance,
    onDeleteAuthorization,
    onAccessSubmit,
    onOAuthRedirect,
    onOAuthConnect,
    errors = {},
    saved = 0,
    tokenFormOpen = $bindable(false),
  }: Props = $props();

  let instanceUrl = $state("");
  let token = $state("");
  // Open by default only when there is nothing saved yet.
  let addOpen = $state(untrack(() => manageableAuthorizations.length === 0));
  let showSaved = $state(false);
  // The panel is re-created per provider, so an error object present at mount belongs to an earlier provider.
  const staleErrors = untrack(() => errors);
  let visibleErrors = $derived(errors === staleErrors ? {} : errors);
  let lastSaved = untrack(() => saved);
  $effect(() => {
    if (saved !== lastSaved) {
      lastSaved = saved;
      token = "";
      instanceUrl = "";
      addOpen = false;
      showSaved = true;
    }
  });

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
  $effect(() => {
    tokenFormOpen = addOpen && providerSupportsSelectedInstanceToken;
  });
</script>

<div class="grid gap-5">
  {#if providerNeedsInstance}
    <label class="grid gap-2">
      <span class="text-sm font-medium ui-title">
        {selectedProviderNoun} instance
      </span>
      <Select
        className="w-full"
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
  {/if}

  <p role="status" class="text-sm ui-title empty:hidden">
    {#if showSaved}Access saved for this organization{/if}
  </p>

  <div class="grid gap-2">
    {#if manageableAuthorizations.length > 0}
      <ul class="grid gap-2">
        {#each manageableAuthorizations as authorization (authorization.id)}
          <li
            class="flex items-center justify-between gap-3 rounded-xl border border-(--panel-border) px-3 py-2 text-sm"
          >
            <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
              <span class="font-medium ui-title">
                {authorization.account_name || authorization.auth_strategy}
              </span>
              <span
                class={`rounded-full px-2.5 py-0.5 text-[11px] font-medium ${
                  isAuthorizationReconnectRequired(authorization)
                    ? "ui-badge-danger"
                    : "ui-badge-accent"
                }`}
              >
                {authorizationHealthLabel(authorization) ??
                  authorization.status}
              </span>
              {#if authorization.masked_value}
                <span class="font-mono text-xs ui-muted">
                  {authorization.masked_value}
                </span>
              {/if}
              {#if authorization.provider_instance_id}
                <span class="text-xs ui-muted">
                  {manageableProviderInstances.find(
                    (instance) =>
                      instance.id === authorization.provider_instance_id,
                  )?.label}
                </span>
              {/if}
              {#if isAuthorizationReconnectRequired(authorization) && authorization.auth_failure_reason}
                <span class="w-full text-xs ui-muted">
                  {authorization.auth_failure_reason}
                </span>
              {/if}
            </div>
            <Button
              type="button"
              variant="icon"
              className="h-8 w-8 shrink-0 text-(--muted)"
              aria-label="Delete authorization"
              onclick={() => onDeleteAuthorization(authorization)}
            >
              <Trash2 aria-hidden="true" size={14} strokeWidth={2} />
            </Button>
          </li>
        {/each}
      </ul>
    {/if}
  </div>

  <div class="grid gap-4">
    <Button
      type="button"
      variant="secondary"
      className="justify-self-start rounded-full px-5"
      aria-expanded={addOpen}
      onclick={() => (addOpen = !addOpen)}
    >
      Add provider access
    </Button>

    {#if addOpen}
      {#if selectedProviderSupportsOAuth}
        <div class="grid gap-3">
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
              <span>Connect with {providerName(selectedProvider)}</span>
            </Button>
          {/if}
        </div>
        {#if providerSupportsSelectedInstanceToken}
          <p class="text-sm ui-muted">or enter token manually</p>
        {/if}
      {/if}

      {#if providerSupportsSelectedInstanceToken}
        <form
          id={formId}
          class="grid gap-4"
          method="POST"
          action={withOrganization("/sources/authorizations")}
          onsubmit={(event) => {
            event.preventDefault();
            showSaved = false;
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
                className="w-full"
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
              className="w-full"
              name="authorization_token"
              type="password"
              autocomplete="off"
              placeholder="Paste a provider token with repository read access"
              required
              bind:value={token}
            />
            <FieldErrors errors={visibleErrors} field="authorization_token" />
          </label>
          {#if selectedProvider === "generic_git"}
            <label class="grid gap-2">
              <span class="text-sm font-medium ui-title">Auth scheme</span>
              <Select className="w-full" name="auth_scheme">
                <option value="bearer">Bearer token</option>
                <option value="basic"> Basic auth (username:token) </option>
              </Select>
            </label>
          {/if}
        </form>
      {:else}
        <p class="text-sm ui-muted">
          This {selectedProviderNoun} instance is configured for OAuth only.
        </p>
      {/if}
    {/if}
  </div>
</div>
