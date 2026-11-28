<script lang="ts">
  import Button from "@/Components/ui/Button.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import type {
    SourceProviderPayload,
    SourceProviderInstancePayload,
    ProviderAuthorizationPayload,
    ProviderResourcePayload,
  } from "@/types/pages";
  import type { OrganizationSummary } from "@/types/organization";
  import RepositoryPicker from "./RepositoryPicker.svelte";
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
    providerNeedsInstance: boolean;
    selectableProviderInstances: SourceProviderInstancePayload[];
    filteredAuthorizations: ProviderAuthorizationPayload[];
    providerResourcesAll: Record<string, ProviderResourcePayload[]>;
    selectedAuthorizationId: string;
    selectedResources: string[];
    selectedRepositoryUrl: string;
    connectVisibility: "private" | "public";
    keepLastReleases: string;
    showAdvancedConnect: boolean;
    hasClickedLoad: boolean;
    existingSourceUrls: Set<string>;
    organization: OrganizationSummary | null;
    isSubmitting: boolean;
    providerName: (providerKey: string) => string;
    withOrganization: (path: string) => string;
    onSelectProviderInstance: (providerInstanceId: string) => void;
    onAuthorizationFailed: (authorizationId: string) => string;
    onOpenAccess: () => void;
    onSubmit: (
      event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
    ) => void;
    onClose: () => void;
  }

  let {
    selectedProvider,
    selectedProviderInstanceId,
    selectedProviderMeta,
    selectedProviderNoun,
    providerNeedsInstance,
    selectableProviderInstances,
    filteredAuthorizations,
    providerResourcesAll = $bindable(),
    selectedAuthorizationId = $bindable(),
    selectedResources = $bindable(),
    selectedRepositoryUrl = $bindable(),
    connectVisibility = $bindable(),
    keepLastReleases = $bindable(),
    showAdvancedConnect = $bindable(),
    hasClickedLoad = $bindable(),
    existingSourceUrls,
    organization,
    isSubmitting,
    providerName,
    withOrganization,
    onSelectProviderInstance,
    onAuthorizationFailed,
    onOpenAccess,
    onSubmit,
    onClose,
  }: Props = $props();

  function selectAuthorization(authorizationId: string): void {
    if (selectedAuthorizationId === authorizationId) {
      return;
    }

    selectedAuthorizationId = authorizationId;
    selectedResources = [];
    selectedRepositoryUrl = "";
    hasClickedLoad = false;
  }

  let isGenericGitProvider = $derived(selectedProvider === "generic_git");
  let needsAuthorization = $derived(filteredAuthorizations.length === 0);
  let selectedAuthorization = $derived(
    filteredAuthorizations.find((a) => a.id === selectedAuthorizationId) ??
      null,
  );
  let selectedAuthorizationRequiresReconnect = $derived(
    isAuthorizationReconnectRequired(selectedAuthorization),
  );
  let providerResources = $derived(
    selectedAuthorization
      ? (providerResourcesAll[selectedAuthorization.id] ?? [])
      : [],
  );
  $effect(() => {
    if (providerResources.length === 1 && hasClickedLoad) {
      const resource = providerResources[0];
      if (!existingSourceUrls.has(resource.canonical_url)) {
        selectedResources = [resource.canonical_url];
      }
    }
  });
  let requiresAuthorizationSelection = $derived(
    filteredAuthorizations.length > 1 && "" === selectedAuthorizationId,
  );
  let publishesPrivateRepository = $derived(
    connectVisibility === "public" &&
      providerResources.some(
        (resource) =>
          resource.private &&
          selectedResources.includes(resource.canonical_url),
      ),
  );
  let hasManualRepositoryUrl = $derived(selectedRepositoryUrl.trim() !== "");
  let connectSourceCount = $derived(
    isGenericGitProvider
      ? hasManualRepositoryUrl
        ? 1
        : 0
      : selectedResources.length,
  );
  let canConnectSource = $derived(
    selectedProviderMeta?.capabilities?.supports_synchronization !== false &&
      (isGenericGitProvider
        ? hasManualRepositoryUrl && !selectedAuthorizationRequiresReconnect
        : (!needsAuthorization || showAdvancedConnect) &&
          (!requiresAuthorizationSelection || showAdvancedConnect) &&
          selectedResources.length > 0),
  );
