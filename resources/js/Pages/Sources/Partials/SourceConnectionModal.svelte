<script lang="ts">
  import { X } from "@lucide/svelte";
  import { router } from "@inertiajs/svelte";
  import { onMount, tick } from "svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
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
  import AddSourceForm from "./AddSourceForm.svelte";
  import ProviderAccessPanel from "./ProviderAccessPanel.svelte";

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
    initialProvider = "",
    initialProviderInstanceId = "",
    returnTo = "",
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
    dialog?.focus();

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
      bind:this={dialog}
      onclick={(event) => event.stopPropagation()}
      onkeydown={handleDialogKeydown}
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
              {providerName}
              {withOrganization}
              onSelectProviderInstance={selectProviderInstance}
              {onDeleteAuthorization}
              {onAccessSubmit}
              onOAuthRedirect={redirectToSourceOAuth}
              onOAuthConnect={connectOAuth}
              onOpenConnect={() => (activeModal = "connect")}
              onClose={handleClose}
            />
          {:else}
            <AddSourceForm
              {selectedProvider}
              {selectedProviderInstanceId}
              {selectedProviderMeta}
              {selectedProviderNoun}
              {providerNeedsInstance}
              {selectableProviderInstances}
              {filteredAuthorizations}
              bind:providerResourcesAll
              bind:selectedAuthorizationId
              bind:selectedResources
              bind:selectedRepositoryUrl
              bind:connectVisibility
              bind:keepLastReleases
              bind:showAdvancedConnect
              bind:hasClickedLoad
              {existingSourceUrls}
              {organization}
              {isSubmitting}
              {providerName}
              {withOrganization}
              onSelectProviderInstance={selectProviderInstance}
              onAuthorizationFailed={(authorizationId) =>
                applyAuthorizationFailureState(authorizationId, null)}
              onOpenAccess={() => (activeModal = "access")}
              onSubmit={handleConnectSubmit}
              onClose={handleClose}
            />
          {/if}
        </div>
      </Card>
    </div>
  </div>
</div>
