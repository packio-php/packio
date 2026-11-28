<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import { onDestroy, onMount, tick } from "svelte";
  import { router } from "@inertiajs/svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import SourcesGrid from "./Partials/SourcesGrid.svelte";
  import SourcesTable from "./Partials/SourcesTable.svelte";
  import { activeFilterChips, clearFiltersQuery } from "./sourceFilters";
  import SourcesFilterToolbar from "./Partials/SourcesFilterToolbar.svelte";
  import SourceConnectionModal from "./Partials/SourceConnectionModal.svelte";
  import ProviderAuthorizationDeleteModal from "./Partials/ProviderAuthorizationDeleteModal.svelte";
  import { mergeBranding } from "@/lib/branding";
  import { apiClient, Endpoints } from "@/lib/api";
  import { cleanQuery, queryString, type QueryValue } from "@/lib/queryFilters";
  import {
    listViewStorageKey,
    readStoredListViewMode,
    storeListViewMode,
    type ListViewMode,
  } from "@/lib/listViewMode";
  import type { AppPageProps } from "@/types/app";
  import type { AuthPayload } from "@/types/auth";
  import type { OrganizationSummary } from "@/types/organization";
  import type {
    PaginationPayload,
    ProviderAuthorizationPayload,
    SourceFilterOptions,
    SourceIndexFilters,
    SourceListItem,
    SourceProviderPayload,
    SourceProviderInstancePayload,
    SourcesIndexStatsPayload,
    SourcesIndexPageProps,
    UserOAuthAccountPayload,
  } from "@/types/pages";

  const defaultPagination: PaginationPayload<SourceListItem> = {
    current_page: 1,
    from: 0,
    has_more_pages: false,
    has_previous_pages: false,
    last_page: 1,
    per_page: 18,
    to: 0,
    total: 0,
  };

  const defaultSourceStats: SourcesIndexStatsPayload = {
    active: 0,
    archived: 0,
    total: 0,
  };

  const defaultSourceFilters: SourceIndexFilters = {
    page: 1,
    per_page: 18,
    provider: [],
    search: "",
    status: [],
    sync_status: [],
    visibility: [],
  };

  const defaultSourceFilterOptions: SourceFilterOptions = {
    providers: [],
    sync_statuses: [],
  };

  function handleAuthorizationCreated(
    authorization: ProviderAuthorizationPayload,
  ): void {
    localProviderAuthorizations = [
      ...localProviderAuthorizations,
      authorization,
    ];
  }
  interface Props {
    auth?: AuthPayload;
    providers?: SourceProviderPayload[];
    provider_authorizations?: ProviderAuthorizationPayload[];
    provider_resources?: SourcesIndexPageProps["provider_resources"];
    source_provider_instances?: SourceProviderInstancePayload[];
    sources?: SourceListItem[];
    source_stats?: SourcesIndexStatsPayload;
    source_filter_options?: SourceFilterOptions;
    filters?: SourceIndexFilters;
    source_urls?: string[];
    pagination?: PaginationPayload<SourceListItem>;
    branding?: SourcesIndexPageProps["branding"];
    organization?: OrganizationSummary | null;
    initial_list_view_mode?: ListViewMode;
    can_manage_sources?: boolean;
    user_oauth_accounts?: UserOAuthAccountPayload[];
  }

  let {
    auth = {},
    providers = [],
    provider_authorizations = [],
    provider_resources = {},
    source_provider_instances = [],
    sources = [],
    source_stats = defaultSourceStats,
    source_filter_options = defaultSourceFilterOptions,
    filters = defaultSourceFilters,
    source_urls = [],
    pagination = defaultPagination,
    branding = {},
    organization = null,
    initial_list_view_mode = "grid",
    can_manage_sources = false,
    user_oauth_accounts = [],
  }: Props = $props();

  // Local state can change in modal callbacks before the next page payload arrives.
  let localProviderAuthorizations = $derived(provider_authorizations);
  let listViewMode: ListViewMode = $state("grid");
  let listViewModeInitialized = $state(false);

  let resolvedBranding = $derived(mergeBranding(branding ?? {}));
  type ModalView = "access" | "connect" | null;

  let activeModal: ModalView = $state(null);
  // Set when opened from a source page: preselects the provider and sends the user back there on close.
  let initialProvider = $state("");
  let initialProviderInstanceId = $state("");
  let returnTo = $state("");
  let isSubmitting = $state(false);
  let scrollLockApplied = false;
  let previousBodyOverflow = "";
  let previousBodyPaddingRight = "";
  let previousHtmlOverflow = "";

  function syncPageScrollLock(locked: boolean): void {
    if (typeof document === "undefined") {
      return;
    }

    if (locked && !scrollLockApplied) {
      const scrollbarWidth =
        window.innerWidth - document.documentElement.clientWidth;

      previousBodyOverflow = document.body.style.overflow;
      previousBodyPaddingRight = document.body.style.paddingRight;
      previousHtmlOverflow = document.documentElement.style.overflow;

      document.body.style.overflow = "hidden";
      document.documentElement.style.overflow = "hidden";

      if (scrollbarWidth > 0) {
        document.body.style.paddingRight = `${scrollbarWidth}px`;
      }

      scrollLockApplied = true;
      return;
    }

    if (!locked && scrollLockApplied) {
      document.body.style.overflow = previousBodyOverflow;
      document.body.style.paddingRight = previousBodyPaddingRight;
      document.documentElement.style.overflow = previousHtmlOverflow;
      scrollLockApplied = false;
    }
  }

  // Restore modal state from sessionStorage after OAuth returns
  onMount(() => {
    listViewMode = readStoredListViewMode(
      listViewStorageKey,
      initial_list_view_mode,
    );
    listViewModeInitialized = true;

    const url = new URL(window.location.href);
    if (url.searchParams.has("manage_access")) {
      const requestedReturn = url.searchParams.get("return_to") ?? "";
      // Mirrors the backend rule: one source page of this organization, optional query.
      const sourcePrefix = withOrganization("/sources/").replace(
        /[.*+?^${}()|[\]\\]/g,
        "\\$&",
      );
      if (
        new RegExp(
          `^${sourcePrefix}[0-9a-fA-F-]{36}(\\?[A-Za-z0-9_=&%.\\-]*)?$`,
        ).test(requestedReturn)
      ) {
        returnTo = requestedReturn;
      }
      initialProvider = url.searchParams.get("provider") ?? "";
      initialProviderInstanceId =
        url.searchParams.get("provider_instance_id") ?? "";
      for (const key of [
        "manage_access",
        "provider",
        "provider_instance_id",
        "return_to",
      ]) {
        url.searchParams.delete(key);
      }
      window.history.replaceState(
        window.history.state,
        "",
        `${url.pathname}${url.search}${url.hash}`,
      );
      if (resolvedCanManage) {
        activeModal = "access";
      }
    }

    const savedModalState = sessionStorage.getItem("sources_modal_state");
    if (savedModalState) {
      const state = JSON.parse(savedModalState);
      if (state.activeModal) {
        activeModal = state.activeModal;
        // Keep sessionStorage for modal to read its own state
      }
    }
  });

  interface DeleteModalData {
    authorization: ProviderAuthorizationPayload | null;
    check: {
      has_sources: boolean;
      source_count: number;
      sources: Array<{ id: string; name: string; has_packages: boolean }>;
      other_authorizations: Array<{
        id: string;
        provider: string;
        auth_strategy: string;
        masked_value: string;
      }>;
    } | null;
  }
  let deleteModal: DeleteModalData = $state({
    authorization: null,
    check: null,
  });

  function setListViewMode(mode: ListViewMode): void {
    listViewMode = mode;
    storeListViewMode(listViewStorageKey, mode);
  }

  // Element that opened a dialog; focus returns to it on close.
  let modalOpener: Element | null = null;
  let deleteOpener: Element | null = null;

  function openConnectModal(): void {
    modalOpener = document.activeElement;
    activeModal = "connect";
  }

  function openAccessModal(): void {
    modalOpener = document.activeElement;
    activeModal = "access";
  }

  // Real close (button, Escape, backdrop). Dialogs opened without a click fall back to the header button.
  async function dismissModal(): Promise<void> {
    if (!activeModal) {
      return; // Escape bubbles through two handlers
    }
    if (returnTo) {
      closeModal();
      router.visit(returnTo);
      return;
    }
    const fallbackId =
      activeModal === "access"
        ? "sources-manage-access-button"
        : "sources-add-source-button";
    closeModal();
    await tick();
    const target =
      modalOpener instanceof HTMLElement && modalOpener.isConnected
        ? modalOpener
        : document.getElementById(fallbackId);
    modalOpener = null;
    target?.focus();
  }

  function closeModal(clearSavedState = true): void {
    activeModal = null;
    // Clear saved modal state to prevent reopening on next visit
    if (clearSavedState && typeof window !== "undefined") {
      sessionStorage.removeItem("sources_modal_state");
      sessionStorage.removeItem("sources_connect_success");
    }
  }

  function clearFilters(): void {
    router.get(
      withOrganization("/sources"),
      clearFiltersQuery(filters.per_page),
      { preserveScroll: true },
    );
  }

  function withOrganization(path: string): string {
    if (!organization?.slug) {
      return path;
    }
    return `/organization/${organization.slug}${path}`;
  }

  function handlePageChange(page: number): void {
    router.get(withOrganization("/sources"), currentQuery({ page }), {
      preserveScroll: true,
    });
  }

  function currentQuery(
    overrides: Record<string, QueryValue> = {},
  ): Record<string, boolean | number | string | string[]> {
    return cleanQuery({
      search: filters.search,
      provider: filters.provider,
      visibility: filters.visibility,
      status: filters.status,
      sync_status: filters.sync_status,
      per_page: filters.per_page,
      page: filters.page,
      ...overrides,
    });
  }

  function sourcesReturnUrl(): string {
    return `${withOrganization("/sources")}${queryString(currentQuery())}`;
  }

  function sourceDetailsUrl(source: SourceListItem): string {
    return `${withOrganization(`/sources/${source.id}`)}?return_to=${encodeURIComponent(sourcesReturnUrl())}`;
  }

  async function openDeleteModal(
    authorization: ProviderAuthorizationPayload,
  ): Promise<void> {
    deleteOpener = document.activeElement;
    deleteModal = { authorization, check: null };

    const org = organization?.slug ?? "";
    const result = await apiClient.post<DeleteModalData["check"]>(
      Endpoints.sources.authorizationCheck(org, authorization.id),
    );

    if (result.ok) {
      deleteModal = { authorization, check: result.data };
    } else {
      deleteModal = {
        authorization,
        check: {
          has_sources: false,
          source_count: 0,
          sources: [],
          other_authorizations: [],
        },
      };
    }
  }

  async function closeDeleteModal(): Promise<void> {
    deleteModal = { authorization: null, check: null };
    await tick();
    if (deleteOpener instanceof HTMLElement && deleteOpener.isConnected) {
      deleteOpener.focus();
    }
    deleteOpener = null;
  }

  onDestroy(() => {
    syncPageScrollLock(false);
  });

  function submitDelete(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    const form = event.currentTarget;
    const formData = new FormData(form);

    if (
      deleteModal.check?.has_sources &&
      deleteModal.check.other_authorizations.length > 0 &&
      !formData.get("new_authorization_id")
    ) {
      return;
    }

    router.post(form.action, Object.fromEntries(formData), {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        closeDeleteModal();
      },
      onError: () => {
        return;
      },
    });
  }

  function submit(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
    modalState?: Record<string, unknown>,
  ): void {
    const form = event.currentTarget;
    const url = form.action;

    // Save modal state before submitting for source connection
    if (url.includes("/sources/connect") && modalState) {
      if (typeof window !== "undefined") {
        sessionStorage.setItem(
          "sources_modal_state",
          JSON.stringify(modalState),
        );
        sessionStorage.setItem("sources_connect_success", "true");
      }
    }

    isSubmitting = true;

    // Convert FormData to object, properly handling arrays
    const formData = new FormData(form);
    const data: Record<string, string | string[] | Record<string, string>> = {};
    formData.forEach((value, key) => {
      if (key.endsWith("[]")) {
        // Array field
        const arrayKey = key.slice(0, -2);
        if (!data[arrayKey]) {
          data[arrayKey] = [];
        }
        (data[arrayKey] as string[]).push(value.toString());
      } else if (key.includes("[") && key.endsWith("]")) {
        const match = key.match(/^([^[]+)\[(.+)]$/);
        if (!match) {
          data[key] = value.toString();
          return;
        }

        const [, mapKey, itemKey] = match;
        if (!data[mapKey]) {
          data[mapKey] = {};
        }
        (data[mapKey] as Record<string, string>)[itemKey] = value.toString();
      } else {
        data[key] = value.toString();
      }
    });

    router.post(form.action, data as Record<string, string>, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        isSubmitting = false;
        const url = form.action;
        if (url.includes("/authorizations")) {
          form.reset();
        } else if (url.includes("/sources/connect")) {
          closeModal(false);
          setTimeout(() => (activeModal = "connect"), 0);
        }
      },
      onError: () => {
        isSubmitting = false;
        if (
          form.action.includes("/sources/connect") &&
          typeof window !== "undefined"
        ) {
          sessionStorage.removeItem("sources_connect_success");
        }
      },
    });
  }
  $effect(() => {
    if (!listViewModeInitialized) {
      listViewMode = initial_list_view_mode;
    }
  });
  let pageProps = $derived(
    ((usePage<AppPageProps>() as { props?: AppPageProps } | undefined)?.props ??
      {}) as AppPageProps,
  );
  let currentMembership = $derived(pageProps.current_membership ?? null);
  let isOrganizationOwner = $derived(currentMembership?.role === "owner");
  let resolvedCanManage = $derived(can_manage_sources || isOrganizationOwner);
  let connectableProviders = $derived(
    providers.filter(
      (provider) => provider.capabilities?.supports_synchronization,
    ),
  );
  let hasSources = $derived(source_stats.total > 0);
  let activeFilterCount = $derived(
    (filters.search !== "" ? 1 : 0) +
      (filters.provider.length > 0 ? 1 : 0) +
      (filters.visibility.length > 0 ? 1 : 0) +
      (filters.status.length > 0 ? 1 : 0) +
      (filters.sync_status.length > 0 ? 1 : 0),
  );
  let showSourceToolbar = $derived(hasSources || activeFilterCount > 0);
  let catalogEmpty = $derived(!hasSources && activeFilterCount === 0);
  let chips = $derived(activeFilterChips(filters, source_filter_options));
  // Get all existing source URLs to check against.
  let existingSourceUrls = $derived(
    new Set(
      source_urls.length > 0
        ? source_urls
        : sources.map((source) => source.url),
    ),
  );
  $effect(() => {
    syncPageScrollLock(
      activeModal !== null || deleteModal.authorization !== null,
    );
  });
