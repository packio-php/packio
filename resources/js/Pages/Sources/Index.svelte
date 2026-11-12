<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import { onDestroy, onMount } from "svelte";
  import { router } from "@inertiajs/svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import SourceListSection from "./Partials/SourceListSection.svelte";
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

  function openConnectModal(): void {
    activeModal = "connect";
  }

  function openAccessModal(): void {
    activeModal = "access";
  }

  function closeModal(clearSavedState = true): void {
    activeModal = null;
    // Clear saved modal state to prevent reopening on next visit
    if (clearSavedState && typeof window !== "undefined") {
      sessionStorage.removeItem("sources_modal_state");
      sessionStorage.removeItem("sources_connect_success");
    }
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

  function closeDeleteModal(): void {
    deleteModal = { authorization: null, check: null };
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
  let activeSourceTotal = $derived(source_stats.active);
  let archivedSourceTotal = $derived(source_stats.archived);
  let hasSources = $derived(source_stats.total > 0);
  let sourceResultTotal = $derived(
    pagination.total > 0 || sources.length === 0
      ? pagination.total
      : sources.length,
  );
  let activeFilterCount = $derived(
    (filters.search !== "" ? 1 : 0) +
      (filters.provider.length > 0 ? 1 : 0) +
      (filters.visibility.length > 0 ? 1 : 0) +
      (filters.status.length > 0 ? 1 : 0) +
      (filters.sync_status.length > 0 ? 1 : 0),
  );
  let showSourceToolbar = $derived(hasSources || activeFilterCount > 0);
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
    <Card className="rounded-[1.75rem] p-6 md:p-8">
      <SectionHeading
        eyebrow={`${organization?.slug ?? "organization"} sources`}
        title={`${organization?.name || resolvedBranding.name} sources`}
        description={`Connect repositories from GitHub or GitLab into ${resolvedBranding.name}. Provider access is reused automatically, so most teams only authorize once and then keep adding sources as needed.`}
        className="max-w-3xl"
      />
      <div
        class="mt-6 grid gap-4 lg:grid-cols-[minmax(18rem,0.72fr)_minmax(0,1.28fr)]"
      >
        <InsetCard className="p-5">
          <p class="font-medium ui-title">Add and manage sources</p>
          <p class="mt-2 text-sm leading-6 ui-muted">
            Add a new source through a guided flow. Existing provider access is
            reused automatically, and authorization is requested only when the
            selected provider still needs it.
          </p>
          {#if resolvedCanManage}
            <div class="mt-4 flex flex-wrap gap-3">
              <Button
                className="rounded-full px-5"
                type="button"
                variant="primary"
                onclick={() => openConnectModal()}
                disabled={connectableProviders.length === 0}
              >
                Add source
              </Button>
              <Button
                className="rounded-full px-5"
                type="button"
                variant="secondary"
                onclick={() => openAccessModal()}
              >
                Manage provider access
              </Button>
            </div>
          {:else}
            <p class="mt-4 text-sm ui-muted">
              Owner membership is required to connect and synchronize
              organization sources.
            </p>
          {/if}
          <div class="mt-4 flex flex-wrap gap-2 text-xs">
            {#each providers as provider (provider.key)}
              <span
                class={`rounded-full px-3 py-1 font-medium ${
                  provider.capabilities?.supports_synchronization
                    ? "ui-badge-accent"
                    : "ui-badge-muted"
                }`}
              >
                {provider.name}
                {provider.capabilities?.supports_synchronization
                  ? " ready"
                  : " coming soon"}
              </span>
            {/each}
          </div>
        </InsetCard>
        <div class="grid gap-4 sm:grid-cols-3 self-start">
          <InsetCard className="p-4">
            <p class="ui-eyebrow">Active</p>
            <p class="ui-title mt-2 text-3xl font-semibold">
              {activeSourceTotal}
            </p>
            <p class="mt-2 text-xs ui-muted">
              connected sources synchronizing packages
            </p>
          </InsetCard>
          <InsetCard className="p-4">
            <p class="ui-eyebrow">Archived</p>
            <p class="ui-title mt-2 text-3xl font-semibold">
              {archivedSourceTotal}
            </p>
            <p class="mt-2 text-xs ui-muted">
              inactive sources kept for recovery
            </p>
          </InsetCard>
          <InsetCard className="p-4">
            <p class="ui-eyebrow">Providers</p>
            <p class="ui-title mt-2 text-3xl font-semibold">
              {connectableProviders.length}
            </p>
            <p class="mt-2 text-xs ui-muted">
              ready for repository synchronization
            </p>
          </InsetCard>
        </div>
      </div>
    </Card>
  </section>

  <section class="grid gap-6">
    {#if showSourceToolbar}
      <SourcesFilterToolbar
        {filters}
        filterOptions={source_filter_options}
        mode={listViewMode}
        onModeChange={setListViewMode}
        {withOrganization}
      />
    {/if}

    {#if pagination.total > 0}
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

    <SourceListSection
      title="Sources"
      {sources}
      total={sourceResultTotal}
      emptyAllMessage={hasSources
        ? "No sources match current filters."
        : `No ${resolvedBranding.name} sources connected yet.`}
      emptyPageMessage="No sources on this page."
      mode={listViewMode}
      {sourceDetailsUrl}
    />
  </section>

  {#if pagination.total > 0}
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
      onClose={closeModal}
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
