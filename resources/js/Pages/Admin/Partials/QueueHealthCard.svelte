<script lang="ts">
  import type { Snippet } from "svelte";
  import Card from "@/Components/ui/Card.svelte";
  import { formatDuration } from "@/lib/date";
  import type { QueueProgress } from "@/types/pages";

  interface Props {
    queueProgress?: QueueProgress;
    authMailProgress?: QueueProgress;
    description: string;
    actions?: Snippet;
  }

  let { queueProgress, authMailProgress, description, actions }: Props =
    $props();

  // Oldest ready job waiting longer than this is highlighted as a warning.
  const staleReadyAgeSeconds = 5 * 60;

  function readyAgeMetric(progress: QueueProgress | undefined): {
    label: string;
    value: string;
    warn: boolean;
  } {
    const age = progress?.oldest_ready_age_seconds ?? null;

    return {
      label: "Oldest ready",
      value: age === null ? "None" : formatDuration(age),
      warn: age !== null && age > staleReadyAgeSeconds,
    };
  }
</script>

<Card className="rounded-[1.25rem] p-4">
  <section aria-labelledby="queue-health">
    <div class="flex items-start justify-between gap-3">
      <h2 id="queue-health" class="text-base font-semibold ui-title">
        Queue health
      </h2>
      {@render actions?.()}
    </div>
    <p class="mt-1 text-sm ui-muted">{description}</p>
    <div class="mt-4 grid gap-3">
      {#each [{ label: "Queue", metrics: [{ label: "Retrying", value: queueProgress?.retrying ?? 0, warn: false }, { label: "Stuck", value: queueProgress?.stuck ?? 0, warn: (queueProgress?.stuck ?? 0) > 0 }, readyAgeMetric(queueProgress)] }, { label: "Auth mail", metrics: [{ label: "Pending", value: authMailProgress?.pending ?? 0, warn: false }, { label: "Failed", value: authMailProgress?.failed ?? 0, warn: (authMailProgress?.failed ?? 0) > 0 }, readyAgeMetric(authMailProgress)] }] as row (row.label)}
        <div class="grid items-center gap-3 sm:grid-cols-[7rem_1fr]">
          <h3 class="text-sm font-semibold ui-title">{row.label}</h3>
          <dl class="grid grid-cols-3 gap-3" aria-label={row.label}>
            {#each row.metrics as metric (metric.label)}
              <div
                class={`rounded-xl border p-3 theme-border ${metric.warn ? "ui-badge-gold" : "ui-inset"}`}
                data-warning={metric.warn ? "true" : undefined}
              >
                <dt class="text-xs ui-muted">{metric.label}</dt>
                <dd class="mt-1 font-semibold ui-title">{metric.value}</dd>
              </div>
            {/each}
          </dl>
        </div>
      {/each}
    </div>
  </section>
</Card>
