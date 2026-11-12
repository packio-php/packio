<script lang="ts" module>
  import { Endpoints } from "@/lib/api";
  import { cleanQuery, queryString } from "@/lib/queryFilters";

  export type RecoveryModalAction =
    | "details"
    | "delete"
    | "requeue"
    | "force_requeue"
    | "cancel"
    | "force_finalize";

  export interface AdminOrganization {
    id: string;
    slug: string;
    name: string;
    status: string;
  }

  export interface AdminSourcePayload {
    active: boolean;
    archived: boolean;
    archived_at: string;
    canonical_url: string;
    created_at: string;
    default_ref: string;
    id: string;
    keep_last_releases: number;
    last_failed_sync_at: string;
    last_successful_sync_at: string;
    last_sync_error: string;
    last_sync_status: string;
    logical_status: string;
    name: string;
    organization: AdminOrganization | null;
    owner: string;
    provider: string;
    provider_label: string;
    repository: string;
    recovery: SourceDeletionRecoverySummary | null;
    status: string;
    updated_at: string;
    url: string;
    visibility: "private" | "public" | string;
  }

  export interface SourceDeleteJobSummary {
    id: string;
    status: string;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
    error_message: string | null;
    idempotency_key: string | null;
    deletion_run_id: string | null;
  }

  export interface SourceDeletionRecoverySummary {
    source_id: string;
    organization_id: string;
    health: string;
    related_jobs_count: number;
    active_jobs_count: number;
    latest_job_id: string | null;
    latest_job_status: string | null;
    latest_job_created_at: string | null;
    latest_job_error: string | null;
    linked_packages_count: number;
    linked_releases_count: number;
    webhook_external_id_present: boolean;
    can_requeue: boolean;
    can_force_requeue: boolean;
    can_cancel: boolean;
    can_force_finalize: boolean;
    related_jobs: SourceDeleteJobSummary[];
  }

  export function statusTone(status: string): string {
    if (status === "deleting") return "danger";
    if (status === "active") return "accent";
    return "muted";
  }

  export function deleteStateLabel(item: AdminSourcePayload): string {
    const recovery = item.recovery;
    if (!recovery) return item.logical_status;

    if (recovery.health === "in_progress") {
      return recovery.latest_job_status === "running"
        ? "delete running"
        : "delete queued";
    }
    if (recovery.health === "failed") return "delete failed";
    if (recovery.health === "stale_without_job") return "delete job missing";
    if (recovery.health === "completed_but_source_exists") {
      return "completed but exists";
    }

    return "deleting";
  }

  export function deleteHealthTone(health: string): string {
    if (
      health === "failed" ||
      health === "stale_without_job" ||
      health === "completed_but_source_exists"
    ) {
      return "danger";
    }

    return health === "in_progress" ? "gold" : "muted";
  }

  export function deleteJobsUrl(item: AdminSourcePayload): string {
    return `${Endpoints.admin.jobs()}${queryString(
      cleanQuery({
        organization_id:
          item.organization?.id ?? item.recovery?.organization_id,
        type: ["source.delete"],
        source_id: item.id,
      }),
    )}`;
  }
</script>

