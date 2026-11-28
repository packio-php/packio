<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import { router } from "@inertiajs/svelte";

  import AppShell from "@/Layouts/AppShell.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import BackButton from "@/Components/ui/BackButton.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import CopyButton from "@/Components/ui/CopyButton.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import Link from "@/Components/core/Link.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { mergeBranding } from "@/lib/branding";
  import { formatDate } from "@/lib/date";
  import type { AppPageProps } from "@/types/app";
  import { auditEventLabel } from "./auditEventLabel";
  import {
    sourceStateLabel,
    sourceStateTone,
    syncSummary,
  } from "./sourceStatus";
  import type { AuthPayload } from "@/types/auth";
  import type { OrganizationSummary } from "@/types/organization";
  import type {
    AuditEntryPayload,
    SourceDetailsPayload,
    SourcePackagePayload,
    SourceStatsPayload,
    SourceWebhookEventPayload,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    source: SourceDetailsPayload;
    stats?: SourceStatsPayload;
    packages?: SourcePackagePayload[];
    webhook_events?: SourceWebhookEventPayload[];
    audit?: AuditEntryPayload[];
    branding?: Partial<import("@/types/branding").Branding>;
    organization?: OrganizationSummary | null;
    can_manage_sources?: boolean;
  }

  let {
    auth = {},
    source,
    stats = {},
    packages = [],
    webhook_events = [],
    audit = [],
    branding = {},
    organization = null,
    can_manage_sources = false,
  }: Props = $props();

  let resolvedBranding = $derived(mergeBranding(branding ?? {}));
  let sourceDeleteConfirmOpen = $state(false);
  let sourceDeleting = $state(false);
  let pendingAction = $state<"hide" | "archive" | null>(null);
  let actionSubmitting = $state(false);
  let editingSettings = $state(false);
  // Copied on Edit so refreshes of `source` (poll, failed save) do not overwrite typed values.
  let draft = $state({ visibility: "public", active: true, keep: 0 });

  function startEditing(): void {
    draft = {
      visibility: source.visibility,
      active: source.active,
      keep: source.keep_last_releases,
    };
    editingSettings = true;
  }
  let rotateConfirmOpen = $state(false);
  let rotating = $state(false);
  let lastDelivery = $derived(webhook_events[0] ?? null);
  let supportsWebhookProcessing = $derived(source.provider !== "generic_git");
  type ActivityTab = "deliveries" | "audit";
  // Local state, so it survives the polling reload; generic Git has no deliveries tab.
  let chosenTab = $state<ActivityTab>("deliveries");
  let activeTab = $derived<ActivityTab>(
    supportsWebhookProcessing ? chosenTab : "audit",
  );
  const activityTabs: ActivityTab[] = ["deliveries", "audit"];
  let tabButtons = $state<Record<ActivityTab, HTMLButtonElement | undefined>>({
    deliveries: undefined,
    audit: undefined,
  });

  function onTabKeydown(event: KeyboardEvent): void {
    const index = activityTabs.indexOf(activeTab);
    const next =
      event.key === "ArrowRight"
        ? (index + 1) % activityTabs.length
        : event.key === "ArrowLeft"
          ? (index - 1 + activityTabs.length) % activityTabs.length
          : event.key === "Home"
            ? 0
            : event.key === "End"
              ? activityTabs.length - 1
              : -1;

    if (next < 0) {
      return;
    }

    event.preventDefault();
    chosenTab = activityTabs[next];
    tabButtons[chosenTab]?.focus();
  }
  let sync = $derived(syncSummary(source));
  let syncing = $derived(source.sync_job_status != null);
  // Mirrors SourceResyncHandler: only active sources can be re-synced; archiving deactivates.
  let resyncUnavailableReason = $derived(
    source.archived
      ? "Source is archived. Restore it before running a manual re-sync."
      : !source.active
        ? "Source is inactive. Enable it before running a manual re-sync."
        : "",
  );
  let showManageAccess = $derived(
    can_manage_sources &&
      !syncing &&
      source.last_sync_status === "failed" &&
      (source.credentials?.auth_health === "reauthorization_required" ||
        (!source.credentials?.configured && source.visibility === "private")),
  );

  // Poll while a sync job is queued or running; $effect never runs during SSR.
  $effect(() => {
    if (!syncing) {
      return;
    }

    let reloading = false;
    const timer = setInterval(() => {
      if (reloading) {
        return;
      }

      reloading = true;
      router.reload({
        only: ["source", "stats", "packages", "webhook_events", "audit"],
        onFinish: () => {
          reloading = false;
        },
      });
    }, 5000);

    return () => clearInterval(timer);
  });

  function withOrganization(path: string): string {
    if (!organization?.slug) {
      return path;
    }

    return path === "/"
      ? `/organization/${organization.slug}`
      : `/organization/${organization.slug}${path}`;
  }

  function queryParam(url: string, name: string): string {
    const query = url.split("?")[1] ?? "";

    return new URLSearchParams(query).get(name) ?? "";
  }

  function safeBackUrl(url: string): string {
    const fallback = withOrganization("/sources");
    const returnTo = queryParam(url, "return_to");
    const canUseAdminReturn = auth?.user?.is_admin === true;

    if (returnTo === fallback || returnTo.startsWith(`${fallback}?`)) {
      return returnTo;
    }

    if (
      canUseAdminReturn &&
      (returnTo === "/admin/sources" || returnTo.startsWith("/admin/sources?"))
    ) {
      return returnTo;
    }

    return fallback;
  }

  let backUrl = $derived(safeBackUrl(usePage<AppPageProps>().url ?? ""));

  function maskedSecret(secret: string): string {
    if (!secret) {
      return "";
    }

    const visibleTail = secret.slice(-6);

    return `••••••••••••••••••••••••••••${visibleTail}`;
  }

  function canReprocessWebhookEvent(event: SourceWebhookEventPayload): boolean {
    return (
      can_manage_sources &&
      source.active &&
      ["rejected", "sync_schedule_failed"].includes(event.status)
    );
  }

  function submit(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    const form = event.currentTarget;

    router.post(form.action, Object.fromEntries(new FormData(form)), {
      preserveScroll: true,
    });
  }

  // Close the form only on success; on error it stays mounted with entered values.
  function submitSettings(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    const form = event.currentTarget;

    router.post(form.action, Object.fromEntries(new FormData(form)), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        editingSettings = false;
      },
    });
  }

  function confirmRotate(): void {
    if (rotating) {
      return;
    }

    rotating = true;
    router.post(
      withOrganization(`/sources/${source.id}/webhook/rotate`),
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          rotating = false;
          rotateConfirmOpen = false;
        },
      },
    );
  }

  function confirmAction(): void {
    if (actionSubmitting || !pendingAction) {
      return;
    }

    const hide = pendingAction === "hide";
    actionSubmitting = true;
    router.post(
      withOrganization(
        `/sources/${source.id}/${hide ? "packages/activity" : "archive"}`,
      ),
      hide ? { active: "0" } : { archived: "1" },
      {
        preserveScroll: true,
        onFinish: () => {
          actionSubmitting = false;
          pendingAction = null;
        },
      },
    );
  }

  function postNow(path: string, data: Record<string, string>): void {
    if (actionSubmitting) {
      return;
    }

    actionSubmitting = true;
    router.post(withOrganization(`/sources/${source.id}/${path}`), data, {
      preserveScroll: true,
      onFinish: () => (actionSubmitting = false),
    });
  }

  function confirmSourceDelete(): void {
    if (sourceDeleting) {
      return;
    }

    sourceDeleting = true;
    router.post(
      withOrganization(`/sources/${source.id}/delete`),
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          sourceDeleting = false;
          sourceDeleteConfirmOpen = false;
        },
      },
    );
  }
