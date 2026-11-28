<script lang="ts">
  import { X } from "@lucide/svelte";
  import { router } from "@inertiajs/svelte";
  import { onMount, tick, untrack } from "svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
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
  import type { PackioFlashPayload } from "@/types/flash";
  import AddSourceForm from "./AddSourceForm.svelte";
  import ProviderAccessPanel from "./ProviderAccessPanel.svelte";
  import { isAuthorizationReconnectRequired } from "./authorizationStatus";

  const connectFormId = "add-source-form";
  const authorizationRejectedMessage =
    "The provider rejected this access. Reconnect it to load repositories.";

  let dialog: HTMLElement | undefined = $state();

  // Keeps Tab inside the dialog: the page behind it is not reachable while it is open.
  function trapTab(event: KeyboardEvent): void {
    const focusable = [
      ...(dialog?.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ) ?? []),
    ];
    const first = focusable[0];
    const last = focusable.at(-1);
    if (!first || !last) {
      return;
    }

    const active = document.activeElement;
    const leaving =
      !dialog?.contains(active) || active === (event.shiftKey ? first : last);
    if (leaving) {
      event.preventDefault();
      (event.shiftKey ? last : first).focus();
    }
  }

  function handleDialogKeydown(event: KeyboardEvent): void {
    if (event.key === "Escape") {
      onClose();
    } else if (event.key === "Tab") {
      trapTab(event);
    }
  }

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
    initialProvider?: string;
    initialProviderInstanceId?: string;
    returnTo?: string;
    connectResult?: PackioFlashPayload["source_connect_result"] | null;
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
    isSubmitting,
    user_oauth_accounts = [],
    initialProvider = "",
    initialProviderInstanceId = "",
    returnTo = "",
    connectResult = null,
    onClose,
    onConnectSubmit,
    onAccessSubmit,
    onDeleteAuthorization,
    onAuthorizationCreated = () => {},
    withOrganization,
  }: Props = $props();

  // Connect-mode state lives here: it survives switching to access mode and back,
  // and the sessionStorage restore, OAuth flow and provider reset write it.
  let selectedProvider = $state("github");
  let selectedAuthorizationId = $state("");
  let selectedProviderInstanceId = $state("");
  let selectedResources: string[] = $state([]);
  let selectedRepositoryUrl = $state("");
  let connectVisibility = $state<"private" | "public">("private");
  let keepLastReleases = $state("0");
  let showAdvancedConnect = $state(false);
  let oauthPopup: Window | null = null;
  let hasClickedLoad = $state(false);
  let showManualUrl = $state(false);
  // Where access mode was entered from; the connect wizard resumes after a successful authorization.
  let accessOrigin: "connect" | null = $state(null);
  let result = $state<PackioFlashPayload["source_connect_result"] | null>(null);

  // After a partial connect only the failed repositories stay selected.
  $effect(() => {
    const latest = connectResult;
    untrack(() => {
      result = latest;
      if (latest && latest.failed.length > 0 && !showManualUrl) {
        selectedResources = latest.failed.map((item) => item.repository);
      }
    });
  });

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

  // Restore state from sessionStorage if available
  onMount(() => {
    window.addEventListener("message", handleSourceOAuthMessage);
    (dialog?.querySelector<HTMLElement>("#source-provider") ?? dialog)?.focus();

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
      if (providers.some((p) => p.key === initialProvider)) {
        selectedProvider = initialProvider;
        selectedProviderInstanceId = initialProviderInstanceId;
      }
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
    connectVisibility = "private";
    keepLastReleases = "0";
    showAdvancedConnect = false;
    showManualUrl = false;
    hasClickedLoad = false;
    result = null;
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
    result = null;
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
      if (returnTo && activeModal === "access") {
        onAuthorizationCreated(authorization);
        router.visit(returnTo);
        return;
      }
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
          authorizationRejectedMessage,
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

    // Opened from a source page: stay in access mode; the authorization step returns there.
    if (!(returnTo && activeModal === "access")) {
      activeModal = "connect";
    }

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
    if (selectedProviderIsSelfHosted && selectedProviderInstanceId) {
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
  let selectedProviderIsSelfHosted = $derived(
    providers.find((p) => p.key === selectedProvider)
      ?.supports_self_hosted_instances === true,
  );
  let selectedProviderNoun = $derived(
    providerName(selectedProvider).replace(/ Self-Hosted$/, ""),
  );
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
    selectedProviderIsSelfHosted && selectableProviderInstances.length > 0,
  );
  let accessNeedsInstance = $derived(
    selectedProviderIsSelfHosted && manageableProviderInstances.length > 0,
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
        (!selectedProviderIsSelfHosted ||
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
  let selectedProviderMeta = $derived(
    providers.find((p) => p.key === selectedProvider) ?? null,
  );
  let isGenericGitProvider = $derived(selectedProvider === "generic_git");
  let needsAuthorization = $derived(filteredAuthorizations.length === 0);
  let selectedAuthorization = $derived(
    filteredAuthorizations.find((a) => a.id === selectedAuthorizationId) ??
      null,
  );
  let selectedAuthorizationRequiresReconnect = $derived(
    isAuthorizationReconnectRequired(selectedAuthorization),
  );
  let usesRepositoryUrl = $derived(isGenericGitProvider || showManualUrl);
  let hasManualRepositoryUrl = $derived(selectedRepositoryUrl.trim() !== "");
  let connectSourceCount = $derived(
    usesRepositoryUrl
      ? hasManualRepositoryUrl
        ? 1
        : 0
      : selectedResources.length,
  );
  let canConnectSource = $derived(
    selectedProviderMeta?.capabilities?.supports_synchronization !== false &&
      !selectedAuthorizationRequiresReconnect &&
      (usesRepositoryUrl
        ? hasManualRepositoryUrl
        : !needsAuthorization &&
          !(
            filteredAuthorizations.length > 1 && selectedAuthorizationId === ""
          ) &&
          selectedResources.length > 0),
  );
  // Only active, configured access counts as available.
  let hasSavedAccess = $derived(
    activeModal === "connect"
      ? filteredAuthorizations.some((a) => !isAuthorizationReconnectRequired(a))
      : manageableAuthorizations.some(
          (a) => a.configured && a.status === "active",
        ),
  );
</script>

<div
  class="fixed inset-0 z-[60] overflow-y-auto bg-(--page-bg)/70 backdrop-blur"
  onclick={handleClose}
  onkeydown={(event) => event.key === "Escape" && handleClose()}
  role="presentation"
  tabindex="-1"
>
  <div class="container mx-auto flex min-h-full items-center px-4 py-4">
    <div
      bind:this={dialog}
      onclick={(event) => event.stopPropagation()}
      onkeydown={handleDialogKeydown}
      role="dialog"
      aria-modal="true"
      aria-label={activeModal === "connect"
        ? "Add source"
        : "Manage provider access"}
      tabindex="-1"
      class="mx-auto w-full max-w-[40rem]"
    >
      <Card
        padding="p-0"
        className="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-[1.9rem]"
      >
        <div
          class="flex items-start justify-between gap-4 px-5 pt-5 pb-3 sm:px-6"
        >
          <div>
            <h2 class="text-xl font-semibold" style="color: var(--page-ink);">
              {activeModal === "connect"
                ? "Add source"
                : "Manage provider access"}
            </h2>
            {#if activeModal === "access"}
              <p class="mt-1 text-sm ui-muted">
                Access is saved for this organization
              </p>
            {/if}
          </div>
          <Button
            type="button"
            variant="iconSmall"
            aria-label="Close modal"
            onclick={handleClose}
          >
            <X aria-hidden="true" size={16} strokeWidth={2} />
          </Button>
        </div>

        <div
          class="grid min-h-0 flex-1 content-start gap-5 overflow-y-auto px-5 py-3 sm:px-6"
        >
          <div class="grid gap-2">
            <label class="grid gap-2">
              <span class="text-sm font-medium ui-title">Provider</span>
              <Select
                id="source-provider"
                className="w-full"
                value={selectedProvider}
                onchange={(event) =>
                  resetConnectFlow(event.currentTarget.value)}
              >
                {#each selectableProviders as provider (provider.key)}
                  <option value={provider.key}>{provider.name}</option>
                {/each}
              </Select>
            </label>
            <p class="text-sm ui-muted" aria-live="polite">
              {hasSavedAccess ? "Saved access available" : "No saved access"}
            </p>
          </div>

          {#if result && result.failed.length > 0 && activeModal === "connect"}
            <div
              role="alert"
              class="grid gap-1 rounded-xl px-3 py-2 text-sm ui-badge-danger"
            >
              {#if result.queued.length > 0}
                <p>Connected: {result.queued.join(", ")}</p>
              {/if}
              <p>
                Needs retry:
                {result.failed
                  .map((item) => `${item.repository} (${item.message})`)
                  .join(", ")}
              </p>
            </div>
          {/if}

          {#key selectedProvider}
            {#if activeModal === "access"}
              <ProviderAccessPanel
                {selectedProvider}
                {selectedProviderInstanceId}
                {selectedProviderMeta}
                {selectedProviderNoun}
                {selectedProviderIsSelfHosted}
                {providerNeedsInstance}
                {manageableProviderInstances}
                {manageableAuthorizations}
                {user_oauth_accounts}
                {returnTo}
                {accessOrigin}
                {providerName}
                {withOrganization}
                onSelectProviderInstance={selectProviderInstance}
                {onDeleteAuthorization}
                {onAccessSubmit}
                onOAuthRedirect={redirectToSourceOAuth}
                onOAuthConnect={connectOAuth}
                onOpenConnect={() => {
                  accessOrigin = null;
                  activeModal = "connect";
                }}
                onClose={handleClose}
              />
            {:else}
              <AddSourceForm
                {selectedProvider}
                {selectedProviderInstanceId}
                {selectedProviderNoun}
                {providerNeedsInstance}
                {needsAuthorization}
                {selectedAuthorization}
                {selectedAuthorizationRequiresReconnect}
                formId={connectFormId}
                {selectableProviderInstances}
                {filteredAuthorizations}
                bind:providerResourcesAll
                bind:selectedAuthorizationId
                bind:selectedResources
                bind:selectedRepositoryUrl
                bind:connectVisibility
                bind:keepLastReleases
                bind:showAdvancedConnect
                bind:showManualUrl
                bind:hasClickedLoad
                {existingSourceUrls}
                {organization}
                {providerName}
                {withOrganization}
                onSelectProviderInstance={selectProviderInstance}
                onAuthorizationFailed={(authorizationId) =>
                  applyAuthorizationFailureState(
                    authorizationId,
                    null,
                    authorizationRejectedMessage,
                  )}
                onOpenAccess={() => {
                  accessOrigin = "connect";
                  activeModal = "access";
                }}
                onSubmit={handleConnectSubmit}
              />
            {/if}
          {/key}
        </div>

        {#if activeModal === "connect"}
          <div
            class="flex flex-wrap items-center justify-end gap-3 border-t border-(--panel-border) px-5 py-4 sm:px-6"
          >
            {#if connectSourceCount > 0}
              <span class="mr-auto text-sm ui-muted">
                {connectSourceCount} selected · {connectVisibility === "public"
                  ? "Public"
                  : "Private"}
              </span>
            {/if}
            <Button
              className="rounded-full px-5"
              type="button"
              variant="secondary"
              onclick={handleClose}
            >
              Cancel
            </Button>
            <Button
              className="rounded-full px-5"
              type="submit"
              form={connectFormId}
              variant="primary"
              disabled={!canConnectSource || isSubmitting}
            >
              {#if isSubmitting}Connecting…{:else if connectSourceCount > 1}Connect
                {connectSourceCount} sources{:else}Connect source{/if}
            </Button>
          </div>
        {/if}
      </Card>
    </div>
  </div>
</div>
