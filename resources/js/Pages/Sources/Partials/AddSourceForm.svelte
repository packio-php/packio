<script lang="ts">
  import Button from "@/Components/ui/Button.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import type {
    SourceProviderInstancePayload,
    ProviderAuthorizationPayload,
    ProviderResourcePayload,
  } from "@/types/pages";
  import type { OrganizationSummary } from "@/types/organization";
  import RepositoryPicker from "./RepositoryPicker.svelte";

  interface Props {
    selectedProvider: string;
    selectedProviderInstanceId: string;
    selectedProviderNoun: string;
    providerNeedsInstance: boolean;
    needsAuthorization: boolean;
    selectedAuthorization: ProviderAuthorizationPayload | null;
    selectedAuthorizationRequiresReconnect: boolean;
    formId: string;
    selectableProviderInstances: SourceProviderInstancePayload[];
    filteredAuthorizations: ProviderAuthorizationPayload[];
    providerResourcesAll: Record<string, ProviderResourcePayload[]>;
    selectedAuthorizationId: string;
    selectedResources: string[];
    selectedRepositoryUrl: string;
    connectVisibility: "private" | "public";
    keepLastReleases: string;
    showAdvancedConnect: boolean;
    showManualUrl: boolean;
    hasClickedLoad: boolean;
    existingSourceUrls: Set<string>;
    organization: OrganizationSummary | null;
    providerName: (providerKey: string) => string;
    withOrganization: (path: string) => string;
    onSelectProviderInstance: (providerInstanceId: string) => void;
    onAuthorizationFailed: (authorizationId: string) => string;
    onOpenAccess: () => void;
    onSubmit: (
      event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
    ) => void;
  }

  let {
    selectedProvider,
    selectedProviderInstanceId,
    selectedProviderNoun,
    providerNeedsInstance,
    needsAuthorization,
    selectedAuthorization,
    selectedAuthorizationRequiresReconnect,
    formId,
    selectableProviderInstances,
    filteredAuthorizations,
    providerResourcesAll = $bindable(),
    selectedAuthorizationId = $bindable(),
    selectedResources = $bindable(),
    selectedRepositoryUrl = $bindable(),
    connectVisibility = $bindable(),
    keepLastReleases = $bindable(),
    showAdvancedConnect = $bindable(),
    showManualUrl = $bindable(),
    hasClickedLoad = $bindable(),
    existingSourceUrls,
    organization,
    providerName,
    withOrganization,
    onSelectProviderInstance,
    onAuthorizationFailed,
    onOpenAccess,
    onSubmit,
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
  let publishesPrivateRepository = $derived(
    connectVisibility === "public" &&
      providerResources.some(
        (resource) =>
          resource.private &&
          selectedResources.includes(resource.canonical_url),
      ),
  );
  let usesRepositoryUrl = $derived(isGenericGitProvider || showManualUrl);
</script>

<form
  id={formId}
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
  {#if !usesRepositoryUrl}
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
  {/if}

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
        {#each selectableProviderInstances as instance (instance.id)}
          <option value={instance.id}>
            {instance.label} ({instance.base_url})
          </option>
        {/each}
      </Select>
    </label>
  {/if}

  <!-- Access -->
  <div class="grid gap-2 text-sm">
    {#if needsAuthorization}
      <p class="leading-6 ui-muted">
        {#if isGenericGitProvider}
          Public repositories can be connected by URL. For a private repository,
          use saved access or a one-off token in Advanced settings.
        {:else}
          No saved {providerName(selectedProvider)} access yet. Configure provider
          access to browse repositories.
        {/if}
      </p>
      <Button
        className="mr-auto rounded-full px-5"
        type="button"
        variant="secondary"
        onclick={onOpenAccess}
      >
        Configure provider access
      </Button>
    {:else}
      {#if filteredAuthorizations.length === 1}
        <p class="ui-muted">
          Using <span class="font-medium ui-title">
            {selectedAuthorization?.account_name ||
              selectedAuthorization?.auth_strategy}
          </span>
        </p>
      {:else}
        <label class="grid gap-2">
          <span class="font-medium ui-title">Saved access</span>
          <Select
            className="w-full"
            value={selectedAuthorizationId}
            onchange={(event) => selectAuthorization(event.currentTarget.value)}
          >
            <option value="" disabled>Choose saved access</option>
            {#each filteredAuthorizations as authorization (authorization.id)}
              <option value={authorization.id}>
                {authorization.account_name ||
                  authorization.auth_strategy}{authorization.masked_value
                  ? ` (${authorization.masked_value})`
                  : ""}
              </option>
            {/each}
          </Select>
        </label>
      {/if}
      {#if selectedAuthorizationRequiresReconnect}
        <p class="text-(--danger)">
          This access needs reconnection before repositories can be loaded.
          {#if selectedAuthorization?.auth_failure_reason}
            <span class="mt-1 block text-xs ui-muted">
              Reason: {selectedAuthorization.auth_failure_reason}
            </span>
          {/if}
        </p>
        <Button
          className="mr-auto rounded-full px-5"
          type="button"
          variant="secondary"
          onclick={onOpenAccess}
        >
          Reconnect {providerName(selectedProvider)}
        </Button>
      {/if}
    {/if}
  </div>

  <!-- Repository -->
  <div class="grid gap-3">
    <span class="text-sm font-medium ui-title">Repository</span>
    {#if usesRepositoryUrl}
      <label class="grid gap-2">
        <span class="text-sm ui-muted">Repository URL</span>
        <Input
          name="repository_url"
          placeholder={isGenericGitProvider
            ? "https://codeberg.org/owner/repository.git"
            : "https://github.com/vendor/package"}
          bind:value={selectedRepositoryUrl}
          required
        />
      </label>
      {#if isGenericGitProvider}
        <p class="text-xs ui-muted">
          For a private repository, use saved access or a one-off token in
          Advanced settings.
        </p>
      {:else}
        <Button
          type="button"
          variant="soft"
          className="mr-auto px-3 py-2 text-xs"
          onclick={() => (showManualUrl = false)}
        >
          Choose from repositories
        </Button>
      {/if}
    {:else if !needsAuthorization && selectedAuthorization && !selectedAuthorizationRequiresReconnect}
      {#key selectedAuthorization.id}
        <RepositoryPicker
          {selectedAuthorization}
          {providerResources}
          bind:providerResourcesAll
          bind:selectedResources
          bind:hasClickedLoad
          {existingSourceUrls}
          {organization}
          {onAuthorizationFailed}
        >
          <Button
            type="button"
            variant="soft"
            className="px-3 py-2 text-xs"
            onclick={() => (showManualUrl = true)}
          >
            Enter repository URL
          </Button>
        </RepositoryPicker>
      {/key}
    {:else}
      <Button
        type="button"
        variant="soft"
        className="mr-auto px-3 py-2 text-xs"
        onclick={() => {
          showManualUrl = true;
          // The one-off token lives in Advanced; reveal it when there is no saved access.
          showAdvancedConnect = showAdvancedConnect || needsAuthorization;
        }}
      >
        Enter repository URL
      </Button>
    {/if}
  </div>

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

  <div class="grid gap-3">
    <Button
      type="button"
      variant="soft"
      className="mr-auto inline-flex items-center gap-2 px-3 py-2 text-xs"
      aria-expanded={showAdvancedConnect}
      onclick={() => (showAdvancedConnect = !showAdvancedConnect)}
    >
      <span aria-hidden="true">{showAdvancedConnect ? "▼" : "▶"}</span>
      Advanced settings
    </Button>

    <!-- hidden, not removed: keep_last_releases and composer_json_path are always submitted -->
    <div
      class="grid gap-4 rounded-[1.25rem] border border-(--panel-border) p-4"
      hidden={!showAdvancedConnect}
    >
      <label class="grid gap-2">
        <span class="text-sm font-medium ui-title"> Keep last releases </span>
        <Input
          name="keep_last_releases"
          type="number"
          min="0"
          step="1"
          bind:value={keepLastReleases}
        />
        <span class="text-xs ui-muted">0 = no limit</span>
      </label>
      {#if isGenericGitProvider}
        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title"> composer.json path </span>
          <Input name="composer_json_path" placeholder="composer.json" />
        </label>
      {/if}
      {#if showAdvancedConnect}
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
      {/if}
    </div>
  </div>
</form>
