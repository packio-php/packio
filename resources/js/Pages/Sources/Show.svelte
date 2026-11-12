<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import { router } from "@inertiajs/svelte";

  import AppShell from "@/Layouts/AppShell.svelte";
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
  let supportsWebhookProcessing = $derived(source.provider !== "generic_git");

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
      <div class="flex items-start justify-between gap-4">
        <SectionHeading
          eyebrow={`${resolvedBranding.name} source`}
          title={source.name}
          description={source.url}
        />
      </div>
      <div class="mt-4 flex flex-wrap gap-2">
        <span
          class={`rounded-full px-3 py-1 text-xs font-medium ${
            source.active ? "ui-badge-accent" : "ui-badge-muted"
          }`}
        >
          {source.active ? "active" : "inactive"}
        </span>
        {#if source.archived}
          <span
            class="rounded-full ui-badge-muted px-3 py-1 text-xs font-medium"
          >
            archived
          </span>
        {/if}
        <span
          class={`rounded-full px-3 py-1 text-xs font-medium ${
            source.visibility === "private"
              ? "ui-badge-danger"
              : "ui-badge-accent"
          }`}
        >
          {source.visibility}
        </span>
      </div>

      <div class="mt-6 grid gap-4 md:grid-cols-3">
        <InsetCard className="p-4">
          <p class="ui-eyebrow">Packages</p>
          <p class="ui-title mt-2 text-3xl font-semibold">
            {stats.packages_count ?? 0}
          </p>
        </InsetCard>
        <InsetCard className="p-4">
          <p class="ui-eyebrow">Releases</p>
          <p class="ui-title mt-2 text-3xl font-semibold">
            {stats.releases_count ?? 0}
          </p>
        </InsetCard>
        <InsetCard className="p-4">
          <p class="ui-eyebrow">Deliveries</p>
          <p class="ui-title mt-2 text-3xl font-semibold">
            {stats.deliveries_count ?? 0}
          </p>
        </InsetCard>
      </div>

      <div class="mt-6 grid gap-2 text-sm ui-muted">
        <p>
          <span class="font-medium ui-title">Provider:</span>
          {source.provider_label ?? source.provider ?? source.type}
        </p>
        <p>
          <span class="font-medium ui-title">Type:</span>
          {source.type}
        </p>
        <p>
          <span class="font-medium ui-title">Default ref:</span>
          {source.default_ref}
        </p>
        <p>
          <span class="font-medium ui-title">Visibility:</span>
          {source.visibility}
        </p>
        <p>
          <span class="font-medium ui-title">Release retention:</span>
          {source.keep_last_releases > 0
            ? `latest ${source.keep_last_releases} releases`
            : "all releases"}
        </p>
        <p>
          <span class="font-medium ui-title">State:</span>
          {source.active ? "active" : "inactive"}
        </p>
        {#if source.archived_at}
          <p>
            <span class="font-medium ui-title">Archived at:</span>
            {formatDate(source.archived_at)}
          </p>
        {/if}
        <p>
          <span class="font-medium ui-title">Created at:</span>
          {formatDate(source.created_at)}
        </p>
        <p>
          <span class="font-medium ui-title">Sync status:</span>
          <span
            class={`ml-2 rounded-full px-2.5 py-1 text-xs font-medium ${
              source.last_sync_status === "failed"
                ? "ui-badge-danger"
                : source.last_sync_status === "success"
                  ? "ui-badge-accent"
                  : "ui-badge-gold"
            }`}
          >
            {source.last_sync_status}
          </span>
        </p>
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
          <p style="color: var(--flash-error-text);">
            <span class="font-medium ui-title">Last error:</span>
            {source.last_sync_error}
          </p>
        {/if}
      </div>
    </Card>
  </section>

  <section class="grid gap-6 xl:grid-cols-2">
    <Card className="rounded-[1.75rem] p-6">
      <SectionHeading
        eyebrow={`${resolvedBranding.name} controls`}
        className="mb-4"
      />
      <div class="mt-4 grid gap-4">
        <InsetCard className="p-4 text-sm ui-muted">
          <p class="font-medium ui-title">Source visibility</p>
          <p class="mt-3">
            Current visibility: {source.visibility}
          </p>
          <p class="mt-2">
            Synchronization: {source.active ? "active" : "inactive"}
          </p>
          <div
            class="mt-4 rounded-2xl border border-[color:var(--line)] bg-[color:var(--surface-2)]/80 p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-medium ui-title">Provider authorization</p>
                <p class="mt-1 text-xs ui-muted">
                  {source.credentials?.status_label ?? "Not configured"}
                </p>
              </div>
              <span
                class={`rounded-full px-3 py-1 text-xs font-medium ${
                  source.credentials?.configured
                    ? "ui-badge-accent"
                    : "ui-badge-muted"
                }`}
              >
                {source.credentials?.configured ? "configured" : "missing"}
              </span>
            </div>
            {#if source.credentials?.masked_value}
              <code
                class="ui-code mt-3 block overflow-x-auto rounded-xl px-3 py-2 text-xs"
              >
                {source.credentials.masked_value}
              </code>
            {/if}
            {#if source.credentials?.auth_health}
              <div class="mt-3 grid gap-2 text-xs">
                <div class="flex items-center gap-2">
                  <span class="ui-muted">Authorization health</span>
                  <span
                    class={`rounded-full px-2.5 py-1 font-medium ${
                      source.credentials.auth_health ===
                      "reauthorization_required"
                        ? "ui-badge-danger"
                        : "ui-badge-accent"
                    }`}
                  >
                    {source.credentials.auth_health}
                  </span>
                </div>
                {#if source.credentials?.auth_failure_reason}
                  <p style="color: var(--flash-error-text);">
                    {source.credentials.auth_failure_reason}
                  </p>
                {/if}
                {#if source.credentials?.last_token_refresh_at}
                  <p class="ui-muted">
                    Last token refresh: {formatDate(
                      source.credentials?.last_token_refresh_at,
                    )}
                  </p>
                {/if}
              </div>
            {/if}
          </div>
        </InsetCard>

        {#if can_manage_sources}
          <form
            method="POST"
            action={withOrganization(`/sources/${source.id}/settings`)}
            onsubmit={(event) => {
              event.preventDefault();
              submit(
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
              >
                <option value="public" selected={source.visibility === "public"}
                  >Public</option
                >
                <option
                  value="private"
                  selected={source.visibility === "private"}>Private</option
                >
              </Select>
              <label
                class="ui-code mt-4 flex items-center gap-3 rounded-xl px-3 py-2 text-sm"
              >
                <input type="hidden" name="active" value="0" />
                <input type="checkbox" name="active" checked={source.active} />
                <span
                  >Source is active and {resolvedBranding.name} may synchronize it</span
                >
              </label>
              <label class="mt-4 grid gap-2">
                <span class="text-sm font-medium ui-title"
                  >Keep last releases</span
                >
                <Input
                  className="w-full"
                  name="keep_last_releases"
                  type="number"
                  min="0"
                  step="1"
                  value={source.keep_last_releases}
                />
                <span class="text-xs ui-muted">
                  Limits active release versions, keeping the highest version
                  numbers. Older versions are hidden from the registry and
                  remain in package history. Dev versions do not count toward
                  the limit. 0 disables the limit. Changes apply on next sync;
                  previously hidden versions are not automatically restored.
                </span>
              </label>
              <p class="mt-2 text-xs ui-muted">
                Provider authorization is managed separately from source
                settings through the organization sources authorization flow.
              </p>
              <Button
                className="mt-3 rounded-full px-4 py-2 text-sm"
                type="submit"
                variant="primary"
              >
                Save {resolvedBranding.name} settings
              </Button>
            </InsetCard>
          </form>
        {:else}
          <InsetCard className="p-4 text-sm ui-muted">
            Owner membership is required to modify source visibility,
            credentials, and synchronization controls.
          </InsetCard>
        {/if}

        {#if can_manage_sources}
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                className="w-full rounded-full px-4 py-2 text-sm"
                disabled={!source.active}
                type="submit"
                variant="primary"
              >
                Re-sync source
              </Button>
            </form>
            <form
              method="POST"
              action={withOrganization(
                `/sources/${source.id}/packages/activity`,
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
              <input
                type="hidden"
                name="active"
                value={source.packages_active ? "0" : "1"}
              />
              <Button
                className="w-full rounded-full px-4 py-2 text-sm"
                type="submit"
                variant="secondary"
              >
                {source.packages_active
                  ? `Hide packages from ${resolvedBranding.name}`
                  : `Show packages in ${resolvedBranding.name}`}
              </Button>
            </form>
            <form
              method="POST"
              action={withOrganization(`/sources/${source.id}/archive`)}
              onsubmit={(event) => {
                event.preventDefault();
                submit(
                  event as SubmitEvent & {
                    currentTarget: EventTarget & HTMLFormElement;
                  },
                );
              }}
            >
              <input
                type="hidden"
                name="archived"
                value={source.archived ? "0" : "1"}
              />
              <Button
                className="w-full rounded-full px-4 py-2 text-sm"
                type="submit"
                variant="secondary"
              >
                {source.archived ? "Restore source" : "Archive source"}
              </Button>
            </form>
            <Button
              className="w-full rounded-full px-4 py-2 text-sm"
              type="button"
              variant="danger"
              onclick={() => (sourceDeleteConfirmOpen = true)}
            >
              Delete source
            </Button>
          </div>
        {/if}
      </div>
    </Card>

    <Card className="rounded-[1.75rem] p-6">
      <SectionHeading eyebrow="Webhook" className="mb-4" />
      <div class="mt-4 grid gap-4">
        {#if supportsWebhookProcessing}
          <InsetCard className="p-4 text-sm ui-muted">
            <div class="flex items-center justify-between gap-3">
              <p class="font-medium ui-title">Webhook URL</p>
              <CopyButton
                label="Copy webhook URL"
                value={source.webhook_url ?? ""}
              />
            </div>
            <code
              class="ui-code mt-3 block overflow-x-auto rounded-xl px-3 py-2 text-xs"
            >
              {source.webhook_url}
            </code>
          </InsetCard>

          {#if can_manage_sources}
            <InsetCard className="p-4 text-sm ui-muted">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-medium ui-title">Upstream webhook status</p>
                  <p class="mt-1 text-xs ui-muted">
                    {#if source.supports_auto_registration}
                      {#if source.webhook_registered}
                        Registered at the upstream provider
                        {#if source.webhook_external_id}
                          <br />
                          External ID:
                          <code class="ui-code"
                            >{source.webhook_external_id}</code
                          >
                        {/if}
                      {:else}
                        Not yet registered at the upstream provider
                      {/if}
                    {:else}
                      Automatic registration is not supported for this provider
                    {/if}
                  </p>
                </div>
                {#if source.supports_auto_registration}
                  <span
                    class={`rounded-full px-3 py-1 text-xs font-medium ${
                      source.webhook_registered
                        ? "ui-badge-accent"
                        : "ui-badge-muted"
                    }`}
                  >
                    {source.webhook_registered
                      ? "registered"
                      : "not registered"}
                  </span>
                {/if}
              </div>
            </InsetCard>

            {#if source.supports_auto_registration && !source.webhook_registered}
              <div class="flex flex-wrap gap-3">
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
              </div>
            {/if}
          {/if}

          {#if can_manage_sources && source.webhook_secret}
            <InsetCard className="p-4 text-sm ui-muted">
              <div class="flex items-center justify-between gap-3">
                <p class="font-medium ui-title">Webhook secret</p>
                <CopyButton
                  label="Copy webhook secret"
                  value={source.webhook_secret}
                />
              </div>
              <code
                class="ui-code mt-3 block overflow-x-auto rounded-xl px-3 py-2 text-xs"
              >
                {maskedSecret(source.webhook_secret ?? "")}
              </code>
            </InsetCard>

            <div class="flex flex-wrap gap-3">
              <form
                method="POST"
                action={withOrganization(
                  `/sources/${source.id}/webhook/rotate`,
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
                  variant="secondary"
                >
                  Rotate secret
                </Button>
              </form>
            </div>
          {/if}
          <InsetCard className="p-4 text-sm ui-muted">
            <p class="font-medium ui-title">Recent deliveries</p>
            <div class="mt-4 grid gap-3 max-h-[33rem] overflow-y-auto">
              {#if webhook_events.length === 0}
                <p class="text-sm ui-muted">No webhook deliveries yet.</p>
              {:else}
                {#each webhook_events as event (event.delivery_id)}
                  <div class="ui-code rounded-xl p-3">
                    <div class="flex items-start justify-between gap-3">
                      <div>
                        <p class="text-sm font-medium ui-title">
                          {event.event} · {event.status}
                        </p>
                        <p class="ui-muted mt-1 text-xs">
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
                      <p class="ui-muted mt-2 text-sm">
                        {event.message}
                      </p>
                    {/if}
                    <p class="ui-muted mt-2 text-xs">
                      received {formatDate(event.created_at)}
                    </p>
                  </div>
                {/each}
              {/if}
            </div>
          </InsetCard>
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

  <section class="grid gap-6 xl:grid-cols-2">
    <Card className="rounded-[1.75rem] p-6">
      <SectionHeading eyebrow="Packages and releases" className="mb-4" />
      <div class="mt-4 grid gap-4">
        {#if packages.length === 0}
          <p class="text-sm ui-muted">No packages synchronized yet.</p>
        {:else}
          {#each packages as pkg (pkg.name)}
            <InsetCard as="article" className="p-5">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="ui-title text-lg font-semibold">
                    <Link
                      class="ui-link-accent"
                      href={withOrganization(`/packages/${pkg.name}`)}
                    >
                      {pkg.name}
                    </Link>
                  </p>
                  <p class="ui-muted mt-2 text-sm leading-6">
                    {pkg.description || "No description"}
                  </p>
                </div>
                <div class="text-right text-sm ui-muted">
                  <p>{pkg.release_count} releases</p>
                  <p class="mt-1">{pkg.visibility}</p>
                  <p class="mt-1">
                    {pkg.active ? "listed" : "hidden"}
                  </p>
                </div>
              </div>
              <div class="mt-4 flex flex-wrap gap-2">
                {#each pkg.releases as release (release.version)}
                  <span
                    class="ui-code rounded-full px-3 py-1.5 text-xs font-medium"
                  >
                    {release.version}
                  </span>
                {/each}
              </div>
            </InsetCard>
          {/each}
        {/if}
      </div>
    </Card>

    <Card className="rounded-[1.75rem] p-6">
      <SectionHeading eyebrow="Audit trail" className="mb-4" />
      <div class="mt-4 grid gap-3">
        {#if audit.length === 0}
          <p class="text-sm ui-muted">
            No {resolvedBranding.name} audit entries for this source yet.
          </p>
        {:else}
          {#each audit as entry (entry.id)}
            <InsetCard className="p-4">
              <p class="font-medium ui-title">
                {entry.event}
              </p>
              <p class="ui-muted mt-2 text-sm">
                {formatDate(entry.occurred_at)}
              </p>
            </InsetCard>
          {/each}
        {/if}
      </div>
    </Card>
  </section>

  <ConfirmationModal
    open={sourceDeleteConfirmOpen}
    eyebrow="Source"
    title="Delete source?"
    description="This removes the source and its synchronized package data from the organization."
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
