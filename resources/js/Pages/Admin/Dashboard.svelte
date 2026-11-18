<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import { RefreshCw } from "@lucide/svelte";
  import Link from "@/Components/core/Link.svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import { formatDate } from "@/lib/date";
  import type { AuthPayload } from "@/types/auth";
  import type { AdminDashboardPageProps } from "@/types/pages";
  import QueueHealthCard from "./Partials/QueueHealthCard.svelte";

  interface Props {
    auth?: AuthPayload;
    stats?: AdminDashboardPageProps["stats"];
    queue_progress?: AdminDashboardPageProps["queue_progress"];
    auth_mail_progress?: AdminDashboardPageProps["auth_mail_progress"];
    failed_sources?: AdminDashboardPageProps["failed_sources"];
    failed_sources_count?: number;
    recent_events?: AdminDashboardPageProps["recent_events"];
  }

  let {
    auth = {},
    stats = {
      admins_count: 0,
      organizations_count: 0,
      packages_count: 0,
      sources_count: 0,
      users_count: 0,
    },
    queue_progress,
    auth_mail_progress,
    failed_sources = [],
    failed_sources_count = 0,
    recent_events = [],
  }: Props = $props();
  let refreshing = $state(false);

  const tiles = $derived([
    { label: "Users", value: stats?.users_count, href: "/admin/users" },
    { label: "Admins", value: stats?.admins_count, href: "/admin/users" },
    {
      label: "Organizations",
      value: stats?.organizations_count,
      href: "/admin/organizations",
    },
    {
      label: "Packages",
      value: stats?.packages_count,
      href: "/admin/packages",
    },
    { label: "Sources", value: stats?.sources_count, href: "/admin/sources" },
  ]);

  function refresh(): void {
    router.reload({
      onStart: () => {
        refreshing = true;
      },
      onFinish: () => {
        refreshing = false;
      },
    });
  }
</script>

<svelte:head>
  <title>Admin</title>
</svelte:head>

<AdminShell title="Admin" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    <div class="grid min-w-0 gap-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <SectionHeading
          eyebrow="Administration"
          title="System overview"
          description="Global Packio controls for users with the admin role."
        />
        <Button
          aria-busy={refreshing}
          className="gap-2 rounded-full"
          disabled={refreshing}
          type="button"
          variant="secondary"
          onclick={refresh}
        >
          <RefreshCw
            aria-hidden="true"
            class={refreshing ? "animate-spin" : ""}
            size={15}
          />
          Refresh
        </Button>
      </div>

      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {#each tiles as tile (tile.label)}
          <Link
            class="ui-inset rounded-xl border p-4 theme-border"
            href={tile.href}
          >
            <p class="text-xs uppercase tracking-[0.18em] ui-muted">
              {tile.label}
            </p>
            <p class="mt-2 text-2xl font-semibold ui-title">
              {tile.value ?? 0}
            </p>
          </Link>
        {/each}
      </div>

      <QueueHealthCard
        queueProgress={queue_progress}
        authMailProgress={auth_mail_progress}
        description="All organizations"
      >
        {#snippet actions()}
          <Link class="text-sm font-medium hover:underline" href="/admin/jobs">
            All jobs
          </Link>
        {/snippet}
      </QueueHealthCard>

      <Card className="rounded-[1.25rem] p-4">
        <section aria-labelledby="failed-syncs">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2">
              <h2 id="failed-syncs" class="text-base font-semibold ui-title">
                Failed syncs
              </h2>
              {#if failed_sources_count > 0}
                <Badge tone="danger">{failed_sources_count}</Badge>
              {/if}
            </div>
            <Link
              class="text-sm font-medium hover:underline"
              href="/admin/sources?sync_status=failed"
            >
              All failed
            </Link>
          </div>
          {#if failed_sources.length === 0}
            <p class="mt-4 text-sm ui-muted">
              All sources synced successfully.
            </p>
          {:else}
            <ul class="mt-4 grid gap-3">
              {#each failed_sources as source (source.id)}
                <li class="ui-inset rounded-xl border p-3 theme-border">
                  <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                  >
                    <span class="font-medium ui-title">{source.name}</span>
                    {#if source.organization}
                      <Link
                        class="text-xs ui-muted hover:underline"
                        href={`/admin/organizations/${source.organization.id}`}
                      >
                        {source.organization.slug}
                      </Link>
                    {/if}
                  </div>
                  <p
                    class="mt-1 truncate text-sm ui-muted"
                    title={source.last_sync_error ?? ""}
                  >
                    {source.last_sync_error ?? ""}
                  </p>
                  {#if source.last_failed_sync_at}
                    <p class="mt-1 text-xs ui-muted">
                      {formatDate(source.last_failed_sync_at)}
                    </p>
                  {/if}
                </li>
              {/each}
            </ul>
          {/if}
        </section>
      </Card>

      <Card className="rounded-[1.25rem] p-4">
        <section aria-labelledby="recent-events">
          <h2 id="recent-events" class="text-base font-semibold ui-title">
            Recent events
          </h2>
          {#if recent_events.length === 0}
            <p class="mt-4 text-sm ui-muted">No events yet.</p>
          {:else}
            <div class="mt-4 hidden md:block">
              <TableFrame ariaLabel="Recent events">
                <thead>
                  <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Event</th>
                    <th class="px-4 py-3">Actor</th>
                    <th class="px-4 py-3">Organization</th>
                  </tr>
                </thead>
                <tbody>
                  {#each recent_events as item (item.id)}
                    <tr>
                      <td class="px-4 py-3 ui-muted">
                        {formatDate(item.occurred_at)}
                      </td>
                      <td class="px-4 py-3 font-mono text-sm">{item.event}</td>
                      <td class="px-4 py-3">{item.actor_name}</td>
                      <td class="px-4 py-3">
                        {#if item.organization}
                          <Link
                            class="hover:underline"
                            href={`/admin/organizations/${item.organization.id}`}
                          >
                            {item.organization.slug}
                          </Link>
                        {:else}
                          <span class="ui-muted">—</span>
                        {/if}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </TableFrame>
            </div>
            <ul class="mt-4 grid gap-3 md:hidden">
              {#each recent_events as item (item.id)}
                <li class="ui-inset rounded-xl border p-3 theme-border">
                  <p class="font-mono text-sm ui-title">{item.event}</p>
                  <p class="mt-1 text-xs ui-muted">
                    {item.actor_name} · {item.organization?.slug ?? "—"} · {formatDate(
                      item.occurred_at,
                    )}
                  </p>
                </li>
              {/each}
            </ul>
          {/if}
        </section>
      </Card>
    </div>
  </div>
</AdminShell>