<script lang="ts">
  import { onMount } from "svelte";
  import { router } from "@inertiajs/svelte";
  import {
    ExternalLink,
    RefreshCw,
    RotateCcw,
    ShieldAlert,
    Trash2,
    X,
  } from "@lucide/svelte";
  import Link from "@/Components/core/Link.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import { apiClient } from "@/lib/api";
  import { formatDate } from "@/lib/date";
  import type { SourceDeletionPreviewPayload } from "@/types/pages";

  interface Props {
    source: AdminSourcePayload;
    action: RecoveryModalAction;
    returnUrl: string;
    onClose: () => void;
    onAction: (action: RecoveryModalAction) => void;
  }

  let { source, action, returnUrl, onClose, onAction }: Props = $props();

  let recoveryConfirmation = $state("");
  let recoveryCancelJobs = $state(false);
  let acknowledgeOrphanedArchives = $state(false);
  let recoverySubmitting = $state(false);
  let deletePreview = $state<SourceDeletionPreviewPayload | null>(null);
  // Loading from the first frame; onMount would flip it one frame late and flash "0".
  // svelte-ignore state_referenced_locally
  let deletePreviewLoading = $state(action === "delete");
  let deletePreviewError = $state("");
  let recoveryConfirmIsDisabled = $derived(
    recoverySubmitting ||
      action === "details" ||
      (action === "cancel" && recoveryConfirmation !== "CANCEL DELETE") ||
      (action === "force_finalize" &&
        recoveryConfirmation !== "FORCE DELETE SOURCE") ||
      (action === "delete" &&
        (deletePreview === null || !deleteConfirmationMatches())),
  );

  function deleteConfirmationMatches(): boolean {
    return recoveryConfirmation === source.name;
  }

  async function loadDeletePreview(sourceId: string): Promise<void> {
    deletePreviewLoading = true;
    const result = await apiClient.get<SourceDeletionPreviewPayload>(
      Endpoints.admin.sourceDeletionPreview(sourceId),
    );
    deletePreviewLoading = false;

    if (!result.ok) {
      deletePreviewError = result.error;
      return;
    }

    deletePreview = result.data;
  }

  function closeRecoveryModal(): void {
    if (recoverySubmitting) return;

    onClose();
  }

  function postRecoveryAction(): void {
    let url = "";
    let payload: Record<string, string> = {};

    if (action === "requeue") {
      url = Endpoints.admin.requeueSourceDelete(source.id);
    } else if (action === "force_requeue") {
      url = Endpoints.admin.forceRequeueSourceDelete(source.id);
    } else if (action === "delete") {
      url = Endpoints.admin.sourceDelete(source.id);
      payload = { confirmationName: recoveryConfirmation };
    } else if (action === "cancel") {
      url = Endpoints.admin.cancelSourceDelete(source.id);
      payload = {
        confirmation: recoveryConfirmation,
        cancel_jobs: recoveryCancelJobs ? "1" : "0",
      };
    } else if (action === "force_finalize") {
      url = Endpoints.admin.forceFinalizeSourceDelete(source.id);
      payload = {
        confirmation: recoveryConfirmation,
        acknowledge_orphaned_archives: acknowledgeOrphanedArchives ? "1" : "0",
      };
    } else {
      return;
    }

    recoverySubmitting = true;
    router.post(`${url}?return_to=${encodeURIComponent(returnUrl)}`, payload, {
      preserveScroll: true,
      onFinish: () => {
        recoverySubmitting = false;
        closeRecoveryModal();
      },
    });
  }

  function recoveryActionTitle(action: RecoveryModalAction): string {
    if (action === "details") return "Recovery details";
    if (action === "delete") return "Delete source?";
    if (action === "requeue") return "Queue a new deletion job?";
    if (action === "force_requeue") return "Force requeue deletion?";
    if (action === "cancel") return "Cancel deletion?";
    return "Force finalize deletion?";
  }

  function recoveryActionDescription(action: RecoveryModalAction): string {
    if (action === "delete") {
      return "Start permanent deletion of this source and its synchronized package data. Cleanup runs as a staged background job.";
    }
    if (action === "requeue") {
      return "Queue a new deletion job for this source. This continues permanent deletion of the source and synchronized package data.";
    }
    if (action === "force_requeue") {
      return "Queue a separate recovery deletion job even if another delete job exists. Use this only when the existing job is stale or cannot be trusted.";
    }
    if (action === "cancel") {
      return "Cancel deletion only if no cleanup has partially removed package or release data. The source will be restored inactive.";
    }
    if (action === "force_finalize") {
      return "This permanently removes the source row and remaining linked metadata. Use it only when requeue cannot complete.";
    }

    return "";
  }

  function recoveryConfirmLabel(action: RecoveryModalAction): string {
    if (action === "delete") return "Delete source";
    if (action === "requeue") return "Requeue delete";
    if (action === "force_requeue") return "Force requeue";
    if (action === "cancel") return "Cancel deletion";
    if (action === "force_finalize") return "Force finalize";
    return "Close";
  }

  function sourceAgeLabel(createdAt: string): string {
    if (!createdAt) return "-";

    const timestamp = Date.parse(createdAt);
    if (Number.isNaN(timestamp)) return "-";

    const days = Math.max(
      0,
      Math.floor((Date.now() - timestamp) / (1000 * 60 * 60 * 24)),
    );

    return days === 1 ? "1 day" : `${days} days`;
  }

  onMount(() => {
    if (action === "delete") {
      void loadDeletePreview(source.id);
    }
  });

  function handleRecoveryKeydown(event: KeyboardEvent): void {
    if (event.key === "Escape") {
      closeRecoveryModal();
    }
  }

  function updateRecoveryConfirmation(event: Event): void {
    recoveryConfirmation =
      event.currentTarget instanceof HTMLInputElement
        ? event.currentTarget.value
        : "";
  }
