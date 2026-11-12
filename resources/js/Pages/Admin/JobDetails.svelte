<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import Link from "@/Components/core/Link.svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import BackButton from "@/Components/ui/BackButton.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import SnippetBlock from "@/Components/ui/SnippetBlock.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { Endpoints } from "@/lib/api";
  import { formatDate } from "@/lib/date";
  import { jobStatusTone, jobTypeLabel } from "@/lib/job";
  import type { AuthPayload } from "@/types/auth";

  interface Props {
    auth?: AuthPayload;
    job: AdminJobPayload;
    logs?: JobLogPayload[];
    organization_record?: AdminJobOrganization | null;
    deletion_run_id?: string | null;
    back_url?: string;
  }

  let {
    auth = {},
    job,
    logs = [],
    organization_record = null,
    deletion_run_id = null,
    back_url = "/admin/jobs",
  }: Props = $props();

  interface AdminJobOrganization {
    id: string;
    slug: string;
    name: string;
    status: string;
  }

  interface AdminJobPayload {
    id: string;
    organizationId: string;
    type: string;
    payload: Record<string, unknown>;
    idempotencyKey: string | null;
    status: string;
    priority: number;
    scheduledAt: string;
    startedAt: string | null;
    completedAt: string | null;
    attemptCount: number;
    maxAttempts: number;
    errorMessage: string | null;
    errorDetails: string | null;
    workerId: string | null;
    createdAt: string;
    durationSeconds: number | null;
    warningCount: number;
  }

  interface JobLogPayload {
    id: string;
    jobId: string;
    level: string;
    message: string;
    context: Record<string, unknown>;
    createdAt: string;
  }

  function formatDuration(seconds: number | null): string {
    if (seconds === null) {
      return "—";
    }

    if (seconds < 60) {
      return `${seconds}s`;
    }

    if (seconds < 3600) {
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      return `${mins}m ${secs}s`;
    }

    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    return `${hours}h ${mins}m`;
  }

  function logLevelColor(level: string): string {
    switch (level) {
      case "debug":
        return "ui-muted";
      case "info":
        return "ui-title";
      case "warning":
        return "text-yellow-500";
      case "error":
        return "text-red-500";
      default:
        return "ui-muted";
    }
  }

  function hasContext(context: Record<string, unknown> | null): boolean {
    return Boolean(context && Object.keys(context).length > 0);
  }

  function formatContextValue(value: unknown): string {
    if (typeof value === "string") {
      return value;
    }

    if (typeof value === "number" || typeof value === "boolean") {
      return String(value);
    }

    if (value === null) {
      return "null";
    }

    return JSON.stringify(value);
  }

  function restartJob(): void {
    if (
      (job.status === "pending" || job.status === "running") &&
      !confirm("Restart this non-terminal job?")
    ) {
      return;
    }

    const restartUrl =
      back_url === "/admin/jobs"
        ? Endpoints.admin.restartJob(job.id)
        : `${Endpoints.admin.restartJob(job.id)}?return_to=${encodeURIComponent(back_url)}`;

    router.post(restartUrl, {});
  }

  let payloadJson = $derived(JSON.stringify(job?.payload ?? {}, null, 2));
</script>

<svelte:head>
  <title>Admin Job {job?.id}</title>
</svelte:head>

