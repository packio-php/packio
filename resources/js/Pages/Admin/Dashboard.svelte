<script lang="ts">
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import type { AuthPayload } from "@/types/auth";
  import type { AdminDashboardPageProps } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    stats?: AdminDashboardPageProps["stats"];
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
  }: Props = $props();
</script>

<svelte:head>
  <title>Admin</title>
</svelte:head>

<AdminShell title="Admin" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
      <SectionHeading
        eyebrow="Administration"
        title="System overview"
        description="Global Packio controls for users with the admin role."
      />
      <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="ui-inset rounded-xl border p-4 sm:p-5 theme-border">
          <p class="text-xs uppercase tracking-[0.24em] ui-muted">Users</p>
          <p
            class="mt-3 text-3xl font-semibold"
            style="color: var(--page-ink);"
          >
            {stats?.users_count ?? 0}
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 sm:p-5 theme-border">
          <p class="text-xs uppercase tracking-[0.24em] ui-muted">Admins</p>
          <p
            class="mt-3 text-3xl font-semibold"
            style="color: var(--page-ink);"
          >
            {stats?.admins_count ?? 0}
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 sm:p-5 theme-border">
          <p class="text-xs uppercase tracking-[0.24em] ui-muted">
            Organizations
          </p>
          <p
            class="mt-3 text-3xl font-semibold"
            style="color: var(--page-ink);"
          >
            {stats?.organizations_count ?? 0}
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 sm:p-5 theme-border">
          <p class="text-xs uppercase tracking-[0.24em] ui-muted">Packages</p>
          <p
            class="mt-3 text-3xl font-semibold"
            style="color: var(--page-ink);"
          >
            {stats?.packages_count ?? 0}
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 sm:p-5 theme-border">
          <p class="text-xs uppercase tracking-[0.24em] ui-muted">Sources</p>
          <p
            class="mt-3 text-3xl font-semibold"
            style="color: var(--page-ink);"
          >
            {stats?.sources_count ?? 0}
          </p>
        </div>
      </div>
    </Card>
  </div>
</AdminShell>