</script>

<form
  class="grid gap-5"
  method="POST"
  action={withOrganization("/sources/connect")}
  onsubmit={(event) => {
    event.preventDefault();
    onSubmit(
      event as SubmitEvent & {
        currentTarget: EventTarget & HTMLFormElement;
      },
    );
  }}
>
  <input type="hidden" name="provider" value={selectedProvider} />
  {#if providerNeedsInstance}
    <input
      type="hidden"
      name="provider_instance_id"
      value={selectedProviderInstanceId}
    />
  {/if}
  {#if selectedAuthorizationId}
    <input
      type="hidden"
      name="authorization_id"
      value={selectedAuthorizationId}
    />
  {/if}
  {#each selectedResources as resourceUrl (resourceUrl)}
    {@const resource = providerResources.find(
      (r) => r.canonical_url === resourceUrl,
    )}
    <input type="hidden" name="resource_urls[]" value={resourceUrl} />
    {#if resource?.default_ref}
      <input
        type="hidden"
        name={`resource_default_refs[${resourceUrl}]`}
        value={resource.default_ref}
      />
    {/if}
  {/each}

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
          {#each selectableProviderInstances as instance (instance.id)}
            <option value={instance.id}>
              {instance.label} ({instance.base_url})
            </option>
          {/each}
        </Select>
      </label>
    </InsetCard>
  {/if}

  <InsetCard className="p-5">
    <p class="font-medium ui-title">1. Provider access</p>
    {#if needsAuthorization}
      <p class="mt-2 text-sm leading-6 ui-muted">
        {#if isGenericGitProvider}
          No saved {providerName(selectedProvider)} authorization is available for
          this organization yet. Public repositories can still be connected by URL;
          private repositories need saved access or a one-off token.
        {:else}
          No saved {providerName(selectedProvider)} authorization is available for
          this organization yet. Save provider access first, then continue with repository
          selection.
        {/if}
      </p>
      <div class="mt-4">
        <Button
          className="rounded-full px-5"
          type="button"
          variant="secondary"
          onclick={onOpenAccess}
        >
          Authorize {providerName(selectedProvider)}
        </Button>
      </div>
    {:else if filteredAuthorizations.length === 1}
      <p class="mt-2 text-sm leading-6 ui-muted">
        Using saved authorization
        <span class="font-medium ui-title">
          {selectedAuthorization?.account_name ||
            selectedAuthorization?.auth_strategy}
        </span> automatically.
      </p>
      {#if selectedAuthorization?.auth_health === "reauthorization_required"}
        <p class="mt-2 text-sm text-(--danger)">
          This authorization needs reconnection before repositories can be
          loaded.
          {#if selectedAuthorization?.auth_failure_reason}
            <span class="block mt-1 text-xs ui-muted">
              Reason: {selectedAuthorization.auth_failure_reason}
            </span>
          {/if}
        </p>
        <div class="mt-3">
          <Button
            className="rounded-full px-5"
            type="button"
            variant="secondary"
            onclick={onOpenAccess}
          >
            Reconnect {providerName(selectedProvider)}
          </Button>
        </div>
      {/if}
    {:else}
      <div class="mt-4 grid gap-2">
        <span class="text-sm font-medium ui-title">
          Choose saved authorization
        </span>
        <div class="grid gap-3">
          {#each filteredAuthorizations as authorization (authorization.id)}
            {@const selected = selectedAuthorizationId === authorization.id}
            <button
              type="button"
              class={`w-full rounded-2xl border p-4 text-left transition ${authorizationCardClass(
                authorization,
                selected,
              )}`}
              aria-pressed={selected}
              onclick={() => selectAuthorization(authorization.id)}
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium ui-title">
                      {authorization.account_name ||
                        authorization.auth_strategy}
                    </span>
                    <span
                      class="ui-badge-muted rounded-full px-2.5 py-1 text-[11px] font-medium"
                    >
                      {authorization.status}
                    </span>
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
                    {#if selected}
                      <span
                        class="ui-badge-accent rounded-full px-2.5 py-1 text-[11px] font-medium"
                      >
                        Selected
                      </span>
                    {/if}
                  </div>
                  {#if authorization.masked_value}
                    <p class="mt-2 text-xs font-mono ui-muted">
                      {authorization.masked_value}
                    </p>
                  {/if}
                  {#if authorization.auth_failure_reason}
                    <p
                      class={`mt-2 text-xs ${
                        isAuthorizationReconnectRequired(authorization)
                          ? "text-(--danger)"
                          : "ui-muted"
                      }`}
                    >
                      {authorization.auth_failure_reason}
                    </p>
                  {/if}
                </div>
                <div class="shrink-0 pt-0.5">
                  <span
                    class={`inline-flex h-5 w-5 items-center justify-center rounded-full border text-[11px] font-medium ${
                      selected
                        ? "border-(--accent) bg-(--accent) text-white"
                        : "border-(--panel-border) ui-muted"
                    }`}
                  >
                    {selected ? "✓" : ""}
                  </span>
                </div>
              </div>
            </button>
          {/each}
        </div>
      </div>
      {#if selectedAuthorization?.auth_health === "reauthorization_required"}
        <p class="mt-2 text-sm text-(--danger)">
          This authorization needs reconnection before repositories can be
          loaded.
          {#if selectedAuthorization?.auth_failure_reason}
            <span class="block mt-1 text-xs ui-muted">
              Reason: {selectedAuthorization.auth_failure_reason}
            </span>
          {/if}
        </p>
        <div class="mt-2">
          <Button
            className="rounded-full px-5"
            type="button"
            variant="secondary"
            onclick={onOpenAccess}
          >
            Reconnect {providerName(selectedProvider)}
          </Button>
        </div>
      {/if}
    {/if}
  </InsetCard>

  <InsetCard className="p-5">
    <p class="font-medium ui-title">2. Repository</p>
    {#if isGenericGitProvider}
      <div class="mt-4 grid gap-4">
        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title"> Repository URL </span>
          <Input
            name="repository_url"
            placeholder="https://codeberg.org/owner/repository.git"
            bind:value={selectedRepositoryUrl}
            required
          />
        </label>
        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title"> composer.json path </span>
          <Input name="composer_json_path" placeholder="composer.json" />
        </label>
        <p class="text-sm leading-6 ui-muted">
          Generic Git uses the repository URL directly; repository discovery is
          not available for this provider.
        </p>
      </div>
    {:else if !needsAuthorization && selectedAuthorization}
      {#if selectedAuthorizationRequiresReconnect}
        <p class="mt-2 text-sm leading-6 text-(--danger)">
          This authorization must be reconnected before repositories can be
          loaded.
        </p>
        {#if selectedAuthorization.auth_failure_reason}
          <p class="mt-2 text-xs ui-muted">
            Reason: {selectedAuthorization.auth_failure_reason}
          </p>
        {/if}
        <div class="mt-4 flex flex-wrap gap-3">
          <Button
            type="button"
            variant="secondary"
            className="rounded-full px-5"
            onclick={onOpenAccess}
          >
            Reconnect {providerName(selectedProvider)}
          </Button>
        </div>
      {:else}
        <RepositoryPicker
          {selectedAuthorization}
          {providerResources}
          bind:providerResourcesAll
          bind:selectedResources
          bind:hasClickedLoad
          {existingSourceUrls}
          {organization}
          {onAuthorizationFailed}
        />
      {/if}
    {:else}
      <p class="mt-2 text-sm leading-6 ui-muted">
        Repository discovery becomes available after provider access is
        configured.
      </p>
    {/if}
  </InsetCard>

  <InsetCard className="p-5">
    <p class="font-medium ui-title">3. Review and connect</p>
    <div class="mt-4 grid gap-4">
      <label class="grid gap-2">
        <span class="text-sm font-medium ui-title">Visibility</span>
        <Select bind:value={connectVisibility} name="visibility">
          <option value="public">Public</option>
          <option value="private">Private</option>
        </Select>
      </label>

      {#if publishesPrivateRepository}
        <p role="alert" class="rounded-xl px-3 py-2 text-sm ui-badge-danger">
          A selected repository is private. With Public visibility, its packages
          will be publicly available.
        </p>
      {/if}

      <label class="grid gap-2">
        <span class="text-sm font-medium ui-title"> Keep last releases </span>
        <Input
          name="keep_last_releases"
          type="number"
          min="0"
          step="1"
          bind:value={keepLastReleases}
        />
        <span class="text-xs ui-muted">
          Limits active release versions, keeping the highest version numbers.
          Older versions are hidden from the registry and remain in package
          history. Dev versions do not count toward the limit. 0 disables the
          limit.
        </span>
      </label>

      <Button
        type="button"
        variant="soft"
        className="mr-auto inline-flex items-center gap-2 px-3 py-2 text-xs"
        aria-expanded={showAdvancedConnect}
        onclick={() => (showAdvancedConnect = !showAdvancedConnect)}
      >
        {#if showAdvancedConnect}<span aria-hidden="true">▼</span> Hide advanced{:else}<span
            aria-hidden="true"
          >
            ▶
          </span> Show advanced{/if}
      </Button>

      {#if showAdvancedConnect}
        <div
          class="grid gap-4 rounded-[1.25rem] border border-(--panel-border) p-4"
        >
          {#if !isGenericGitProvider}
            <label class="grid gap-2">
              <span class="text-sm font-medium ui-title">
                Repository URL fallback
              </span>
              <Input
                name="repository_url"
                placeholder="https://github.com/vendor/package"
                bind:value={selectedRepositoryUrl}
              />
            </label>
          {/if}
          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">
              One-off token override
            </span>
            <Input
              name="authorization_token"
              type="password"
              autocomplete="off"
              placeholder="Optional fallback when saved access is unavailable"
            />
          </label>
        </div>
      {/if}

      <div class="ui-code rounded-xl p-4 text-sm ui-muted">
        <p>
          <span class="font-medium ui-title">Provider:</span>
          {providerName(selectedProvider)}
        </p>
        <p class="mt-2">
          <span class="font-medium ui-title">Access:</span>
          {selectedAuthorization?.account_name ||
            selectedAuthorization?.auth_strategy ||
            "manual entry required"}
        </p>
        <div class="mt-2">
          <span class="font-medium ui-title">Repositories:</span>
          {#if isGenericGitProvider && hasManualRepositoryUrl}
            <ul class="mt-1 list-inside list-disc">
              <li class="break-all text-sm">
                {selectedRepositoryUrl}
              </li>
            </ul>
          {:else if selectedResources.length > 0}
            <ul class="mt-1 list-inside list-disc">
              {#each selectedResources as resourceUrl (resourceUrl)}
                <li class="break-all text-sm">
                  {providerResources.find(
                    (r) => r.canonical_url === resourceUrl,
                  )?.name || resourceUrl}
                </li>
              {/each}
            </ul>
          {:else}
            <span class="ui-muted">
              select repositories above or enter a repository URL
            </span>
          {/if}
        </div>
        <p class="mt-2">
          <span class="font-medium ui-title"> Release retention: </span>
          {Number(keepLastReleases) > 0
            ? `latest ${Number(keepLastReleases)} releases`
            : "all releases"}
        </p>
      </div>

      <div class="flex flex-wrap gap-3">
        <Button
          className="rounded-full px-5"
          type="submit"
          variant="primary"
          disabled={!canConnectSource || isSubmitting}
        >
          {#if isSubmitting}Connecting {connectSourceCount} source(s)...{:else}Connect
            {connectSourceCount || ""} source{connectSourceCount === 1
              ? ""
              : "s"}{/if}
        </Button>
        <Button
          className="rounded-full px-5"
          type="button"
          variant="secondary"
          onclick={onClose}
        >
          Cancel
        </Button>
      </div>
    </div>
  </InsetCard>
</form>
