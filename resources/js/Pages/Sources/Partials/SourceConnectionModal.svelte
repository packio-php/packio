<script lang="ts">
  import { Trash2, X } from "@lucide/svelte";
  import { onMount, tick } from "svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import OAuthIcon from "@/Components/ui/OAuthIcon.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import { toast } from "@/lib/toast";
  import { apiClient } from "@/lib/api";
  import type { ToastPosition, ToastType } from "@/types/toast";
  import type {
    SourceProviderPayload,
    SourceProviderInstancePayload,
    ProviderAuthorizationPayload,
    ProviderResourcePayload,
    UserOAuthAccountPayload,
  } from "@/types/pages";
  import type { OrganizationSummary } from "@/types/organization";

  function handleClose(): void {
    hasClickedLoad = false;
    onClose();
  }

  interface Props {
    activeModal: "access" | "connect";
    providers: SourceProviderPayload[];
    providerAuthorizationsAll: ProviderAuthorizationPayload[];
    providerResourcesAll?: Record<string, ProviderResourcePayload[]>;
    sourceProviderInstances?: SourceProviderInstancePayload[];
    existingSourceUrls: Set<string>;
    organization: OrganizationSummary | null;
    resolvedBranding: { name: string };
    isSubmitting: boolean;
    user_oauth_accounts?: UserOAuthAccountPayload[];
    onClose: () => void;
    onConnectSubmit: (
      event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
      state: Record<string, unknown>,
    ) => void;
    onAccessSubmit: (
      event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
    ) => void;
    onDeleteAuthorization: (auth: ProviderAuthorizationPayload) => void;
    onAuthorizationCreated?: (
      authorization: ProviderAuthorizationPayload,
    ) => void;
    withOrganization: (path: string) => string;
  }

  let {
    activeModal = $bindable(),
    providers,
    providerAuthorizationsAll = $bindable(),
    providerResourcesAll = $bindable({}),
    sourceProviderInstances = [],
    existingSourceUrls,
    organization,
    resolvedBranding,
    isSubmitting,
    user_oauth_accounts = [],
    onClose,
    onConnectSubmit,
    onAccessSubmit,
    onDeleteAuthorization,
    onAuthorizationCreated = () => {},
    withOrganization,
  }: Props = $props();

  let selectedProvider = $state("github");
  let selectedAuthorizationId = $state("");
  let selectedProviderInstanceId = $state("");
  let selectedResources: string[] = $state([]);
  let selectedRepositoryUrl = $state("");
  let connectVisibility: "private" | "public" = $state("private");
  let keepLastReleases = $state("0");
  let showAdvancedConnect = $state(false);
  let instanceUrl = $state("");
  let oauthPopup: Window | null = null;
  let hasClickedLoad = $state(false);

  type BackendToastPayload = {
    type?: ToastType | null;
    message?: string | null;
    duration?: number | null;
    position?: ToastPosition | null;
  };

  type AuthorizationErrorResponse = {
    message?: string;
    error?: string;
    auth_health?: string | null;
    auth_failure_reason?: string | null;
    authorization?: {
      id?: string;
      status?: string;
    } | null;
  };

  function resolveAuthorizationErrorMessage(
    errorBody: AuthorizationErrorResponse | null,
    fallback: string,
  ): string {
    const failureReason = errorBody?.auth_failure_reason?.trim();
    if (failureReason) {
      return failureReason;
    }

    const message = errorBody?.message?.trim();
    if (message) {
      return message;
    }

    const errorMessage = errorBody?.error?.trim();
    if (errorMessage) {
      return errorMessage;
    }

    return fallback;
  }

  function applyAuthorizationFailureState(
    authorizationId: string,
    errorBody: AuthorizationErrorResponse | null,
    fallbackMessage = "",
  ): string {
    const errorMessage = resolveAuthorizationErrorMessage(
      errorBody,
      fallbackMessage,
    );

    providerAuthorizationsAll = providerAuthorizationsAll.map(
      (authorization) =>
        authorization.id === authorizationId
          ? {
              ...authorization,
              status:
                errorBody?.authorization?.id === authorizationId &&
                errorBody.authorization.status
                  ? errorBody.authorization.status
                  : "inactive",
              auth_health: errorBody?.auth_health ?? "reauthorization_required",
              auth_failure_reason:
                errorBody?.auth_failure_reason ?? errorMessage,
            }
          : authorization,
    );

    providerResourcesAll = {
      ...providerResourcesAll,
      [authorizationId]: [],
    };
    selectedResources = [];

    return errorMessage;
  }

  function showBackendToast(
    backendToast?: BackendToastPayload | null,
  ): boolean {
    if (
      !backendToast?.type ||
      !backendToast.message ||
      backendToast.message.trim() === ""
    ) {
      return false;
    }

    const options = {
      duration:
        typeof backendToast.duration === "number"
          ? backendToast.duration
          : undefined,
      position: backendToast.position ?? undefined,
    };

    if (backendToast.type === "success") {
      toast.success(backendToast.message, options);
      return true;
    }

    if (backendToast.type === "error") {
      toast.error(backendToast.message, options);
      return true;
    }

    if (backendToast.type === "warning") {
      toast.warning(backendToast.message, options);
      return true;
    }

    if (backendToast.type === "info") {
      toast.info(backendToast.message, options);
      return true;
    }

    return false;
  }

  async function fetchResourcesForAuth(
    authorizationId: string,
    provider: string,
  ): Promise<void> {
    const authorization = providerAuthorizationsAll.find(
      (item) => item.id === authorizationId,
    );
    if (isAuthorizationReconnectRequired(authorization)) {
      selectedResources = [];
      if (authorization?.auth_failure_reason) {
        toast.error(authorization.auth_failure_reason);
      }
      return;
    }

    const org = organization?.slug ?? "";
    const result = await apiClient.get<{
      resources: ProviderResourcePayload[];
    }>(
      `/organization/${org}/sources/authorizations/${authorizationId}/resources?provider=${encodeURIComponent(provider)}`,
    );

    if (result.ok) {
      providerResourcesAll = {
        ...providerResourcesAll,
        [authorizationId]: result.data.resources,
      };
    } else if (result.status === 401) {
      // For 401 we need the full errorBody; keep fallback behavior here,
      // because apiClient already returns a parsed error string.
      // Use applyAuthorizationFailureState with null when extra details are unavailable.
      const errorMessage = applyAuthorizationFailureState(
        authorizationId,
        null,
      );
      if (errorMessage) {
        toast.error(errorMessage);
      }
    } else {
      toast.error(result.error);
      providerResourcesAll = {
        ...providerResourcesAll,
        [authorizationId]: [],
      };
    }
  }

  // Restore state from sessionStorage if available
  onMount(() => {
    window.addEventListener("message", handleSourceOAuthMessage);

    const savedModalState = sessionStorage.getItem("sources_modal_state");
    const connectSuccess = sessionStorage.getItem("sources_connect_success");

    if (savedModalState) {
      const state = JSON.parse(savedModalState);
      selectedProvider = state.selectedProvider ?? "github";
      selectedAuthorizationId = state.selectedAuthorizationId ?? "";
      selectedProviderInstanceId = state.selectedProviderInstanceId ?? "";

      if (connectSuccess) {
        selectedResources = [];
        selectedRepositoryUrl = "";
        sessionStorage.removeItem("sources_connect_success");
      } else {
        selectedResources = state.selectedResources ?? [];
        selectedRepositoryUrl = state.selectedRepositoryUrl ?? "";
      }

      connectVisibility = state.connectVisibility ?? "private";
      keepLastReleases = state.keepLastReleases ?? "0";
      showAdvancedConnect = state.showAdvancedConnect ?? false;
      hasClickedLoad = false;
      sessionStorage.removeItem("sources_modal_state");
    } else {
      hasClickedLoad = false;
    }

    return () => {
      window.removeEventListener("message", handleSourceOAuthMessage);
    };
  });

  function resetConnectFlow(providerKey: string): void {
    selectedProvider = providerKey;
    selectedAuthorizationId = "";
    selectedProviderInstanceId = "";
    selectedResources = [];
    selectedRepositoryUrl = "";
    connectVisibility = "public";
    keepLastReleases = "0";
    showAdvancedConnect = false;
    hasClickedLoad = false;
  }

  function selectAuthorization(authorizationId: string): void {
    if (selectedAuthorizationId === authorizationId) {
      return;
    }

    selectedAuthorizationId = authorizationId;
    selectedResources = [];
    selectedRepositoryUrl = "";
    hasClickedLoad = false;
  }

  function selectProviderInstance(providerInstanceId: string): void {
    if (selectedProviderInstanceId === providerInstanceId) {
      return;
    }

    selectedProviderInstanceId = providerInstanceId;
    selectedAuthorizationId = "";
    selectedResources = [];
    selectedRepositoryUrl = "";
    hasClickedLoad = false;
  }

  async function createAuthorizationFromOAuth(
    provider: string,
    payload: Record<string, string>,
  ): Promise<void> {
    const org = organization?.slug ?? "";
    const result = await apiClient.post<{
      authorization?: ProviderAuthorizationPayload;
      toast?: BackendToastPayload;
      error?: string;
    }>(`/organization/${org}/sources/authorizations/oauth`, {
      provider,
      ...payload,
    });

    if (result.ok) {
      const { authorization, toast: backendToast } = result.data;
      showBackendToast(backendToast);

      if (!authorization) {
        return;
      }

      providerAuthorizationsAll = [...providerAuthorizationsAll, authorization];
      selectedAuthorizationId = authorization.id;
      sessionStorage.setItem(
        "sources_modal_state",
        JSON.stringify({
          selectedProvider: authorization.provider,
          selectedAuthorizationId: authorization.id,
          selectedProviderInstanceId:
            authorization.provider_instance_id ?? selectedProviderInstanceId,
          activeModal: "connect",
        }),
      );

      // Fetch resources for the new authorization
      const resourcesResult = await apiClient.get<{
        resources: ProviderResourcePayload[];
      }>(
        `/organization/${org}/sources/authorizations/${authorization.id}/resources?provider=${encodeURIComponent(authorization.provider)}`,
      );

      if (resourcesResult.ok) {
        providerResourcesAll = {
          ...providerResourcesAll,
          [authorization.id]: resourcesResult.data.resources,
        };
        await tick();
      } else if (resourcesResult.status === 401) {
        const errorMessage = applyAuthorizationFailureState(
          authorization.id,
          null,
        );
        if (errorMessage) {
          toast.error(errorMessage);
        }
      } else {
        providerResourcesAll = {
          ...providerResourcesAll,
          [authorization.id]: [],
        };
        toast.error(resourcesResult.error);
      }

      // Notify parent component about new authorization
      onAuthorizationCreated(authorization);
    } else {
      const data = (result as { data?: { toast?: BackendToastPayload } }).data;
      const backendToast = data?.toast;
      if (!showBackendToast(backendToast)) {
        toast.error(result.error);
      }
    }
  }

  async function handleSourceOAuthMessage(event: MessageEvent): Promise<void> {
    if (event.origin !== window.location.origin) {
      return;
    }

    const data = event.data as {
      type?: string;
      success?: boolean;
      provider?: string;
      sourceOAuthAccountId?: string;
      error?: string;
    };

    if (data.type !== "packio:source-oauth") {
      return;
    }

    if (oauthPopup && !oauthPopup.closed) {
      oauthPopup.close();
    }
    oauthPopup = null;

    if (!data.success || !data.provider || !data.sourceOAuthAccountId) {
      if (data.error) {
        toast.error(data.error);
      }
      return;
    }

    activeModal = "connect";

    await createAuthorizationFromOAuth(data.provider, {
      sourceOauthAccountId: data.sourceOAuthAccountId,
    });
  }

  function redirectToSourceOAuth(provider: string): void {
    sessionStorage.setItem(
      "sources_modal_state",
      JSON.stringify({
        selectedProvider,
        selectedProviderInstanceId,
        activeModal: "connect",
      }),
    );
    openSourceOAuthPopup(provider, "connect");
  }

  function connectOAuth(provider: string): void {
    // Use source-redirect for logged-in users to get repo scopes
    // This will redirect back to sources page after OAuth
    sessionStorage.setItem(
      "sources_modal_state",
      JSON.stringify({
        selectedProvider,
        selectedProviderInstanceId,
        activeModal: "access",
      }),
    );
    openSourceOAuthPopup(provider, "access");
  }

  function openSourceOAuthPopup(
    provider: string,
    modal: "access" | "connect",
  ): void {
    const organizationSlugRaw = organization?.slug ?? "";
    const organizationSlug = encodeURIComponent(organizationSlugRaw);
    const query = ["popup=1"];
    if (provider === "gitlab_self_hosted" && selectedProviderInstanceId) {
      query.push(
        `provider_instance_id=${encodeURIComponent(selectedProviderInstanceId)}`,
      );
    }
    const url = `/organization/${organizationSlug}/auth/oauth/${provider}/source-redirect?${query.join("&")}`;

    sessionStorage.setItem(
      "sources_modal_state",
      JSON.stringify({
        selectedProvider: provider,
        selectedProviderInstanceId,
        activeModal: modal,
      }),
    );

    oauthPopup = window.open(
      url,
      "packio-source-oauth",
      "popup=yes,width=720,height=760",
    );

    if (!oauthPopup) {
      toast.error(
        "Popup was blocked. Allow popups for this site and try again.",
      );
    }
  }

  function providerName(providerKey: string): string {
    const providerMeta = providers.find((p) => p.key === providerKey);

    return providerMeta?.name ?? providerKey;
  }

  function isAuthorizationReconnectRequired(
    authorization: ProviderAuthorizationPayload | null | undefined,
  ): boolean {
    return (
      authorization?.status === "inactive" ||
      authorization?.auth_health === "reauthorization_required"
    );
  }

  function authorizationHealthLabel(
    authorization: ProviderAuthorizationPayload | null | undefined,
  ): string | null {
    if (!authorization) {
      return null;
    }

    return isAuthorizationReconnectRequired(authorization)
      ? "Reauthorization required"
      : authorization.auth_health === "healthy"
        ? "Healthy"
        : authorization.auth_health
          ? "Authorization error"
          : null;
  }

  function authorizationCardClass(
    authorization: ProviderAuthorizationPayload,
    selected: boolean,
  ): string {
    const reconnectRequired = isAuthorizationReconnectRequired(authorization);

    if (selected && reconnectRequired) {
      return "border-(--button-danger-bg) bg-(--danger-soft) shadow-sm";
    }

    if (selected) {
      return "border-(--accent) bg-(--surface-muted) shadow-sm";
    }

    if (reconnectRequired) {
      return "border-(--button-danger-bg) bg-(--danger-soft)";
    }

    return "border-(--panel-border) bg-(--surface) hover:border-(--accent) hover:bg-(--surface-muted)";
  }

  function handleConnectSubmit(
    e: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ) {
    onConnectSubmit(e, {
      activeModal,
      selectedProvider,
      selectedProviderInstanceId,
      selectedAuthorizationId,
      selectedResources,
      selectedRepositoryUrl,
      connectVisibility,
      keepLastReleases,
      showAdvancedConnect,
    });
  }
  let selectableProviders = $derived(
    providers.filter((p) => p.capabilities?.supports_synchronization).length > 0
      ? providers.filter((p) => p.capabilities?.supports_synchronization)
      : providers,
  );
  $effect(() => {
    const providerKeys = selectableProviders.map((provider) => provider.key);
    if (providerKeys.length > 0 && !providerKeys.includes(selectedProvider)) {
      selectedProvider = providerKeys[0];
    }
  });
  let selectableProviderInstances = $derived(
    sourceProviderInstances.filter(
      (instance) =>
        instance.provider_type === selectedProvider &&
        instance.status === "active",
    ),
  );
  let manageableProviderInstances = $derived(
    sourceProviderInstances.filter(
      (instance) => instance.provider_type === selectedProvider,
    ),
  );
  let connectNeedsInstance = $derived(
    selectedProvider === "gitlab_self_hosted" &&
      selectableProviderInstances.length > 0,
  );
  let accessNeedsInstance = $derived(
    selectedProvider === "gitlab_self_hosted" &&
      manageableProviderInstances.length > 0,
  );
  let providerNeedsInstance = $derived(
    activeModal === "access" ? accessNeedsInstance : connectNeedsInstance,
  );
  let effectiveProviderInstances = $derived(
    activeModal === "access"
      ? manageableProviderInstances
      : selectableProviderInstances,
  );
  $effect(() => {
    if (!providerNeedsInstance) {
      selectedProviderInstanceId = "";
    } else if (
      effectiveProviderInstances.length > 0 &&
      !effectiveProviderInstances.some(
        (instance) => instance.id === selectedProviderInstanceId,
      )
    ) {
      selectedProviderInstanceId = effectiveProviderInstances[0].id;
    }
  });
  let filteredAuthorizations = $derived(
    providerAuthorizationsAll.filter(
      (a) =>
        a.provider === selectedProvider &&
        a.configured &&
        a.status === "active" &&
        (selectedProvider !== "gitlab_self_hosted" ||
          (a.provider_instance_id
            ? selectableProviderInstances.some(
                (instance) => instance.id === a.provider_instance_id,
              ) && a.provider_instance_id === selectedProviderInstanceId
            : selectableProviderInstances.length === 0)),
    ),
  );
  let manageableAuthorizations = $derived(
    providerAuthorizationsAll.filter(
      (a) =>
        a.provider === selectedProvider &&
        (!accessNeedsInstance ||
          a.provider_instance_id === selectedProviderInstanceId),
    ),
  );
  $effect(() => {
    const authIds = filteredAuthorizations.map((a) => a.id);
    if (filteredAuthorizations.length === 1) {
      selectedAuthorizationId = filteredAuthorizations[0].id;
    } else if (
      selectedAuthorizationId &&
      !authIds.includes(selectedAuthorizationId)
    ) {
      selectedAuthorizationId = "";
    }
  });
  // Track if resources are loading for selected authorization
  let resourcesLoading = $derived(
    selectedAuthorizationId !== "" &&
      !providerResourcesAll[selectedAuthorizationId] &&
      hasClickedLoad,
  );
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
  let selectedProviderMeta = $derived(
    providers.find((p) => p.key === selectedProvider) ?? null,
  );
  let selectedOAuthProvider = $derived(
    selectedProviderMeta?.oauth_provider ?? null,
  );
  let selectedProviderInstance = $derived(
    effectiveProviderInstances.find(
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
  let isGenericGitProvider = $derived(selectedProvider === "generic_git");
  let providerSupportsSelectedInstanceToken = $derived(
    !providerNeedsInstance ||
      selectedProviderInstance?.auth_mode === "token" ||
      selectedProviderInstance?.auth_mode === "oauth2_and_token",
  );
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

<div
  class="fixed inset-0 z-[60] overflow-y-auto bg-(--page-bg)/70 backdrop-blur"
  onclick={handleClose}
  onkeydown={(event) => event.key === "Escape" && handleClose()}
  role="presentation"
  tabindex="-1"
>
  <div class="container mx-auto min-h-full px-4 py-6 md:py-10">
    <div
      onclick={(event) => event.stopPropagation()}
      onkeydown={(event) => event.key === "Escape" && onClose()}
      role="dialog"
      aria-modal="true"
      aria-label={activeModal === "connect"
        ? "Add source"
        : "Manage provider access"}
      tabindex="-1"
    >
      <Card
        className="mx-auto max-h-[calc(100vh-3rem)] max-w-3xl overflow-y-auto rounded-[1.9rem] p-6 md:p-8"
      >
        <div class="flex items-start justify-between gap-4">
          <SectionHeading
            eyebrow={activeModal === "connect"
              ? "Add source"
              : "Provider access"}
            title={activeModal === "connect"
              ? `Connect a source to ${organization?.name || resolvedBranding.name}`
              : "Manage provider access"}
            description={activeModal === "connect"
              ? "Choose a provider first. If access already exists, Packio will reuse it automatically."
              : "Provider authorization is stored at the organization level and reused across multiple sources."}
            className="max-w-2xl"
          />
          <Button
            type="button"
            variant="iconSmall"
            aria-label="Close modal"
            onclick={handleClose}
          >
            <X aria-hidden="true" size={16} strokeWidth={2} />
          </Button>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
          <!-- Sidebar -->
          <div class="grid gap-3 self-start">
            {#each selectableProviders as provider (provider.key)}
              <div
                role="button"
                tabindex="0"
                class={`rounded-[1.25rem] border p-4 text-left transition cursor-pointer ${
                  selectedProvider === provider.key
                    ? "ui-card"
                    : "ui-inset hover:border-(--accent)"
                }`}
                onclick={() => resetConnectFlow(provider.key)}
                onkeydown={(event) =>
                  event.key === "Enter" && resetConnectFlow(provider.key)}
              >
                <div class="flex items-start justify-between gap-3">
                  <span class="font-medium ui-title">{provider.name}</span>
                  <span
                    class={`rounded-full px-2.5 py-1 text-[11px] font-medium ${
                      provider.capabilities?.supports_synchronization
                        ? "ui-badge-accent"
                        : "ui-badge-muted"
                    }`}
                  >
                    {provider.capabilities?.supports_synchronization
                      ? "ready"
                      : "soon"}
                  </span>
                </div>
                <span class="mt-2 block text-xs leading-6 ui-muted">
                  {provider.description}
                </span>
              </div>
            {/each}
          </div>

          <!-- Main Content -->
          {#if activeModal === "access"}
            <div class="grid gap-5">
              {#if providerNeedsInstance}
                <InsetCard className="p-5">
                  <label class="grid gap-2">
                    <span class="text-sm font-medium ui-title"
                      >GitLab instance</span
                    >
                    <Select
                      className="rounded-2xl px-4 py-3 text-sm w-full h-14"
                      value={selectedProviderInstanceId}
                      onchange={(event) =>
                        selectProviderInstance(event.currentTarget.value)}
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
                      Authorize this provider once, then source connect flows
                      can reuse it automatically.
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
                                {authorization.account_name ||
                                  authorization.auth_strategy}
                              </p>
                              {#if authorizationHealthLabel(authorization)}
                                <span
                                  class={`rounded-full px-2.5 py-1 text-[11px] font-medium ${
                                    isAuthorizationReconnectRequired(
                                      authorization,
                                    )
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
                            <Trash2
                              aria-hidden="true"
                              size={14}
                              strokeWidth={2}
                            />
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
                    Connect source access for {providerName(selectedProvider)}.
                    Login accounts are shown only as a reference.
                  </p>
                  <div class="mt-4 grid gap-3">
                    {#if hasOAuthForSelected}
                      {#each selectedOAuthAccounts as account (account.id)}
                        <Button
                          type="button"
                          variant="secondary"
                          className="rounded-3xl px-5 flex items-center gap-3 justify-start h-auto py-3"
                          onclick={() =>
                            redirectToSourceOAuth(account.provider)}
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
                          selectedOAuthProvider &&
                          connectOAuth(selectedOAuthProvider)}
                      >
                        <OAuthIcon provider={selectedOAuthIconProvider} />
                        <span
                          >Connect with {providerName(selectedProvider)}</span
                        >
                      </Button>
                    {/if}
                  </div>
                </InsetCard>

                {#if providerSupportsSelectedInstanceToken}
                  <div class="relative py-2">
                    <div class="absolute inset-0 flex items-center">
                      <div
                        class="w-full border-t border-(--panel-border)"
                      ></div>
                    </div>
                    <div class="relative flex justify-center">
                      <span class="bg-(--surface) px-3 text-sm ui-muted"
                        >or enter token manually</span
                      >
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
                  <input
                    type="hidden"
                    name="provider"
                    value={selectedProvider}
                  />
                  {#if providerNeedsInstance}
                    <input
                      type="hidden"
                      name="provider_instance_id"
                      value={selectedProviderInstanceId}
                    />
                  {/if}
                  {#if selectedProvider === "gitlab_self_hosted" && !providerNeedsInstance}
                    <label class="grid gap-2">
                      <span class="text-sm font-medium ui-title"
                        >GitLab instance URL</span
                      >
                      <Input
                        className="w-full h-14"
                        name="instance_url"
                        type="url"
                        autocomplete="off"
                        placeholder="https://gitlab.mycompany.com"
                        bind:value={instanceUrl}
                      />
                    </label>
                  {/if}
                  <label class="grid gap-2">
                    <span class="text-sm font-medium ui-title"
                      >{providerName(selectedProvider)} authorization token</span
                    >
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
                      <span class="text-sm font-medium ui-title"
                        >Auth scheme</span
                      >
                      <Select
                        className="rounded-2xl px-4 py-3 text-sm w-full h-14"
                        name="auth_scheme"
                      >
                        <option value="bearer">Bearer token</option>
                        <option value="basic"
                          >Basic auth (username:token)</option
                        >
                      </Select>
                    </label>
                  {/if}
                  <div class="flex flex-wrap gap-3">
                    <Button
                      className="rounded-full px-5"
                      type="submit"
                      variant="primary">Save provider access</Button
                    >
                    <Button
                      className="rounded-full px-5"
                      type="button"
                      variant="secondary"
                      onclick={() => (activeModal = "connect")}
                    >
                      Continue to add source
                    </Button>
                  </div>
                </form>
              {:else}
                <InsetCard className="p-5">
                  <p class="font-medium ui-title">Token authorization</p>
                  <p class="mt-1 text-sm ui-muted">
                    This GitLab instance is configured for OAuth only.
                  </p>
                </InsetCard>
              {/if}
            </div>
          {:else}
            <form
              class="grid gap-5"
              method="POST"
              action={withOrganization("/sources/connect")}
              onsubmit={(event) => {
                event.preventDefault();
                handleConnectSubmit(
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
                <input
                  type="hidden"
                  name="resource_urls[]"
                  value={resourceUrl}
                />
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
                    <span class="text-sm font-medium ui-title"
                      >GitLab instance</span
                    >
                    <Select
                      className="rounded-2xl px-4 py-3 text-sm w-full h-14"
                      value={selectedProviderInstanceId}
                      onchange={(event) =>
                        selectProviderInstance(event.currentTarget.value)}
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
                      No saved {providerName(selectedProvider)} authorization is available
                      for this organization yet. Public repositories can still be
                      connected by URL; private repositories need saved access or
                      a one-off token.
                    {:else}
                      No saved {providerName(selectedProvider)} authorization is available
                      for this organization yet. Save provider access first, then
                      continue with repository selection.
                    {/if}
                  </p>
                  <div class="mt-4">
                    <Button
                      className="rounded-full px-5"
                      type="button"
                      variant="secondary"
                      onclick={() => (activeModal = "access")}
                    >
                      Authorize {providerName(selectedProvider)}
                    </Button>
                  </div>
                {:else if filteredAuthorizations.length === 1}
                  <p class="mt-2 text-sm leading-6 ui-muted">
                    Using saved authorization
                    <span class="font-medium ui-title"
                      >{selectedAuthorization?.account_name ||
                        selectedAuthorization?.auth_strategy}</span
                    > automatically.
                  </p>
                  {#if selectedAuthorization?.auth_health === "reauthorization_required"}
                    <p class="mt-2 text-sm text-(--danger)">
                      This authorization needs reconnection before repositories
                      can be loaded.
                      {#if selectedAuthorization?.auth_failure_reason}
                        <span class="block mt-1 text-xs ui-muted"
                          >Reason: {selectedAuthorization.auth_failure_reason}</span
                        >
                      {/if}
                    </p>
                    <div class="mt-3">
                      <Button
                        className="rounded-full px-5"
                        type="button"
                        variant="secondary"
                        onclick={() => (activeModal = "access")}
                      >
                        Reconnect {providerName(selectedProvider)}
                      </Button>
                    </div>
                  {/if}
                {:else}
                  <div class="mt-4 grid gap-2">
                    <span class="text-sm font-medium ui-title"
                      >Choose saved authorization</span
                    >
                    <div class="grid gap-3">
                      {#each filteredAuthorizations as authorization (authorization.id)}
                        {@const selected =
                          selectedAuthorizationId === authorization.id}
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
                                      isAuthorizationReconnectRequired(
                                        authorization,
                                      )
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
                                    isAuthorizationReconnectRequired(
                                      authorization,
                                    )
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
                      This authorization needs reconnection before repositories
                      can be loaded.
                      {#if selectedAuthorization?.auth_failure_reason}
                        <span class="block mt-1 text-xs ui-muted"
                          >Reason: {selectedAuthorization.auth_failure_reason}</span
                        >
                      {/if}
                    </p>
                    <div class="mt-2">
                      <Button
                        className="rounded-full px-5"
                        type="button"
                        variant="secondary"
                        onclick={() => (activeModal = "access")}
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
                      <span class="text-sm font-medium ui-title"
                        >Repository URL</span
                      >
                      <Input
                        name="repository_url"
                        placeholder="https://codeberg.org/owner/repository.git"
                        bind:value={selectedRepositoryUrl}
                        required
                      />
                    </label>
                    <label class="grid gap-2">
                      <span class="text-sm font-medium ui-title"
                        >composer.json path</span
                      >
                      <Input
                        name="composer_json_path"
                        placeholder="composer.json"
                      />
                    </label>
                    <p class="text-sm leading-6 ui-muted">
                      Generic Git uses the repository URL directly; repository
                      discovery is not available for this provider.
                    </p>
                  </div>
                {:else if !needsAuthorization && selectedAuthorization}
                  {#if selectedAuthorizationRequiresReconnect}
                    <p class="mt-2 text-sm leading-6 text-(--danger)">
                      This authorization must be reconnected before repositories
                      can be loaded.
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
                        onclick={() => (activeModal = "access")}
                      >
                        Reconnect {providerName(selectedProvider)}
                      </Button>
                    </div>
                  {:else if resourcesLoading}
                    <div class="mt-4 flex items-center gap-3">
                      <div
                        class="h-5 w-5 animate-spin rounded-full border-2 border-(--border) border-t-transparent"
                      ></div>
                      <span class="text-sm ui-muted"
                        >Loading repositories...</span
                      >
                    </div>
                  {:else if !hasClickedLoad}
                    <p class="mt-2 text-sm leading-6 ui-muted">
                      Click Load to fetch available repositories for this
                      authorization.
                    </p>
                    <Button
                      type="button"
                      variant="secondary"
                      className="rounded-full px-5 mt-3"
                      onclick={() => {
                        hasClickedLoad = true;
                        const auth = providerAuthorizationsAll.find(
                          (a) => a.id === selectedAuthorizationId,
                        );
                        if (auth) {
                          fetchResourcesForAuth(
                            selectedAuthorizationId,
                            auth.provider,
                          );
                        }
                      }}
                    >
                      Load repositories
                    </Button>
                  {:else if providerResources.length === 0}
                    <p class="mt-2 text-sm leading-6 ui-muted">
                      No repositories were discovered for this authorization
                      yet. You can still enter a repository URL manually in
                      Advanced.
                    </p>
                  {:else if providerResources.length === 1}
                    <p class="mt-2 text-sm leading-6 ui-muted">
                      Repository <span class="font-medium ui-title"
                        >{providerResources[0].name}</span
                      > was selected automatically.
                    </p>
                  {:else}
                    <div class="mt-4 grid gap-2">
                      <span class="text-sm font-medium ui-title"
                        >Select repositories ({selectedResources.length} selected)</span
                      >
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
                              checked={selectedResources.includes(
                                resource.canonical_url,
                              )}
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
                              <span class="text-sm"
                                >{resource.name}{#if resource.private}<span
                                    class="ml-1 text-xs ui-muted"
                                    >· private</span
                                  >{/if}</span
                              >
                              {#if isAlreadyConnected}<span
                                  class="text-xs ui-badge-success"
                                  >✓ Already connected</span
                                >{/if}
                            </span>
                          </label>
                        {/each}
                      </div>
                      {#if selectedResources.length > 0}
                        <button
                          type="button"
                          class="text-left text-xs ui-link-accent"
                          onclick={() => (selectedResources = [])}
                          >Clear selection</button
                        >
                      {/if}
                    </div>
                  {/if}
                {:else}
                  <p class="mt-2 text-sm leading-6 ui-muted">
                    Repository discovery becomes available after provider access
                    is configured.
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

                  <label class="grid gap-2">
                    <span class="text-sm font-medium ui-title"
                      >Keep last releases</span
                    >
                    <Input
                      name="keep_last_releases"
                      type="number"
                      min="0"
                      step="1"
                      bind:value={keepLastReleases}
                    />
                    <span class="text-xs ui-muted">
                      Limits active release versions, keeping the highest
                      version numbers. Older versions are hidden from the
                      registry and remain in package history. Dev versions do
                      not count toward the limit. 0 disables the limit.
                    </span>
                  </label>

                  <Button
                    type="button"
                    variant="soft"
                    className="mr-auto inline-flex items-center gap-2 px-3 py-2 text-xs"
                    aria-expanded={showAdvancedConnect}
                    onclick={() => (showAdvancedConnect = !showAdvancedConnect)}
                  >
                    {#if showAdvancedConnect}<span aria-hidden="true">▼</span> Hide
                      advanced{:else}<span aria-hidden="true">▶</span> Show advanced{/if}
                  </Button>

                  {#if showAdvancedConnect}
                    <div
                      class="grid gap-4 rounded-[1.25rem] border border-(--panel-border) p-4"
                    >
                      {#if !isGenericGitProvider}
                        <label class="grid gap-2">
                          <span class="text-sm font-medium ui-title"
                            >Repository URL fallback</span
                          >
                          <Input
                            name="repository_url"
                            placeholder="https://github.com/vendor/package"
                            bind:value={selectedRepositoryUrl}
                          />
                        </label>
                      {/if}
                      <label class="grid gap-2">
                        <span class="text-sm font-medium ui-title"
                          >One-off token override</span
                        >
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
                        <span class="ui-muted"
                          >select repositories above or enter a repository URL</span
                        >
                      {/if}
                    </div>
                    <p class="mt-2">
                      <span class="font-medium ui-title"
                        >Release retention:</span
                      >
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
                        {connectSourceCount || ""} source{connectSourceCount ===
                        1
                          ? ""
                          : "s"}{/if}
                    </Button>
                    <Button
                      className="rounded-full px-5"
                      type="button"
                      variant="secondary"
                      onclick={handleClose}>Cancel</Button
                    >
                  </div>
                </div>
              </InsetCard>
            </form>
          {/if}
        </div>
      </Card>
    </div>
  </div>
</div>