</script>

<svelte:window onkeydown={handleRecoveryKeydown} />

<div
  class="fixed inset-0 z-50 overflow-y-auto bg-(--page-bg)/75 p-3 backdrop-blur sm:p-4"
  role="presentation"
  onclick={(event) => {
    if (event.target === event.currentTarget) closeRecoveryModal();
  }}
>
  <div
    class="mx-auto flex min-h-full w-full max-w-3xl items-start py-3 sm:items-center sm:py-4"
    aria-modal="true"
    aria-labelledby="source-recovery-modal-title"
    role="dialog"
  >
    <Card
      className="max-h-[calc(100dvh-1.5rem)] w-full overflow-y-auto rounded-[1.4rem] p-0 sm:max-h-[calc(100dvh-2rem)]"
    >
      <div class="grid gap-5 p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="ui-eyebrow">
              {action === "delete"
                ? "Source deletion"
                : "Source deletion recovery"}
            </p>
            <h2
              id="source-recovery-modal-title"
              class="ui-title mt-2 text-xl font-semibold"
            >
              {recoveryActionTitle(action)}
            </h2>
            {#if action !== "details"}
              <p class="mt-2 text-sm leading-6 ui-muted">
                {recoveryActionDescription(action)}
              </p>
            {/if}
          </div>
          <Button
            aria-label="Close recovery dialog"
            disabled={recoverySubmitting}
            title="Close"
            type="button"
            variant="iconSmall"
            onclick={closeRecoveryModal}
          >
            <X aria-hidden="true" size={16} />
          </Button>
        </div>

        <div class="grid gap-4">
          <div class="grid gap-3 rounded-xl border p-4 theme-border">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-lg font-semibold ui-title">
                  {source.name}
                </p>
                <p class="mt-1 break-all text-sm ui-muted">
                  {source.url}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <Badge tone={statusTone(source.logical_status)}>
                  {source.logical_status}
                </Badge>
                {#if source.recovery}
                  <Badge tone={deleteHealthTone(source.recovery.health)}>
                    {deleteStateLabel(source)}
                  </Badge>
                {/if}
              </div>
            </div>

            <div class="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <p class="ui-eyebrow">Source ID</p>
                <p class="mt-1 break-all ui-title">
                  {source.id}
                </p>
              </div>
              <div>
                <p class="ui-eyebrow">Organization</p>
                <p class="mt-1 ui-title">
                  {source.organization?.name ?? "unknown"}
                </p>
                <p class="text-xs ui-muted">
                  {source.organization?.id ??
                    source.recovery?.organization_id ??
                    "-"}
                </p>
              </div>
              <div>
                <p class="ui-eyebrow">Provider</p>
                <p class="mt-1 ui-title">
                  {source.provider_label}
                </p>
              </div>
              <div>
                <p class="ui-eyebrow">Age</p>
                <p class="mt-1 ui-title">
                  {sourceAgeLabel(source.created_at)}
                </p>
                <p class="text-xs ui-muted">
                  Created {source.created_at
                    ? formatDate(source.created_at)
                    : "-"}
                </p>
                <p class="text-xs ui-muted">
                  Updated {source.updated_at
                    ? formatDate(source.updated_at)
                    : "-"}
                </p>
              </div>
            </div>
          </div>

          {#if source.recovery}
            <div class="grid gap-3 sm:grid-cols-4">
              <div class="ui-inset rounded-xl border p-3 theme-border">
                <p class="ui-eyebrow">Packages</p>
                <p class="mt-2 text-xl font-semibold ui-title">
                  {source.recovery.linked_packages_count}
                </p>
              </div>
              <div class="ui-inset rounded-xl border p-3 theme-border">
                <p class="ui-eyebrow">Releases</p>
                <p class="mt-2 text-xl font-semibold ui-title">
                  {source.recovery.linked_releases_count}
                </p>
              </div>
              <div class="ui-inset rounded-xl border p-3 theme-border">
                <p class="ui-eyebrow">Delete jobs</p>
                <p class="mt-2 text-xl font-semibold ui-title">
                  {source.recovery.related_jobs_count}
                </p>
              </div>
              <div class="ui-inset rounded-xl border p-3 theme-border">
                <p class="ui-eyebrow">Webhook</p>
                <p class="mt-2 text-xl font-semibold ui-title">
                  {source.recovery.webhook_external_id_present
                    ? "external"
                    : "none"}
                </p>
              </div>
            </div>

            <div class="grid gap-3 rounded-xl border p-4 theme-border">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold ui-title">
                  Related delete jobs
                </p>
                {#if source.recovery.related_jobs_count > 0}
                  <Link
                    class="ui-link-accent inline-flex items-center gap-2 text-sm font-medium"
                    href={deleteJobsUrl(source)}
                  >
                    <ExternalLink aria-hidden="true" size={15} />
                    View jobs
                  </Link>
                {/if}
              </div>

              {#if source.recovery.related_jobs.length === 0}
                <p class="text-sm ui-muted">
                  No delete jobs are retained for this source. You can requeue
                  deletion from the source record.
                </p>
              {:else}
                <div class="grid gap-2">
                  {#each source.recovery.related_jobs as job (job.id)}
                    <div
                      class="grid gap-2 rounded-lg border p-3 text-sm theme-border md:grid-cols-[minmax(0,1fr)_auto]"
                    >
                      <div class="min-w-0">
                        <Link
                          class="ui-link-accent break-all font-medium"
                          href={Endpoints.admin.job(job.id)}
                        >
                          {job.id}
                        </Link>
                        {#if job.error_message}
                          <p class="mt-1 break-words text-xs ui-muted">
                            {job.error_message}
                          </p>
                        {/if}
                      </div>
                      <div
                        class="flex flex-wrap items-center gap-2 md:justify-end"
                      >
                        <Badge
                          tone={job.status === "failed" ? "danger" : "neutral"}
                        >
                          {job.status}
                        </Badge>
                        <span class="text-xs ui-muted">
                          {job.created_at ? formatDate(job.created_at) : "-"}
                        </span>
                      </div>
                    </div>
                  {/each}
                </div>
              {/if}
            </div>
          {/if}

          {#if action === "delete"}
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="ui-inset rounded-xl border p-3 theme-border">
                <p class="ui-eyebrow">Packages</p>
                {#if deletePreviewLoading}
                  <p class="mt-2 text-sm ui-muted">Loading...</p>
                {:else if deletePreviewError}
                  <p
                    class="mt-2 text-sm"
                    style="color: var(--flash-error-text);"
                  >
                    {deletePreviewError}
                  </p>
                {:else}
                  <p class="mt-2 text-xl font-semibold ui-title">
                    {deletePreview?.packages_count ?? 0}
                  </p>
                {/if}
              </div>
              <div class="ui-inset rounded-xl border p-3 theme-border">
                <p class="ui-eyebrow">Releases</p>
                {#if deletePreviewLoading}
                  <p class="mt-2 text-sm ui-muted">Loading...</p>
                {:else if deletePreviewError}
                  <p
                    class="mt-2 text-sm"
                    style="color: var(--flash-error-text);"
                  >
                    {deletePreviewError}
                  </p>
                {:else}
                  <p class="mt-2 text-xl font-semibold ui-title">
                    {deletePreview?.releases_count ?? 0}
                  </p>
                {/if}
              </div>
            </div>
          {/if}

          {#if action === "cancel"}
            <label class="grid gap-2 text-sm">
              <span class="font-medium ui-title">Typed confirmation</span>
              <input
                class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                type="text"
                value={recoveryConfirmation}
                oninput={updateRecoveryConfirmation}
              />
              <span class="text-xs ui-muted">Type CANCEL DELETE</span>
            </label>
            <label class="flex items-center gap-2 text-sm ui-title">
              <input
                bind:checked={recoveryCancelJobs}
                class="h-4 w-4 rounded border-(--panel-border)"
                type="checkbox"
              />
              Cancel pending delete jobs
            </label>
          {:else if action === "force_finalize"}
            <label class="grid gap-2 text-sm">
              <span class="font-medium ui-title">Typed confirmation</span>
              <input
                class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                type="text"
                value={recoveryConfirmation}
                oninput={updateRecoveryConfirmation}
              />
              <span class="text-xs ui-muted">Type FORCE DELETE SOURCE</span>
            </label>
            <label class="flex items-center gap-2 text-sm ui-title">
              <input
                bind:checked={acknowledgeOrphanedArchives}
                class="h-4 w-4 rounded border-(--panel-border)"
                type="checkbox"
              />
              Continue if archive cleanup cannot be fully scheduled
            </label>
          {:else if action === "delete"}
            <label class="grid gap-2 text-sm">
              <span class="font-medium ui-title">Typed confirmation</span>
              <input
                class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                type="text"
                value={recoveryConfirmation}
                oninput={updateRecoveryConfirmation}
              />
              <span class="text-xs ui-muted">
                Type <strong>{source.name}</strong> to confirm.
              </span>
            </label>
          {/if}
        </div>

        {#if action === "details"}
          <div class="grid gap-3 border-t pt-4 theme-border">
            <p class="text-sm font-semibold ui-title">Recovery actions</p>
            <div class="flex flex-wrap gap-2">
              {#if source.recovery?.can_requeue}
                <Button
                  className="gap-2 rounded-full"
                  type="button"
                  variant="primary"
                  onclick={() => onAction("requeue")}
                >
                  <RefreshCw aria-hidden="true" size={15} />
                  Requeue delete
                </Button>
              {/if}
              {#if source.recovery?.can_force_requeue}
                <Button
                  className="gap-2 rounded-full"
                  type="button"
                  variant="soft"
                  onclick={() => onAction("force_requeue")}
                >
                  <RotateCcw aria-hidden="true" size={15} />
                  Force requeue
                </Button>
              {/if}
              {#if source.recovery?.can_cancel}
                <Button
                  className="gap-2 rounded-full"
                  type="button"
                  variant="dangerSoft"
                  onclick={() => onAction("cancel")}
                >
                  <ShieldAlert aria-hidden="true" size={15} />
                  Cancel deletion
                </Button>
              {/if}
              {#if source.recovery?.can_force_finalize}
                <Button
                  className="gap-2 rounded-full"
                  type="button"
                  variant="dangerSoft"
                  onclick={() => onAction("force_finalize")}
                >
                  <Trash2 aria-hidden="true" size={15} />
                  Force finalize
                </Button>
              {/if}
            </div>
          </div>

          <div class="flex justify-end">
            <Button
              className="rounded-full px-5"
              type="button"
              variant="secondary"
              onclick={closeRecoveryModal}
            >
              Close
            </Button>
          </div>
        {:else}
          <div
            class="grid gap-3 border-t pt-4 sm:flex sm:justify-end theme-border"
          >
            <Button
              className="w-full rounded-full px-5 sm:w-auto"
              disabled={recoverySubmitting}
              type="button"
              variant="secondary"
              onclick={closeRecoveryModal}
            >
              Cancel
            </Button>
            <Button
              className="w-full gap-2 rounded-full px-5 sm:w-auto"
              disabled={recoveryConfirmIsDisabled}
              type="button"
              variant={action === "force_finalize" ||
              action === "cancel" ||
              action === "delete"
                ? "danger"
                : "primary"}
              onclick={postRecoveryAction}
            >
              {#if action === "force_finalize" || action === "delete"}
                <Trash2 aria-hidden="true" size={15} />
              {:else if action === "cancel"}
                <ShieldAlert aria-hidden="true" size={15} />
              {:else if action === "force_requeue"}
                <RotateCcw aria-hidden="true" size={15} />
              {:else}
                <RefreshCw aria-hidden="true" size={15} />
              {/if}
              {recoverySubmitting ? "Working..." : recoveryConfirmLabel(action)}
            </Button>
          </div>
        {/if}
      </div>
    </Card>
  </div>
</div>