</script>

<svelte:head>
  <title>{source.name}</title>
</svelte:head>

<AppShell title={source.name} {auth}>
  <section class="grid gap-6">
    <BackButton label="Back to sources" href={backUrl} />

    <Card className="rounded-[1.75rem] p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="ui-eyebrow">{resolvedBranding.name} source</p>
          <h2 class="ui-title mt-1 text-2xl font-semibold break-all">
            {source.name}
          </h2>
          <a
            class="ui-link-accent mt-1 block text-sm break-all"
            href={source.url}
            rel="noopener noreferrer"
            target="_blank"
          >
            {source.url}
          </a>
        </div>
        {#if can_manage_sources}
          <form
            method="POST"
            action={withOrganization(`/sources/${source.id}/resync`)}
            onsubmit={(event) => {
              event.preventDefault();
              submit(
                event as SubmitEvent & {
                  currentTarget: EventTarget & HTMLFormElement;
                },
              );
            }}
          >
            <Button
              className="rounded-full px-4 py-2 text-sm"
              disabled={!source.active || syncing}
              type="submit"
              variant="primary"
            >
              {source.sync_job_status === "running"
                ? "Syncing…"
                : source.sync_job_status === "pending"
                  ? "Sync queued…"
                  : "Re-sync source"}
            </Button>
          </form>
        {/if}
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <Badge tone="neutral">
          {source.provider_label ?? source.provider ?? source.type}
        </Badge>
        <Badge tone={source.visibility === "private" ? "danger" : "accent"}>
          {source.visibility}
        </Badge>
        <Badge tone={sourceStateTone(source)}>{sourceStateLabel(source)}</Badge>
        {#if source.sync_job_status}
          <Badge tone="gold">
            {source.sync_job_status === "running"
              ? "Sync running"
              : "Sync queued"}
          </Badge>
        {:else}
          <Badge tone={sync.tone}>{sync.label}</Badge>
        {/if}
      </div>

      {#if resyncUnavailableReason}
        <p class="mt-3 text-sm ui-muted">{resyncUnavailableReason}</p>
      {/if}

      <div class="mt-3 grid gap-1 text-sm ui-muted">
        {#if source.last_successful_sync_at}
          <p>
            <span class="font-medium ui-title">Last success:</span>
            {formatDate(source.last_successful_sync_at)}
          </p>
        {/if}
        {#if source.last_failed_sync_at}
          <p>
            <span class="font-medium ui-title">Last failure:</span>
            {formatDate(source.last_failed_sync_at)}
          </p>
        {/if}
        {#if source.last_sync_error}
          <p class="break-words" style="color: var(--flash-error-text);">
            <span class="font-medium ui-title">Last error:</span>
            {source.last_sync_error}
          </p>
        {/if}
        {#if showManageAccess}
          <p>
            <Link
              class="ui-link-accent font-medium"
              href={withOrganization("/sources?manage_access=1")}
            >
              Manage provider access
            </Link>
            {#if source.credentials?.auth_failure_reason}
              <span class="break-words" style="color: var(--flash-error-text);">
                {source.credentials.auth_failure_reason}
              </span>
            {/if}
          </p>
        {/if}
        <p>
          {stats.packages_count ?? 0} packages · {stats.releases_count ?? 0} releases
        </p>
      </div>

      <p class="mt-3 text-xs ui-muted">
        Type: {source.type} · Default ref: {source.default_ref} · Release retention:
        {source.keep_last_releases > 0
          ? `latest ${source.keep_last_releases} releases`
          : "all releases"} · Created at: {formatDate(source.created_at)}
        {#if source.archived_at}
          · Archived at: {formatDate(source.archived_at)}
        {/if}
      </p>
    </Card>
  </section>

  <section class="grid items-start gap-6 lg:grid-cols-2">
    <Card className="min-w-0 rounded-[1.75rem] p-6">
      <SectionHeading eyebrow="Source settings" className="mb-4" />
      <div class="mt-4 grid gap-4">
        <InsetCard className="grid gap-2 p-4 text-sm ui-muted">
          <p>
            <span class="font-medium ui-title">Default branch:</span>
            <code class="ui-code break-all">{source.default_ref}</code>
          </p>
          <p>
            <span class="font-medium ui-title">Release retention:</span>
            {source.keep_last_releases > 0
              ? `latest ${source.keep_last_releases} releases`
              : "all releases"}
          </p>
          <p>
            <span class="font-medium ui-title">Visibility:</span>
            {source.visibility} ·
            <span class="font-medium ui-title">Synchronization:</span>
            {source.active ? "active" : "inactive"}
          </p>
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium ui-title">Provider access:</span>
            <span>{source.credentials?.status_label ?? "Not configured"}</span>
            <Badge tone={source.credentials?.configured ? "accent" : "neutral"}>
              {source.credentials?.configured ? "configured" : "missing"}
            </Badge>
          </div>
          {#if source.credentials?.masked_value}
            <code class="ui-code block break-all rounded-xl px-3 py-2 text-xs">
              {source.credentials.masked_value}
            </code>
          {/if}
          {#if source.credentials?.auth_failure_reason}
            <p class="break-words" style="color: var(--flash-error-text);">
              {source.credentials.auth_failure_reason}
            </p>
          {/if}
          {#if source.credentials?.last_token_refresh_at}
            <p class="text-xs">
              Last token refresh: {formatDate(
                source.credentials.last_token_refresh_at,
              )}
            </p>
          {/if}
        </InsetCard>

        {#if can_manage_sources}
          {#if !editingSettings}
            <div class="flex flex-wrap gap-3">
              <Button
                className="rounded-full px-4 py-2 text-sm"
                type="button"
                variant="secondary"
                onclick={startEditing}
              >
                Edit settings
              </Button>
              <Link
                class="ui-link-accent self-center text-sm font-medium"
                href={withOrganization("/sources?manage_access=1")}
              >
                Manage provider access
              </Link>
            </div>
          {:else}
            <form
              method="POST"
              action={withOrganization(`/sources/${source.id}/settings`)}
              onsubmit={(event) => {
                event.preventDefault();
                submitSettings(
                  event as SubmitEvent & {
                    currentTarget: EventTarget & HTMLFormElement;
                  },
                );
              }}
            >
              <InsetCard className="p-4 text-sm ui-muted">
                <p class="font-medium ui-title">Update source settings</p>
                <Select
                  className="mt-3 w-full rounded-xl px-3 py-2 text-sm"
                  name="visibility"
                  bind:value={draft.visibility}
                >
                  <option value="public">Public</option>
                  <option value="private">Private</option>
                </Select>
                <label
                  class="ui-code mt-4 flex items-center gap-3 rounded-xl px-3 py-2 text-sm"
                >
                  <input type="hidden" name="active" value="0" />
                  <input
                    type="checkbox"
                    name="active"
                    bind:checked={draft.active}
                  />
                  <span>
                    Source is active and {resolvedBranding.name} may synchronize it
                  </span>
                </label>
                <label class="mt-4 grid gap-2">
                  <span class="text-sm font-medium ui-title">
                    Keep last releases
                  </span>
                  <Input
                    className="w-full"
                    name="keep_last_releases"
                    type="number"
                    min="0"
                    step="1"
                    bind:value={draft.keep}
                  />
                  <span class="text-xs ui-muted">
                    Limits active release versions, keeping the highest version
                    numbers. Older versions are hidden from the registry and
                    remain in package history. Dev versions do not count toward
                    the limit. 0 disables the limit. Changes apply on next sync;
                    previously hidden versions are not automatically restored.
                  </span>
                </label>
                <div class="mt-4 flex flex-wrap gap-3">
                  <Button
                    className="rounded-full px-4 py-2 text-sm"
                    type="submit"
                    variant="primary"
                  >
                    Save
                  </Button>
                  <Button
                    className="rounded-full px-4 py-2 text-sm"
                    type="button"
                    variant="secondary"
                    onclick={() => (editingSettings = false)}
                  >
                    Cancel
                  </Button>
                </div>
              </InsetCard>
            </form>
          {/if}
        {:else}
          <InsetCard className="p-4 text-sm ui-muted">
            Owner membership is required to modify source visibility,
            credentials, and synchronization controls.
          </InsetCard>
        {/if}
      </div>
    </Card>

    <Card className="min-w-0 rounded-[1.75rem] p-6">
      <SectionHeading eyebrow="Webhook" className="mb-4" />
      <div class="mt-4 grid gap-4">
        {#if supportsWebhookProcessing}
          <InsetCard className="grid gap-2 p-4 text-sm ui-muted">
            <!-- registration data is only sent to managers; viewers see no badge -->
            {#if can_manage_sources}
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium ui-title">Registration:</span>
                {#if source.supports_auto_registration}
                  <Badge
                    tone={source.webhook_registered ? "accent" : "neutral"}
                  >
                    {source.webhook_registered
                      ? "Registered at provider"
                      : "Not registered at provider"}
                  </Badge>
                {:else}
                  <Badge tone="neutral">Manual registration</Badge>
                {/if}
              </div>
            {/if}
            {#if can_manage_sources && source.supports_auto_registration && source.webhook_registered && source.webhook_external_id}
              <p class="text-xs">
                External ID:
                <code class="ui-code break-all">
                  {source.webhook_external_id}
                </code>
              </p>
            {/if}
            {#if can_manage_sources && !source.supports_auto_registration}
              <p>
                This provider cannot register webhooks automatically. Add a
                webhook in the provider yourself: open Connection details, copy
                the URL and the secret and paste them into the provider webhook
                settings.
              </p>
            {/if}
            {#if lastDelivery}
              <p>
                <span class="font-medium ui-title">Last delivery:</span>
                {lastDelivery.status} at {formatDate(lastDelivery.created_at)}
              </p>
            {/if}
            {#if can_manage_sources && source.supports_auto_registration && !source.webhook_registered}
              <form
                method="POST"
                action={withOrganization(
                  `/sources/${source.id}/webhook/register`,
                )}
                onsubmit={(event) => {
                  event.preventDefault();
                  submit(
                    event as SubmitEvent & {
                      currentTarget: EventTarget & HTMLFormElement;
                    },
                  );
                }}
              >
                <Button
                  className="rounded-full px-4 py-2 text-sm"
                  type="submit"
                  variant="primary"
                >
                  Register webhook at provider
                </Button>
              </form>
            {/if}
          </InsetCard>

          <details
            class="ui-inset min-w-0 rounded-xl border p-4 text-sm ui-muted"
          >
            <summary class="cursor-pointer font-medium ui-title">
              Connection details
            </summary>
            <div class="mt-3 grid gap-3">
              <div>
                <div class="flex items-center justify-between gap-3">
                  <p class="font-medium ui-title">Webhook URL</p>
                  <CopyButton
                    label="Copy webhook URL"
                    value={source.webhook_url ?? ""}
                  />
                </div>
                <code
                  class="ui-code mt-2 block break-all rounded-xl px-3 py-2 text-xs"
                >
                  {source.webhook_url}
                </code>
              </div>
              {#if can_manage_sources && source.webhook_secret}
                <div>
                  <div class="flex items-center justify-between gap-3">
                    <p class="font-medium ui-title">Webhook secret</p>
                    <CopyButton
                      label="Copy webhook secret"
                      value={source.webhook_secret}
                    />
                  </div>
                  <code
                    class="ui-code mt-2 block break-all rounded-xl px-3 py-2 text-xs"
                  >
                    {maskedSecret(source.webhook_secret ?? "")}
                  </code>
                </div>
                <div>
                  <Button
                    className="rounded-full px-4 py-2 text-sm"
                    type="button"
                    variant="secondary"
                    onclick={() => (rotateConfirmOpen = true)}
                  >
                    Rotate secret
                  </Button>
                </div>
              {/if}
            </div>
          </details>
        {:else}
          <InsetCard className="p-4 text-sm ui-muted">
            <p class="font-medium ui-title">Webhooks are not supported</p>
            <p class="mt-2 leading-6">
              Generic Git sources do not support webhook processing. Use manual
              resync to update packages from this repository.
            </p>
          </InsetCard>
        {/if}
      </div>
    </Card>
  </section>

  <section class="grid gap-6">
    <Card className="min-w-0 rounded-[1.75rem] p-6">
      <SectionHeading eyebrow="Packages" className="mb-4" />
      {#if packages.length === 0}
        <p class="text-sm ui-muted">No packages synchronized yet.</p>
      {:else}
        <ul class="grid gap-2">
          {#each packages as pkg (pkg.name)}
            <li>
              <InsetCard
                className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-2.5 text-sm"
              >
                <Link
                  class="ui-link-accent min-w-0 font-medium break-all"
                  href={withOrganization(`/packages/${pkg.name}`)}
                >
                  {pkg.name}
                </Link>
                <span class="ui-muted">
                  {pkg.visibility} · {pkg.active ? "listed" : "hidden"} · {pkg.release_count}
                  {pkg.release_count === 1 ? "release" : "releases"}
                </span>
              </InsetCard>
            </li>
          {/each}
        </ul>
      {/if}
    </Card>

    <Card className="min-w-0 rounded-[1.75rem] p-6">
      <SectionHeading eyebrow="Activity" className="mb-4" />
      {#if supportsWebhookProcessing}
        {@const tabClass = (tab: ActivityTab) =>
          `cursor-pointer rounded-lg border px-3 py-1.5 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color-mix(in_srgb,var(--accent)_60%,transparent)] ${
            activeTab === tab
              ? "border-[var(--accent)] bg-[var(--accent-soft)] text-[var(--accent-ink)]"
              : "border-transparent text-[var(--page-muted)] hover:bg-[var(--surface-strong)] hover:text-[var(--accent-ink)]"
          }`}
        <div
          class="mb-4 inline-flex flex-wrap items-center gap-1 rounded-xl border theme-border theme-surface-muted p-1"
          role="tablist"
          aria-label="Activity"
          tabindex="-1"
          onkeydown={onTabKeydown}
        >
          <button
            bind:this={tabButtons.deliveries}
            type="button"
            role="tab"
            id="activity-tab-deliveries"
            aria-selected={activeTab === "deliveries"}
            aria-controls="activity-panel-deliveries"
            tabindex={activeTab === "deliveries" ? 0 : -1}
            class={tabClass("deliveries")}
            onclick={() => (chosenTab = "deliveries")}
          >
            Webhook deliveries ({webhook_events.length})
          </button>
          <button
            bind:this={tabButtons.audit}
            type="button"
            role="tab"
            id="activity-tab-audit"
            aria-selected={activeTab === "audit"}
            aria-controls="activity-panel-audit"
            tabindex={activeTab === "audit" ? 0 : -1}
            class={tabClass("audit")}
            onclick={() => (chosenTab = "audit")}
          >
            Audit log
          </button>
        </div>
      {/if}

      {#if activeTab === "deliveries"}
        <div
          role="tabpanel"
          id="activity-panel-deliveries"
          aria-labelledby="activity-tab-deliveries"
          tabindex="0"
        >
          {#if webhook_events.length === 0}
            <p class="text-sm ui-muted">No deliveries yet</p>
          {:else}
            <ul class="grid max-h-[33rem] gap-2 overflow-y-auto">
              {#each webhook_events as event (event.delivery_id)}
                <li>
                  <InsetCard className="px-4 py-2.5 text-sm">
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                        <p class="font-medium ui-title">
                          {event.event} · {event.status}
                        </p>
                        <p class="ui-muted mt-1 text-xs break-all">
                          {event.delivery_id}
                        </p>
                      </div>
                      {#if canReprocessWebhookEvent(event)}
                        <form
                          method="POST"
                          action={withOrganization(
                            `/sources/webhooks/${source.id}/${event.delivery_id}/reprocess`,
                          )}
                          onsubmit={(event) => {
                            event.preventDefault();
                            submit(
                              event as SubmitEvent & {
                                currentTarget: EventTarget & HTMLFormElement;
                              },
                            );
                          }}
                        >
                          <Button
                            className="rounded-full px-3 py-1.5 text-xs"
                            type="submit"
                            variant="secondary"
                          >
                            Reprocess
                          </Button>
                        </form>
                      {/if}
                    </div>
                    {#if event.message}
                      <p class="ui-muted mt-2 break-words">{event.message}</p>
                    {/if}
                    <p class="ui-muted mt-2 text-xs">
                      received {formatDate(event.created_at)}
                    </p>
                  </InsetCard>
                </li>
              {/each}
            </ul>
          {/if}
        </div>
      {:else}
        <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
        <div
          role={supportsWebhookProcessing ? "tabpanel" : undefined}
          id="activity-panel-audit"
          aria-labelledby={supportsWebhookProcessing
            ? "activity-tab-audit"
            : undefined}
          tabindex={supportsWebhookProcessing ? 0 : undefined}
        >
          {#if audit.length === 0}
            <p class="text-sm ui-muted">
              No audit entries for this source yet.
            </p>
          {:else}
            <ul class="grid gap-2">
              {#each audit as entry (entry.id)}
                <li>
                  <InsetCard
                    className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-2.5 text-sm"
                  >
                    <span class="min-w-0">
                      <span class="font-medium ui-title">
                        {auditEventLabel(entry.event)}
                      </span>
                      <span class="ui-muted ml-2 text-xs break-all">
                        {entry.event}
                      </span>
                    </span>
                    <span class="ui-muted">
                      {formatDate(entry.occurred_at)}
                    </span>
                  </InsetCard>
                </li>
              {/each}
            </ul>
          {/if}
        </div>
      {/if}
    </Card>

    {#if can_manage_sources}
      <Card className="min-w-0 rounded-[1.75rem] border-red-500/40 p-6">
        <SectionHeading eyebrow="Source actions" className="mb-4" />
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {#if source.packages_active}
            <Button
              className="w-full rounded-full px-4 py-2 text-sm"
              type="button"
              variant="secondary"
              onclick={() => (pendingAction = "hide")}
            >
              Hide packages from {resolvedBranding.name}
            </Button>
          {:else}
            <Button
              className="w-full rounded-full px-4 py-2 text-sm"
              type="button"
              variant="secondary"
              disabled={actionSubmitting}
              onclick={() => postNow("packages/activity", { active: "1" })}
            >
              Show packages in {resolvedBranding.name}
            </Button>
          {/if}
          {#if source.archived}
            <Button
              className="w-full rounded-full px-4 py-2 text-sm"
              type="button"
              variant="secondary"
              disabled={actionSubmitting}
              onclick={() => postNow("archive", { archived: "0" })}
            >
              Restore source
            </Button>
          {:else}
            <Button
              className="w-full rounded-full px-4 py-2 text-sm"
              type="button"
              variant="secondary"
              onclick={() => (pendingAction = "archive")}
            >
              Archive source
            </Button>
          {/if}
          <Button
            className="w-full rounded-full px-4 py-2 text-sm"
            type="button"
            variant="danger"
            onclick={() => (sourceDeleteConfirmOpen = true)}
          >
            Delete source
          </Button>
        </div>
      </Card>
    {/if}
  </section>

  <ConfirmationModal
    open={pendingAction === "hide"}
    eyebrow="Source actions"
    title={`Hide packages from ${resolvedBranding.name}?`}
    description={`All packages of this source are hidden from the ${resolvedBranding.name} registry, so Composer can no longer install or update them until they are shown again. Releases, webhooks and sync settings are not changed. Use "Show packages in ${resolvedBranding.name}" to list them again.`}
    confirmLabel="Hide packages"
    cancelLabel="Keep packages listed"
    confirming={actionSubmitting}
    onCancel={() => (pendingAction = null)}
    onConfirm={confirmAction}
  />

  <ConfirmationModal
    open={pendingAction === "archive"}
    eyebrow="Source actions"
    title="Archive source?"
    description={`Archiving deactivates the source, so syncing and manual re-sync stop, and hides all its packages from the ${resolvedBranding.name} registry, so Composer can no longer install or update them. Releases and settings are kept. Restore source re-activates it and lists its packages again, including any you hid separately.`}
    confirmLabel="Archive source"
    cancelLabel="Keep source active"
    confirming={actionSubmitting}
    onCancel={() => (pendingAction = null)}
    onConfirm={confirmAction}
  />

  <ConfirmationModal
    open={rotateConfirmOpen}
    eyebrow="Webhook"
    title="Rotate webhook secret?"
    description={`Rotating generates a new secret and the old one stops working at once. ${resolvedBranding.name} does not update the webhook at the provider, not even an automatically registered one: replace the secret in the provider webhook settings yourself, otherwise deliveries will be rejected.`}
    confirmLabel="Rotate secret"
    cancelLabel="Keep current secret"
    confirming={rotating}
    onCancel={() => (rotateConfirmOpen = false)}
    onConfirm={confirmRotate}
  />

  <ConfirmationModal
    open={sourceDeleteConfirmOpen}
    eyebrow="Source"
    title="Delete source?"
    description={`Deletion runs in the background and cannot be undone. It permanently removes the source, all its packages and releases, their stored archives and its webhook data from ${resolvedBranding.name}. An automatically registered webhook is also removed at the provider; the repository itself is not touched.`}
    confirmLabel="Delete source"
    cancelLabel="Keep source"
    confirming={sourceDeleting}
    onCancel={() => (sourceDeleteConfirmOpen = false)}
    onConfirm={confirmSourceDelete}
  >
    <div class="ui-inset grid gap-2 rounded-xl border p-4">
      <p class="font-medium ui-title">{source.name}</p>
      <p class="break-all text-xs ui-muted">{source.url}</p>
      <div class="flex flex-wrap gap-2 pt-1">
        <span
          class="ui-badge-muted rounded-full px-2.5 py-1 text-[11px] font-medium"
        >
          {source.provider_label ?? source.provider ?? source.type}
        </span>
        <span
          class="ui-badge-muted rounded-full px-2.5 py-1 text-[11px] font-medium"
        >
          {source.visibility}
        </span>
      </div>
    </div>
  </ConfirmationModal>
</AppShell>