</script>

<svelte:head>
  <title>Sources</title>
</svelte:head>

<AppShell title="Sources" {auth}>
  <section class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="ui-title text-2xl font-semibold">Sources</h2>
      {#if resolvedCanManage}
        <div class="flex flex-wrap gap-3">
          <Button
            id="sources-add-source-button"
            className="rounded-full px-5"
            type="button"
            variant="primary"
            onclick={() => openConnectModal()}
            disabled={connectableProviders.length === 0}
          >
            Add source
          </Button>
          <Button
            id="sources-manage-access-button"
            className="rounded-full px-5"
            type="button"
            variant="secondary"
            onclick={() => openAccessModal()}
          >
            Manage provider access
          </Button>
        </div>
      {/if}
    </div>

    {#if showSourceToolbar}
      <SourcesFilterToolbar
        {filters}
        filterOptions={source_filter_options}
        mode={listViewMode}
        onModeChange={setListViewMode}
        {withOrganization}
      />
    {/if}

    {#if chips.length > 0 || (activeFilterCount > 0 && sources.length > 0)}
      <div class="flex flex-wrap items-center gap-2">
        {#each chips as chip (chip)}
          <Badge tone="neutral">{chip}</Badge>
        {/each}
        {#if activeFilterCount > 0 && sources.length > 0}
          <Button
            className="rounded-full px-3 py-1 text-xs"
            type="button"
            variant="secondary"
            onclick={clearFilters}
          >
            Clear filters
          </Button>
        {/if}
      </div>
    {/if}

    {#if pagination.last_page > 1}
      <Pagination
        currentPage={pagination.current_page}
        lastPage={pagination.last_page}
        hasMorePages={pagination.has_more_pages}
        hasPreviousPages={pagination.has_previous_pages}
        from={pagination.from}
        to={pagination.to}
        total={pagination.total}
        perPage={pagination.per_page}
        onPageChange={handlePageChange}
        showPerPageSelector={false}
      />
    {:else if pagination.total > 0}
      <p class="text-sm ui-muted">
        {pagination.total}
        {pagination.total === 1 ? "source" : "sources"}
      </p>
    {/if}

    {#if sources.length === 0}
      <Card className="rounded-[1.75rem] p-6">
        {#if catalogEmpty}
          <p class="ui-title font-medium">No sources yet</p>
          <p class="mt-2 text-sm ui-muted">
            Connect a repository to start syncing packages.
          </p>
          {#if resolvedCanManage}
            <Button
              className="mt-4 rounded-full px-5"
              type="button"
              variant="primary"
              onclick={() => openConnectModal()}
              disabled={connectableProviders.length === 0}
            >
              Add source
            </Button>
          {/if}
        {:else}
          <p class="ui-title font-medium">No sources match your search</p>
          <Button
            className="mt-4"
            type="button"
            variant="secondary"
            onclick={clearFilters}
          >
            Clear filters
          </Button>
        {/if}
      </Card>
    {:else if listViewMode === "grid"}
      <SourcesGrid {sources} {sourceDetailsUrl} />
    {:else}
      <SourcesTable {sources} {sourceDetailsUrl} />
    {/if}

    {#if pagination.last_page > 1}
      <Pagination
        currentPage={pagination.current_page}
        lastPage={pagination.last_page}
        hasMorePages={pagination.has_more_pages}
        hasPreviousPages={pagination.has_previous_pages}
        from={pagination.from}
        to={pagination.to}
        total={pagination.total}
        perPage={pagination.per_page}
        onPageChange={handlePageChange}
        showPerPageSelector={false}
      />
    {/if}
  </section>

  {#if activeModal}
    <SourceConnectionModal
      {activeModal}
      {providers}
      providerAuthorizationsAll={localProviderAuthorizations}
      providerResourcesAll={provider_resources ?? {}}
      sourceProviderInstances={source_provider_instances}
      {existingSourceUrls}
      {organization}
      {resolvedBranding}
      {isSubmitting}
      {user_oauth_accounts}
      {initialProvider}
      {initialProviderInstanceId}
      {returnTo}
      onClose={dismissModal}
      onConnectSubmit={submit}
      onAccessSubmit={submit}
      onDeleteAuthorization={openDeleteModal}
      onAuthorizationCreated={handleAuthorizationCreated}
      {withOrganization}
    />
  {/if}

  {#if deleteModal.authorization}
    <ProviderAuthorizationDeleteModal
      authorization={deleteModal.authorization}
      check={deleteModal.check}
      onClose={closeDeleteModal}
      onSubmit={submitDelete}
      {withOrganization}
    />
  {/if}
</AppShell>
