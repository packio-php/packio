<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import Link from "@/Components/core/Link.svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import type { AuthPayload } from "@/types/auth";
  import type {
    AdminOrganizationsPageProps,
    AdminUsersPaginationMeta,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    organizations?: AdminOrganizationsPageProps["organizations"];
    pagination?: AdminOrganizationsPageProps["pagination"];
  }

  const defaultPagination: AdminUsersPaginationMeta = {
    current_page: 1,
    from: 0,
    has_more_pages: false,
    has_previous_pages: false,
    last_page: 1,
    per_page: 20,
    to: 0,
    total: 0,
  };

  let {
    auth = {},
    organizations = [],
    pagination = defaultPagination,
  }: Props = $props();

  function handlePageChange(page: number): void {
    router.get(
      "/admin/organizations",
      { page, per_page: pagination.per_page },
      { preserveScroll: true },
    );
  }
</script>

<svelte:head>
  <title>Admin Organizations</title>
</svelte:head>

<AdminShell title="Admin Organizations" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    <div class="grid gap-6">
      <SectionHeading
        eyebrow="Administration"
        title="Organizations"
        description="Browse all organizations and open a read-only admin overview."
      />

      {#if (organizations?.length ?? 0) === 0}
        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <p class="text-sm ui-muted">No organizations found.</p>
        </Card>
      {:else}
        <div class="grid gap-4">
          {#each organizations ?? [] as organization (organization.id)}
            <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
              <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
              >
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <h2
                      class="break-words text-lg font-semibold sm:text-xl"
                      style="color: var(--page-ink);"
                    >
                      {organization.name}
                    </h2>
                    {#if organization.status === "deleting"}
                      <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium uppercase tracking-[0.14em]"
                        style="background-color: var(--surface-muted); color: var(--page-muted);"
                      >
                        deleting
                      </span>
                    {/if}
                  </div>
                  <p class="mt-1 text-sm ui-muted">/{organization.slug}</p>
                </div>

                <div
                  class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between md:gap-6"
                >
                  <div class="flex flex-wrap gap-3 text-sm ui-muted">
                    <span>{organization.members_count} members</span>
                    <span>{organization.invitations_count} invitations</span>
                  </div>
                  <Link
                    class="ui-button-primary inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-semibold no-underline transition"
                    href={`/admin/organizations/${organization.id}`}
                  >
                    View
                  </Link>
                </div>
              </div>
            </Card>
          {/each}
        </div>

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
    </div>
  </div>
</AdminShell>