<AdminShell title="Admin Job" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    <div class="grid gap-6">
      <BackButton label="Back to jobs" href={back_url} />

      <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
        <div
          class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
        >
          <SectionHeading
            eyebrow="Administration"
            title={jobTypeLabel(job.type)}
            description={`Job ID: ${job.id}`}
          />

          <Button
            className="w-full rounded-full px-5 sm:w-fit"
            type="button"
            variant="dangerSoft"
            onclick={restartJob}
          >
            Restart
          </Button>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-4">
          <div class="ui-inset rounded-xl border p-4 theme-border">
            <p class="text-xs uppercase tracking-[0.18em] ui-muted">Status</p>
            <p class="mt-3">
              <Badge tone={jobStatusTone(job.status)}>{job.status}</Badge>
            </p>
          </div>
          <div class="ui-inset rounded-xl border p-4 theme-border">
            <p class="text-xs uppercase tracking-[0.18em] ui-muted">Attempts</p>
            <p class="mt-2 text-2xl font-semibold ui-title">
              {job.attemptCount}/{job.maxAttempts}
            </p>
          </div>
          <div class="ui-inset rounded-xl border p-4 theme-border">
            <p class="text-xs uppercase tracking-[0.18em] ui-muted">Priority</p>
            <p class="mt-2 text-2xl font-semibold ui-title">
              {job.priority}
            </p>
          </div>
          <div class="ui-inset rounded-xl border p-4 theme-border">
            <p class="text-xs uppercase tracking-[0.18em] ui-muted">Duration</p>
            <p class="mt-2 text-2xl font-semibold ui-title">
              {formatDuration(job.durationSeconds)}
            </p>
          </div>
        </div>

        <dl class="mt-6 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
          <div>
            <dt class="font-medium ui-title">Created</dt>
            <dd class="mt-1 ui-muted">{formatDate(job.createdAt)}</dd>
          </div>
          <div>
            <dt class="font-medium ui-title">Scheduled</dt>
            <dd class="mt-1 ui-muted">{formatDate(job.scheduledAt)}</dd>
          </div>
          <div>
            <dt class="font-medium ui-title">Started</dt>
            <dd class="mt-1 ui-muted">{formatDate(job.startedAt)}</dd>
          </div>
          <div>
            <dt class="font-medium ui-title">Completed</dt>
            <dd class="mt-1 ui-muted">{formatDate(job.completedAt)}</dd>
          </div>
        </dl>
      </Card>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.55fr)]">
        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <SectionHeading eyebrow="Payload" className="mb-4" />
          <SnippetBlock
            title="JSON"
            content={payloadJson}
            multiline
            copyContent={payloadJson}
          />
        </Card>

        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <SectionHeading eyebrow="Context" className="mb-4" />
          <dl class="grid gap-4 text-sm">
            <div>
              <dt class="font-medium ui-title">Organization</dt>
              <dd class="mt-1">
                {#if organization_record}
                  <Link
                    class="font-medium ui-title hover:underline"
                    href={`/admin/organizations/${organization_record.id}`}
                  >
                    {organization_record.name}
                  </Link>
                  <p class="text-xs ui-muted">/{organization_record.slug}</p>
                {:else}
                  <span class="break-all ui-muted">{job.organizationId}</span>
                {/if}
              </dd>
            </div>
            <div>
              <dt class="font-medium ui-title">Idempotency key</dt>
              <dd class="mt-1 break-all ui-muted">
                {job.idempotencyKey ?? "—"}
              </dd>
            </div>
            <div>
              <dt class="font-medium ui-title">Worker</dt>
              <dd class="mt-1 break-all ui-muted">{job.workerId ?? "—"}</dd>
            </div>
            <div>
              <dt class="font-medium ui-title">Deletion operation</dt>
              <dd class="mt-1 break-all">
                {#if deletion_run_id}
                  <Link
                    class="ui-title hover:underline"
                    href={`/admin/jobs?deletion_run_id=${encodeURIComponent(deletion_run_id)}`}
                  >
                    {deletion_run_id}
                  </Link>
                {:else}
                  <span class="ui-muted">—</span>
                {/if}
              </dd>
            </div>
          </dl>
        </Card>
      </div>

      {#if job.errorMessage || job.errorDetails}
        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <SectionHeading eyebrow="Error" className="mb-4" />
          {#if job.errorMessage}
            <SnippetBlock
              title="Message"
              content={job.errorMessage}
              multiline
              copyContent={job.errorMessage}
              className="border-red-500/30"
            />
          {/if}
          {#if job.errorDetails}
            <div class="mt-4">
              <SnippetBlock
                title="Details"
                content={job.errorDetails}
                multiline
                copyContent={job.errorDetails}
                className="border-red-500/30"
              />
            </div>
          {/if}
        </Card>
      {/if}

      <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
        <SectionHeading eyebrow="Logs" className="mb-4" />
        {#if logs.length === 0}
          <p class="text-sm ui-muted">No logs available for this job.</p>
        {:else}
          <div
            class="min-w-0 overflow-x-auto rounded-xl border border-(--panel-border) bg-(--surface)"
          >
            <div class="max-h-[34rem] min-w-0 overflow-y-auto">
              {#each logs as log (log.id)}
                <div
                  class="min-w-0 border-b border-(--panel-border) p-4 last:border-b-0"
                >
                  <div class="flex flex-wrap items-center gap-3">
                    <span
                      class={`text-xs font-medium uppercase ${logLevelColor(log.level)}`}
                    >
                      {log.level}
                    </span>
                    <span class="text-xs ui-muted">
                      {formatDate(log.createdAt)}
                    </span>
                  </div>
                  <p
                    class="mt-2 min-w-0 break-all whitespace-pre-wrap text-sm {logLevelColor(
                      log.level,
                    )}"
                  >
                    {log.message}
                  </p>
                  {#if hasContext(log.context)}
                    <dl
                      class="mt-3 min-w-0 overflow-x-auto rounded-lg border border-(--panel-border) bg-(--surface-muted) p-3 text-xs"
                    >
                      {#each Object.entries(log.context) as [key, value]}
                        <div
                          class="grid gap-1 sm:grid-cols-[10rem_minmax(0,1fr)]"
                        >
                          <dt class="break-all font-medium ui-muted">
                            {key}
                          </dt>
                          <dd
                            class="break-all whitespace-pre-wrap font-mono ui-title"
                          >
                            {formatContextValue(value)}
                          </dd>
                        </div>
                      {/each}
                    </dl>
                  {/if}
                </div>
              {/each}
            </div>
          </div>
        {/if}
      </Card>
    </div>
  </div>
</AdminShell>
