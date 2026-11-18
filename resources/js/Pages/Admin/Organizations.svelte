<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import { FilterX, ListFilter, Search } from "@lucide/svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Link from "@/Components/core/Link.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import { formatDate } from "@/lib/date";
  import { cleanQuery } from "@/lib/queryFilters";
  import type { AuthPayload } from "@/types/auth";
  import type {
    AdminOrganizationsPageProps,
    AdminUsersPaginationMeta,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    filters?: AdminOrganizationsPageProps["filters"];
    organizations?: AdminOrganizationsPageProps["organizations"];
    pagination?: AdminOrganizationsPageProps["pagination"];
    stats?: AdminOrganizationsPageProps["stats"];
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
    filters = { search: "" },
    organizations = [],
    pagination = defaultPagination,
    stats = { organizations_count: 0 },
  }: Props = $props();

  let filtersOpen = $state(false);
  let activeFilterCount = $derived((filters?.search ?? "") !== "" ? 1 : 0);

  function applyFilters(event: SubmitEvent): void {
    const formData = new FormData(event.currentTarget as HTMLFormElement);

    router.get(
      "/admin/organizations",
      cleanQuery({
        search: String(formData.get("search") ?? ""),
        page: 1,
        per_page: pagination.per_page,
      }),
      { preserveScroll: true },
    );
  }

  function clearFilters(): void {
    router.get(
      "/admin/organizations",
      { per_page: pagination.per_page },
      { preserveScroll: true },
    );
  }

  function handlePageChange(page: number): void {
    router.get(
      "/admin/organizations",
      cleanQuery({
        search: filters?.search ?? "",
        page,
        per_page: pagination.per_page,
      }),
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
    <div class="grid min-w-0 gap-6">
      <SectionHeading
        eyebrow="Administration"
        title="Organizations"
        description="Browse all organizations and open a read-only admin overview."
      />

      <div class="grid gap-3 md:grid-cols-4">
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">
            Organizations
          </p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {stats.organizations_count}
          </p>
        </div>
      </div>

      <Card className="rounded-[1.25rem] p-4">
        <div class="grid gap-4">
          <div class="flex items-center gap-2">
            <div class="relative">
              <Button
                aria-controls="admin-organization-filters"
                aria-expanded={filtersOpen}
                aria-label={filtersOpen ? "Hide filters" : "Show filters"}
                className="rounded-full"
                title={filtersOpen ? "Hide filters" : "Show filters"}
                type="button"
                variant="icon"
                onclick={() => {
                  filtersOpen = !filtersOpen;
                }}
              >
                <ListFilter aria-hidden="true" size={18} />
              </Button>
              {#if activeFilterCount > 0}
                <span
                  class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full px-1 text-[0.65rem] font-bold"
                  style="background-color: var(--accent); color: var(--button-primary-text);"
                >
                  {activeFilterCount}
                </span>
              {/if}
            </div>

            {#if activeFilterCount > 0}
              <Button
                aria-label="Clear filters"
                className="rounded-full"
                title="Clear filters"
                type="button"
                variant="icon"
                onclick={clearFilters}
              >
                <FilterX aria-hidden="true" size={18} />
              </Button>
            {/if}
          </div>

          {#if filtersOpen}
            <form
              id="admin-organization-filters"
              class="grid gap-4"
              onsubmit={(event) => {
                event.preventDefault();
                applyFilters(event);
              }}
            >
              <div class="grid gap-4 lg:grid-cols-4">
                <label class="grid gap-2 text-sm">
                  <span class="font-medium ui-title">Search</span>
                  <input
                    class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
                    name="search"
                    placeholder="Name or slug"
                    type="search"
                    value={filters?.search ?? ""}
                  />
                </label>
              </div>

              <div class="flex flex-wrap gap-3">
                <Button
                  className="gap-2 rounded-full px-5"
                  type="submit"
                  variant="primary"
                >
                  <Search aria-hidden="true" size={16} />
                  Apply filters
                </Button>
                <Button
                  className="gap-2 rounded-full px-5"
                  type="button"
                  variant="soft"
                  onclick={clearFilters}
                >
                  <FilterX aria-hidden="true" size={16} />
                  Clear
                </Button>
              </div>
            </form>
          {/if}
        </div>
      </Card>

      {#if (organizations?.length ?? 0) === 0}
        <p class="text-sm ui-muted">No organizations found.</p>
      {:else}
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

        <div class="hidden md:block">
          <TableFrame
            ariaLabel="Admin organizations"
            tableClassName="min-w-[720px]"
          >
            <thead>
              <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3 text-right">Members</th>
                <th class="px-4 py-3 text-right">Invitations</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Created</th>
              </tr>
            </thead>
            <tbody>
              {#each organizations ?? [] as organization (organization.id)}
                <tr class="align-top">
                  <td class="px-4 py-3">
                    <Link
                      class="font-semibold ui-title"
                      href={`/admin/organizations/${organization.id}`}
                    >
                      {organization.name}
                    </Link>
                    <p class="text-xs ui-muted">/{organization.slug}</p>
                  </td>
                  <td class="px-4 py-3 text-right tabular-nums">
                    {organization.members_count}
                  </td>
                  <td class="px-4 py-3 text-right tabular-nums">
                    {organization.invitations_count}
                  </td>
                  <td class="px-4 py-3">
                    {#if organization.status === "deleting"}
                      <Badge tone="muted">deleting</Badge>
                    {/if}
                  </td>
                  <td class="px-4 py-3 text-sm ui-muted">
                    {organization.created_at
                      ? formatDate(organization.created_at)
                      : "-"}
                  </td>
                </tr>
              {/each}
            </tbody>
          </TableFrame>
        </div>

        <section class="grid gap-4 md:hidden">
          {#each organizations ?? [] as organization (organization.id)}
            <Card as="article" className="rounded-[1.25rem] p-5">
              <div class="flex items-start justify-between gap-3">
                <Link
                  class="min-w-0 break-words text-lg font-semibold ui-title"
                  href={`/admin/organizations/${organization.id}`}
                >
                  {organization.name}
                </Link>
                {#if organization.status === "deleting"}
                  <Badge tone="muted">deleting</Badge>
                {/if}
              </div>
              <p class="mt-1 text-sm ui-muted">/{organization.slug}</p>
              <div class="mt-4 grid gap-2 text-sm ui-muted">
                <p>Members: {organization.members_count}</p>
                <p>Invitations: {organization.invitations_count}</p>
                <p>
                  Created:
                  {organization.created_at
                    ? formatDate(organization.created_at)
                    : "-"}
                </p>
              </div>
            </Card>
          {/each}
        </section>
      {/if}
    </div>
  </div>
</AdminShell>
