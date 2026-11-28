<script lang="ts">
  import { onDestroy, type Snippet } from "svelte";
  import Button from "@/Components/ui/Button.svelte";
  import { toast } from "@/lib/toast";
  import { apiClient } from "@/lib/api";
  import type {
    ProviderAuthorizationPayload,
    ProviderResourcePayload,
  } from "@/types/pages";
  import type { OrganizationSummary } from "@/types/organization";
  import { isAuthorizationReconnectRequired } from "./authorizationStatus";

  interface Props {
    selectedAuthorization: ProviderAuthorizationPayload;
    providerResources: ProviderResourcePayload[];
    providerResourcesAll: Record<string, ProviderResourcePayload[]>;
    selectedResources: string[];
    hasClickedLoad: boolean;
    existingSourceUrls: Set<string>;
    organization: OrganizationSummary | null;
    // Marks the authorization as needing reconnection; returns the message to show.
    onAuthorizationFailed: (authorizationId: string) => string;
    // Extra actions rendered next to "Load repositories".
    children?: Snippet;
  }

  let {
    selectedAuthorization,
    providerResources,
    providerResourcesAll = $bindable(),
    selectedResources = $bindable(),
    hasClickedLoad = $bindable(),
    existingSourceUrls,
    organization,
    onAuthorizationFailed,
    children,
  }: Props = $props();

  let loadError = $state("");
  // Bumped per request and on unmount: a response is applied only if it is still the latest.
  let requestSeq = 0;
  onDestroy(() => requestSeq++);

  async function fetchResourcesForAuth(
    authorization: ProviderAuthorizationPayload,
  ): Promise<void> {
    const authorizationId = authorization.id;
    if (isAuthorizationReconnectRequired(authorization)) {
      selectedResources = [];
      if (authorization.auth_failure_reason) {
        toast.error(authorization.auth_failure_reason);
      }
      return;
    }

    const seq = ++requestSeq;
    loadError = "";
    const org = organization?.slug ?? "";
    const result = await apiClient.get<{
      resources: ProviderResourcePayload[];
    }>(
      `/organization/${org}/sources/authorizations/${authorizationId}/resources?provider=${encodeURIComponent(authorization.provider)}`,
    );

    // Provider or authorization changed (or the picker went away) while loading.
    if (seq !== requestSeq || selectedAuthorization.id !== authorizationId) {
      return;
    }

    if (result.ok) {
      providerResourcesAll = {
        ...providerResourcesAll,
        [authorizationId]: result.data.resources,
      };
    } else if (result.status === 401) {
      const errorMessage = onAuthorizationFailed(authorizationId);
      loadError = errorMessage || result.error;
      toast.error(loadError);
    } else {
      loadError = result.error;
    }
  }

  let resourcesLoading = $derived(
    !providerResourcesAll[selectedAuthorization.id] &&
      hasClickedLoad &&
      !loadError,
  );
</script>

<div class="flex flex-wrap items-center gap-2">
  {#if !hasClickedLoad}
    <Button
      type="button"
      variant="secondary"
      className="rounded-full px-5"
      onclick={() => {
        hasClickedLoad = true;
        fetchResourcesForAuth(selectedAuthorization);
      }}
    >
      Load repositories
    </Button>
  {/if}
  {@render children?.()}
</div>

{#if resourcesLoading}
  <div class="flex items-center gap-3" role="status">
    <div
      class="h-5 w-5 animate-spin rounded-full border-2 border-(--border) border-t-transparent"
    ></div>
    <span class="text-sm ui-muted"> Loading repositories... </span>
  </div>
{:else if loadError}
  <div class="grid gap-2">
    <p role="alert" class="text-sm text-(--danger)">{loadError}</p>
    <Button
      type="button"
      variant="secondary"
      className="mr-auto rounded-full px-5"
      onclick={() => fetchResourcesForAuth(selectedAuthorization)}
    >
      Retry
    </Button>
  </div>
{:else if !hasClickedLoad}
  <!-- nothing loaded yet -->
{:else if providerResources.length === 0}
  <p class="text-sm leading-6 ui-muted">
    No repositories found. You can enter a repository URL instead.
  </p>
{:else if providerResources.length === 1}
  <p class="text-sm leading-6 ui-muted">
    Repository <span class="font-medium ui-title">
      {providerResources[0].name}
    </span> was selected automatically.
  </p>
{:else}
  <div class="grid gap-2">
    <span class="text-sm font-medium ui-title">
      Select repositories ({selectedResources.length} selected)
    </span>
    <div
      class="max-h-60 overflow-y-auto rounded-2xl border border-(--panel-border) bg-(--surface) p-3"
    >
      {#each providerResources as resource (resource.canonical_url)}
        {@const isAlreadyConnected = existingSourceUrls.has(
          resource.canonical_url,
        )}
        <label
          class="flex items-start gap-3 rounded-lg p-2 {isAlreadyConnected
            ? 'opacity-50'
            : 'cursor-pointer hover:bg-(--surface-muted)'}"
        >
          <input
            type="checkbox"
            value={resource.canonical_url}
            disabled={isAlreadyConnected}
            checked={selectedResources.includes(resource.canonical_url)}
            onchange={(event) => {
              if (event.currentTarget.checked) {
                selectedResources = [
                  ...selectedResources,
                  resource.canonical_url,
                ];
              } else {
                selectedResources = selectedResources.filter(
                  (url) => url !== resource.canonical_url,
                );
              }
            }}
            class="mt-0.5 h-4 w-4 rounded border-(--panel-border) disabled:cursor-not-allowed"
          />
          <span class="flex flex-col gap-0.5">
            <span class="text-sm">
              {resource.name}{#if resource.private}<span
                  class="ml-1 text-xs ui-muted"
                >
                  · private
                </span>{/if}
            </span>
            {#if isAlreadyConnected}<span class="text-xs ui-badge-success">
                ✓ Already connected
              </span>{/if}
          </span>
        </label>
      {/each}
    </div>
    {#if selectedResources.length > 0}
      <button
        type="button"
        class="text-left text-xs ui-link-accent"
        onclick={() => (selectedResources = [])}
      >
        Clear selection
      </button>
    {/if}
  </div>
{/if}
