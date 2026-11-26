<script lang="ts">
  import { tick } from "svelte";
  import { apiClient, Endpoints } from "@/lib/api";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import CopyButton from "@/Components/ui/CopyButton.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import { hasFieldErrors } from "@/lib/validationErrors";
  import { toast } from "@/lib/toast";
  import type {
    SourceProviderInstanceDependenciesPayload,
    SourceProviderInstancePayload,
    ValidationErrors,
  } from "@/types/pages";

  interface Props {
    organizationSlug: string;
    appBaseUrl: string;
    providers?: { key: string; label: string }[];
  }

  let { organizationSlug, appBaseUrl, providers = [] }: Props = $props();

  const types = $derived(
    providers.length > 0
      ? providers
      : [{ key: "gitlab_self_hosted", label: "GitLab Self-Hosted" }],
  );
  let selectedType = $state("");
  const providerType = $derived(selectedType || types[0].key);
  // With several types the form stays hidden until one is chosen.
  const typeChosen = $derived(selectedType !== "" || types.length === 1);
  const redirectUri = $derived(
    `${appBaseUrl.replace(/\/+$/, "")}/auth/oauth/${providerType}/source-callback`,
  );
  function typeLabel(key: string): string {
    return types.find((type) => type.key === key)?.label ?? key;
  }
  // "GitLab Self-Hosted" -> "GitLab" for sentences
  function typeNoun(key: string): string {
    return typeLabel(key).replace(/ Self-Hosted$/, "");
  }

  type ProviderInstanceResponse = {
    instance: SourceProviderInstancePayload;
  };

  type ProviderInstancesResponse = {
    instances: SourceProviderInstancePayload[];
  };

  type ProviderInstanceDependenciesResponse =
    SourceProviderInstanceDependenciesPayload;

  type ProviderInstanceCleanupResponse = {
    deleted: true;
    deleted_inactive_authorizations_count: number;
    deleted_source_oauth_accounts_count: number;
  };

  let providerInstances: SourceProviderInstancePayload[] = $state([]);
  let providerInstancesLoading = $state(false);
  let providerInstanceSaving = $state(false);
  let providerInstanceDeleting = $state(false);
  let providerInstancePendingDelete: SourceProviderInstancePayload | null =
    $state(null);
  let providerInstanceDependencies =
    $state<SourceProviderInstanceDependenciesPayload | null>(null);
  let providerInstanceFormElement: HTMLFormElement | null = $state(null);
  let providerInstanceErrors: ValidationErrors = $state({});
  let editingProviderInstanceId = $state("");
  let providerInstanceForm = $state({
    label: "",
    baseUrl: "",
    clientId: "",
    clientSecret: "",
    authMode: "oauth2_and_token",
  });
  const usesOAuth = $derived(providerInstanceForm.authMode !== "token");
  function authModeLabel(
    authMode: SourceProviderInstancePayload["auth_mode"],
  ): string {
    switch (authMode) {
      case "oauth2":
        return "OAuth";
      case "token":
        return "Manual token";
      case "oauth2_and_token":
        return "OAuth + token";
    }
  }
  function resetProviderInstanceForm(): void {
    providerInstanceErrors = {};
    editingProviderInstanceId = "";
    selectedType = "";
    providerInstanceForm = {
      label: "",
      baseUrl: "",
      clientId: "",
      clientSecret: "",
      authMode: "oauth2_and_token",
    };
  }

  async function editProviderInstance(
    instance: SourceProviderInstancePayload,
  ): Promise<void> {
    providerInstanceErrors = {};
    editingProviderInstanceId = instance.id;
    selectedType = instance.provider_type;
    providerInstanceForm = {
      label: instance.label,
      baseUrl: instance.base_url,
      clientId: instance.client_id ?? "",
      clientSecret: "",
      authMode: instance.auth_mode,
    };

    await tick();
    providerInstanceFormElement?.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  }

  async function loadProviderInstances(): Promise<void> {
    const org = organizationSlug;
    if (!org) {
      return;
    }

    providerInstancesLoading = true;
    const result = await apiClient.get<ProviderInstancesResponse>(
      Endpoints.sources.providerInstances(org),
    );
    providerInstancesLoading = false;

    if (result.ok) {
      providerInstances = result.data.instances;
      return;
    }

    toast.error(result.error);
  }

  async function saveProviderInstance(): Promise<void> {
    const org = organizationSlug;
    if (!org || providerInstanceSaving) {
      return;
    }

    providerInstanceSaving = true;
    providerInstanceErrors = {};
    const payload = {
      providerType,
      label: providerInstanceForm.label,
      baseUrl: providerInstanceForm.baseUrl,
      // Hidden OAuth fields must not send stale values in token mode.
      // On edit the stored client ID is kept; only a new token instance sends none.
      clientId:
        usesOAuth || editingProviderInstanceId
          ? providerInstanceForm.clientId
          : "",
      clientSecret: usesOAuth ? providerInstanceForm.clientSecret : "",
      authMode: providerInstanceForm.authMode,
    };
    const result = editingProviderInstanceId
      ? await apiClient.patch<ProviderInstanceResponse>(
          Endpoints.sources.providerInstance(org, editingProviderInstanceId),
          payload,
        )
      : await apiClient.post<ProviderInstanceResponse>(
          Endpoints.sources.providerInstances(org),
          payload,
        );
    providerInstanceSaving = false;

    if (result.ok) {
      const saved = result.data.instance;
      providerInstances = editingProviderInstanceId
        ? providerInstances.map((instance) =>
            instance.id === saved.id ? saved : instance,
          )
        : [...providerInstances, saved];
      resetProviderInstanceForm();
      toast.success(`${typeNoun(saved.provider_type)} instance saved.`);
      return;
    }

    const messages = (result.data as { messages?: ValidationErrors } | null)
      ?.messages;
    providerInstanceErrors = messages ?? {};
    toast.error(result.error);
  }

  async function setProviderInstanceStatus(
    instance: SourceProviderInstancePayload,
    active: boolean,
  ): Promise<void> {
    const org = organizationSlug;
    if (!org) {
      return;
    }

    const result = await apiClient.post<ProviderInstanceResponse>(
      active
        ? Endpoints.sources.providerInstanceEnable(org, instance.id)
        : Endpoints.sources.providerInstanceDisable(org, instance.id),
    );

    if (result.ok) {
      providerInstances = providerInstances.map((item) =>
        item.id === instance.id ? result.data.instance : item,
      );
      return;
    }

    toast.error(result.error);
  }

  async function deleteProviderInstance(
    instance: SourceProviderInstancePayload,
  ): Promise<void> {
    const org = organizationSlug;
    if (!org || providerInstanceDeleting) {
      return;
    }

    const result = await apiClient.get<ProviderInstanceDependenciesResponse>(
      Endpoints.sources.providerInstanceDependencies(org, instance.id),
    );

    if (!result.ok) {
      toast.error(result.error);
      return;
    }

    providerInstancePendingDelete = instance;
    providerInstanceDependencies = result.data;
  }

  const pendingNoun = $derived(
    typeNoun(
      (providerInstancePendingDelete as SourceProviderInstancePayload | null)
        ?.provider_type ?? providerType,
    ),
  );

  function canDeleteProviderInstanceNormally(): boolean {
    return (
      providerInstanceDependencies?.source_count === 0 &&
      providerInstanceDependencies.authorization_count === 0
    );
  }

  function canDeleteProviderInstanceWithInactiveAuthorizations(): boolean {
    return (
      providerInstanceDependencies?.source_count === 0 &&
      providerInstanceDependencies.active_authorization_count === 0 &&
      (providerInstanceDependencies?.inactive_authorization_count ?? 0) > 0
    );
  }

  function dependencyConflict(
    data: unknown,
  ): SourceProviderInstanceDependenciesPayload | null {
    if (!data || typeof data !== "object") {
      return null;
    }

    const dependencies = (data as { dependencies?: unknown }).dependencies;
    if (
      !dependencies ||
      typeof dependencies !== "object" ||
      !Array.isArray((dependencies as { sources?: unknown }).sources) ||
      !Array.isArray(
        (dependencies as { authorizations?: unknown }).authorizations,
      )
    ) {
      return null;
    }

    return dependencies as SourceProviderInstanceDependenciesPayload;
  }

  async function confirmDeleteProviderInstance(): Promise<void> {
    const instance = providerInstancePendingDelete;
    const org = organizationSlug;
    if (!org || !instance || providerInstanceDeleting) {
      return;
    }

    providerInstanceDeleting = true;
    const result = canDeleteProviderInstanceNormally()
      ? await apiClient.delete<{ deleted: boolean }>(
          Endpoints.sources.providerInstance(org, instance.id),
        )
      : canDeleteProviderInstanceWithInactiveAuthorizations()
        ? await apiClient.post<ProviderInstanceCleanupResponse>(
            Endpoints.sources.providerInstanceDeleteInactiveAuthorizations(
              org,
              instance.id,
            ),
          )
        : null;
    providerInstanceDeleting = false;

    if (result?.ok) {
      providerInstances = providerInstances.filter(
        (item) => item.id !== instance.id,
      );
      if (editingProviderInstanceId === instance.id) {
        resetProviderInstanceForm();
      }
      providerInstancePendingDelete = null;
      providerInstanceDependencies = null;
      if ("deleted_inactive_authorizations_count" in result.data) {
        const cleanupResult = result.data as ProviderInstanceCleanupResponse;
        toast.success(
          `Deleted ${cleanupResult.deleted_inactive_authorizations_count} inactive authorization(s), ${cleanupResult.deleted_source_oauth_accounts_count} OAuth account(s), and the ${typeNoun(instance.provider_type)} instance.`,
        );
      } else {
        toast.success(`${typeNoun(instance.provider_type)} instance deleted.`);
      }
      return;
    }

    if (result) {
      const dependencies =
        result.status === 409 ? dependencyConflict(result.data) : null;
      if (dependencies) {
        providerInstanceDependencies = dependencies;
      }
      toast.error(result.error);
    }
  }
  // The list shows every type; the selected type only affects the form.
  $effect(() => {
    void loadProviderInstances();
  });
</script>

<div class="grid gap-6">
  <Card className="rounded-[1.75rem] p-6">
    <SectionHeading
      eyebrow="Source provider instances"
      title={types.length > 1
        ? "Self-hosted instances"
        : `${typeNoun(providerType)} self-hosted`}
      description="Register organization instances for OAuth and token-based source access."
      className="max-w-xl"
    />

    <form
      bind:this={providerInstanceFormElement}
      class="mt-6 grid scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)] gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveProviderInstance();
      }}
    >
      {#if types.length > 1}
        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title">Instance type</span>
          <Select
            bind:value={selectedType}
            name="provider_type"
            disabled={editingProviderInstanceId !== ""}
          >
            <option value="" disabled>Select instance type</option>
            {#each types as type (type.key)}
              <option value={type.key}>{type.label}</option>
            {/each}
          </Select>
        </label>
      {/if}

      {#if typeChosen}
        <div class="grid gap-4 md:grid-cols-2">
          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">Label</span>
            <Input
              name="label"
              bind:value={providerInstanceForm.label}
              placeholder={`Company ${typeNoun(providerType)}`}
              required
            />
          </label>
          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">Base URL</span>
            <Input
              name="base_url"
              type="url"
              bind:value={providerInstanceForm.baseUrl}
              placeholder={`https://${providerType === "forgejo_self_hosted" ? "forgejo" : "gitlab"}.company.com`}
              invalid={hasFieldErrors(providerInstanceErrors, "baseUrl")}
              required
            />
            <FieldErrors errors={providerInstanceErrors} field="baseUrl" />
          </label>
        </div>

        {#if usesOAuth}
          <div class="grid gap-4 md:grid-cols-2">
            <label class="grid gap-2">
              <span class="text-sm font-medium ui-title">OAuth client ID</span>
              <Input
                name="client_id"
                bind:value={providerInstanceForm.clientId}
                autocomplete="off"
                placeholder="Application ID"
              />
            </label>
            <label class="grid gap-2">
              <span class="text-sm font-medium ui-title">
                OAuth client secret
              </span>
              <Input
                name="client_secret"
                type="password"
                bind:value={providerInstanceForm.clientSecret}
                autocomplete="off"
                placeholder={editingProviderInstanceId
                  ? "Leave blank to keep current secret"
                  : "Application secret"}
              />
            </label>
          </div>
        {/if}

        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title">Authorization mode</span>
          <Select bind:value={providerInstanceForm.authMode} name="auth_mode">
            <option value="oauth2_and_token">OAuth and manual token</option>
            <option value="oauth2">OAuth only</option>
            <option value="token">Manual token only</option>
          </Select>
        </label>

        {#if usesOAuth}
          <div class="grid gap-2">
            <span class="text-sm font-medium ui-title">
              OAuth redirect URI
            </span>
            <div
              class="ui-code flex min-w-0 items-center gap-3 rounded-2xl px-4 py-3"
            >
              <code class="min-w-0 flex-1 break-all text-xs">
                {redirectUri}
              </code>
              <CopyButton value={redirectUri} label="Copy OAuth redirect URI" />
            </div>
            <p class="text-xs ui-muted">
              Add this callback URL to the {typeNoun(providerType)} OAuth application
              for every self-hosted instance.
            </p>
          </div>
        {/if}

        <div class="flex flex-wrap gap-3">
          <Button
            className="rounded-full px-5"
            type="submit"
            variant="primary"
            disabled={providerInstanceSaving}
          >
            {providerInstanceSaving
              ? "Saving..."
              : editingProviderInstanceId
                ? "Update instance"
                : "Add instance"}
          </Button>
          {#if editingProviderInstanceId}
            <Button
              className="rounded-full px-5"
              type="button"
              variant="secondary"
              onclick={resetProviderInstanceForm}
            >
              Cancel edit
            </Button>
          {/if}
        </div>
      {/if}
    </form>

    <div class="mt-6 grid gap-3">
      {#if providerInstancesLoading}
        <p class="text-sm ui-muted">Loading instances...</p>
      {:else if providerInstances.length === 0}
        <p class="text-sm ui-muted">No instances configured yet.</p>
      {:else}
        {#each providerInstances as instance (instance.id)}
          <InsetCard className="p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium ui-title">{instance.label}</p>
                  <span
                    class={`rounded-full px-2.5 py-1 text-[11px] font-medium ${
                      instance.status === "active"
                        ? "ui-badge-accent"
                        : "ui-badge-muted"
                    }`}
                  >
                    {instance.status}
                  </span>
                  <span
                    class="ui-badge-muted rounded-full px-2.5 py-1 text-[11px] font-medium"
                  >
                    {authModeLabel(instance.auth_mode)}
                  </span>
                  <span
                    class="ui-badge-muted rounded-full px-2.5 py-1 text-[11px] font-medium"
                  >
                    {typeLabel(instance.provider_type)}
                  </span>
                </div>
                <p class="mt-1 break-all text-xs ui-muted">
                  {instance.base_url}
                </p>
              </div>
              <div
                class="grid w-full grid-cols-3 gap-2 sm:w-auto sm:flex sm:flex-wrap"
              >
                <Button
                  className="w-full rounded-full px-2 py-2 text-sm sm:w-auto sm:px-4"
                  type="button"
                  variant="primary"
                  onclick={() => editProviderInstance(instance)}
                >
                  Edit
                </Button>
                <Button
                  className="w-full rounded-full px-2 py-2 text-sm sm:w-auto sm:px-3"
                  type="button"
                  variant="secondary"
                  onclick={() =>
                    setProviderInstanceStatus(
                      instance,
                      instance.status !== "active",
                    )}
                >
                  {instance.status === "active" ? "Disable" : "Enable"}
                </Button>
                <Button
                  className="w-full rounded-full px-2 py-2 text-sm sm:w-auto sm:px-4"
                  type="button"
                  variant="danger"
                  onclick={() => deleteProviderInstance(instance)}
                >
                  Delete
                </Button>
              </div>
            </div>
          </InsetCard>
        {/each}
      {/if}
    </div>
  </Card>

  <ConfirmationModal
    open={providerInstancePendingDelete !== null}
    eyebrow="Source provider instance"
    title={canDeleteProviderInstanceNormally()
      ? `Delete ${pendingNoun} instance?`
      : canDeleteProviderInstanceWithInactiveAuthorizations()
        ? `Delete inactive authorizations and ${pendingNoun} instance?`
        : `${pendingNoun} instance has dependencies`}
    description={canDeleteProviderInstanceNormally()
      ? `This removes the organization-level ${pendingNoun} self-hosted provider instance. Orphan OAuth accounts for this instance are removed with it.`
      : canDeleteProviderInstanceWithInactiveAuthorizations()
        ? `This permanently removes the listed inactive authorizations, orphan OAuth accounts for this instance, and then the ${pendingNoun} instance.`
        : "Sources and active authorizations cannot be removed by this action. Resolve or reassign them first."}
    confirmLabel={canDeleteProviderInstanceWithInactiveAuthorizations()
      ? `Delete ${providerInstanceDependencies?.inactive_authorization_count ?? 0} inactive authorization(s) and instance`
      : "Delete instance"}
    cancelLabel="Keep instance"
    confirming={providerInstanceDeleting}
    confirmDisabled={!canDeleteProviderInstanceNormally() &&
      !canDeleteProviderInstanceWithInactiveAuthorizations()}
    onCancel={() => {
      providerInstancePendingDelete = null;
      providerInstanceDependencies = null;
    }}
    onConfirm={confirmDeleteProviderInstance}
  >
    {#if providerInstancePendingDelete}
      <div class="ui-inset grid gap-2 rounded-xl border p-4">
        <p class="font-medium ui-title">
          {providerInstancePendingDelete.label}
        </p>
        <p class="break-all text-xs ui-muted">
          {providerInstancePendingDelete.base_url}
        </p>
        <div class="flex flex-wrap gap-2 pt-1">
          <span
            class="ui-badge-muted rounded-full px-2.5 py-1 text-[11px] font-medium"
          >
            {providerInstancePendingDelete.status}
          </span>
          <span
            class="ui-badge-muted rounded-full px-2.5 py-1 text-[11px] font-medium"
          >
            {authModeLabel(providerInstancePendingDelete.auth_mode)}
          </span>
        </div>
      </div>

      {#if providerInstanceDependencies}
        {#if providerInstanceDependencies.sources.length > 0}
          <div class="grid gap-2">
            <p class="text-sm font-medium ui-title">
              Sources ({providerInstanceDependencies.source_count})
            </p>
            {#each providerInstanceDependencies.sources as source (source.id)}
              <div class="ui-inset grid gap-1 rounded-xl border p-3 text-sm">
                <p class="font-medium ui-title">{source.name}</p>
                <p class="break-all text-xs ui-muted">{source.url}</p>
                <p class="text-xs ui-muted">
                  {source.id} · {source.provider} · {source.status}
                </p>
              </div>
            {/each}
          </div>
        {/if}

        {#if providerInstanceDependencies.authorizations.some((authorization) => authorization.status === "active")}
          <div class="grid gap-2">
            <p class="text-sm font-medium ui-title">
              Active authorizations ({providerInstanceDependencies.active_authorization_count})
            </p>
            {#each providerInstanceDependencies.authorizations.filter((authorization) => authorization.status === "active") as authorization (authorization.id)}
              <div class="ui-inset grid gap-1 rounded-xl border p-3 text-sm">
                <p class="font-medium ui-title">
                  {authorization.account_name || authorization.auth_strategy}
                </p>
                <p class="text-xs ui-muted">
                  {authorization.id} · {authorization.provider} ·
                  {authorization.auth_strategy} · {authorization.status}
                </p>
              </div>
            {/each}
          </div>
        {/if}

        {#if providerInstanceDependencies.authorizations.some((authorization) => authorization.status === "inactive")}
          <div class="grid gap-2">
            <p class="text-sm font-medium ui-title">
              Inactive authorizations ({providerInstanceDependencies.inactive_authorization_count})
            </p>
            {#each providerInstanceDependencies.authorizations.filter((authorization) => authorization.status === "inactive") as authorization (authorization.id)}
              <div class="ui-inset grid gap-1 rounded-xl border p-3 text-sm">
                <p class="font-medium ui-title">
                  {authorization.account_name || authorization.auth_strategy}
                </p>
                <p class="text-xs ui-muted">
                  {authorization.id} · {authorization.provider} ·
                  {authorization.auth_strategy} · Reauthorization required
                </p>
              </div>
            {/each}
          </div>
        {/if}
      {/if}
    {/if}
  </ConfirmationModal>
</div>
