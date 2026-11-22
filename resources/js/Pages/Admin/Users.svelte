<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import { FilterX, ListFilter, Search } from "@lucide/svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Link from "@/Components/core/Link.svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import { formatDate } from "@/lib/date";
  import { cleanQuery } from "@/lib/queryFilters";
  import { apiClient, Endpoints } from "@/lib/api";
  import { toast } from "@/lib/toast";
  import type { AuthPayload } from "@/types/auth";
  import type {
    AdminUsersFilters,
    AdminUsersPageProps,
    AdminUsersPaginationMeta,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    users?: AdminUsersPageProps["users"];
    filters?: AdminUsersPageProps["filters"];
    pagination?: AdminUsersPageProps["pagination"];
    stats?: AdminUsersPageProps["stats"];
  }

  const defaultFilters: AdminUsersFilters = {
    search: "",
    status: "",
    page: 1,
    per_page: 20,
  };
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
    users = [],
    filters = defaultFilters,
    pagination = defaultPagination,
    stats = { users_count: 0, admins_count: 0 },
  }: Props = $props();

  let verifyingUserIds = $state(new Set<string>());

  function currentQuery(
    overrides: Partial<AdminUsersFilters> = {},
  ): Record<string, string | number> {
    return { ...filters, ...overrides };
  }

  let filtersOpen = $state(false);
  let activeFilterCount = $derived(
    (filters.search !== "" ? 1 : 0) + (filters.status !== "" ? 1 : 0),
  );

  function applyFilters(event: SubmitEvent): void {
    const formData = new FormData(event.currentTarget as HTMLFormElement);

    router.get(
      "/admin/users",
      cleanQuery({
        search: String(formData.get("search") ?? ""),
        status: String(formData.get("status") ?? ""),
        page: 1,
        per_page: pagination.per_page,
      }),
      { preserveScroll: true },
    );
  }

  function clearFilters(): void {
    router.get(
      "/admin/users",
      { per_page: pagination.per_page },
      { preserveScroll: true },
    );
  }

  function handlePageChange(page: number): void {
    router.get("/admin/users", currentQuery({ page }), {
      preserveScroll: true,
    });
  }

  function handlePerPageChange(perPage: number): void {
    router.get("/admin/users", currentQuery({ page: 1, per_page: perPage }), {
      preserveScroll: true,
    });
  }

  function hasFilters(): boolean {
    return activeFilterCount > 0;
  }

  function isVerifying(userId: string): boolean {
    return verifyingUserIds.has(userId);
  }

  async function verifyUser(userId: string): Promise<void> {
    if (isVerifying(userId)) {
      return;
    }

    verifyingUserIds = new Set([...verifyingUserIds, userId]);
    const result = await apiClient.post(Endpoints.admin.verifyUser(userId));

    if (result.ok) {
      router.reload({ only: ["users", "pagination", "filters"] });

      return;
    }

    verifyingUserIds = new Set(
      [...verifyingUserIds].filter(
        (verifyingUserId) => verifyingUserId !== userId,
      ),
    );
    toast.error(result.error);
  }
</script>

<svelte:head>
  <title>Admin Users</title>
</svelte:head>

<AdminShell title="Admin Users" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    <div class="grid min-w-0 gap-6">
      <SectionHeading
        eyebrow="Administration"
        title="Users"
        description="Local operator accounts provisioned for this Packio installation."
      />

      <div class="grid gap-3 md:grid-cols-4">
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Users</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {stats.users_count}
          </p>
        </div>
        <div class="ui-inset rounded-xl border p-4 theme-border">
          <p class="text-xs uppercase tracking-[0.18em] ui-muted">Admins</p>
          <p class="mt-2 text-2xl font-semibold ui-title">
            {stats.admins_count}
          </p>
        </div>
      </div>

      <Card className="rounded-[1.25rem] p-4">
        <div class="grid gap-4">
          <div class="flex items-center gap-2">
            <div class="relative">
              <Button
                aria-controls="admin-user-filters"
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
              id="admin-user-filters"
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
                    placeholder="Email or display name"
                    type="search"
                    value={filters.search}
                  />
                </label>
                <label class="grid gap-2 text-sm">
                  <span class="font-medium ui-title">Account status</span>
                  <Select
                    aria-label="Account status"
                    className="rounded-xl px-3 py-2 text-sm"
                    name="status"
                    value={filters.status}
                  >
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="banned">Banned</option>
                  </Select>
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

      {#if users.length === 0}
        <p class="text-sm ui-muted">
          {hasFilters()
            ? "No users match the current filters."
            : "No users have been created yet."}
        </p>
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
          onPerPageChange={handlePerPageChange}
        />

        <div class="hidden md:block">
          <TableFrame ariaLabel="Admin users" tableClassName="min-w-[840px]">
            <thead>
              <tr>
                <th class="px-4 py-3">User</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Verified</th>
                <th class="px-4 py-3">Roles</th>
                <th class="px-4 py-3">Created</th>
                <th class="px-4 py-3">Updated</th>
                <th class="px-4 py-3">Last sign-in</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {#each users as user (user.id)}
                <tr class="align-top">
                  <td class="px-4 py-3">
                    <div class="font-semibold ui-title">
                      {user.display_name}
                    </div>
                    <div class="mt-1 text-xs ui-muted">{user.email}</div>
                  </td>
                  <td class="px-4 py-3">
                    <Badge tone={user.is_banned ? "danger" : "accent"}>
                      {user.is_banned ? "Banned" : "Active"}
                    </Badge>
                  </td>
                  <td class="px-4 py-3">
                    <Badge tone={user.email_verified ? "accent" : "gold"}>
                      {user.email_verified ? "Verified" : "Unverified"}
                    </Badge>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-2">
                      {#each user.roles as role (role)}
                        <Badge tone={role === "admin" ? "gold" : "muted"}>
                          {role}
                        </Badge>
                      {/each}
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm ui-muted">
                    {formatDate(user.created_at)}
                  </td>
                  <td class="px-4 py-3 text-sm ui-muted">
                    {formatDate(user.updated_at)}
                  </td>
                  <td class="px-4 py-3 text-sm ui-muted">
                    {formatDate(user.last_sign_in_at)}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                      <Link
                        class="ui-button-secondary inline-flex items-center rounded-full border px-3 py-2 text-sm font-medium transition"
                        href={Endpoints.admin.user(user.id)}
                      >
                        Edit
                      </Link>
                      {#if !user.email_verified}
                        <Button
                          className="whitespace-nowrap rounded-full"
                          disabled={isVerifying(user.id)}
                          type="button"
                          variant="soft"
                          onclick={() => verifyUser(user.id)}
                        >
                          {isVerifying(user.id)
                            ? "Verifying..."
                            : "Verify email"}
                        </Button>
                      {/if}
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </TableFrame>
        </div>

        <section class="grid gap-4 md:hidden">
          {#each users as user (user.id)}
            <Card as="article" className="rounded-[1.25rem] p-5">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="break-words text-lg font-semibold ui-title">
                    {user.display_name}
                  </p>
                  <p class="mt-1 break-all text-xs ui-muted">{user.email}</p>
                </div>
                <Badge tone={user.email_verified ? "accent" : "gold"}>
                  {user.email_verified ? "Verified" : "Unverified"}
                </Badge>
              </div>
              <div class="mt-4 flex flex-wrap gap-2">
                <Badge tone={user.is_banned ? "danger" : "accent"}>
                  {user.is_banned ? "Banned" : "Active"}
                </Badge>
                {#each user.roles as role (role)}
                  <Badge tone={role === "admin" ? "gold" : "muted"}>
                    {role}
                  </Badge>
                {/each}
              </div>
              <div class="mt-4 grid gap-2 text-sm ui-muted">
                <p>Created: {formatDate(user.created_at)}</p>
                <p>Updated: {formatDate(user.updated_at)}</p>
                <p>Last sign-in: {formatDate(user.last_sign_in_at)}</p>
              </div>
              <div class="mt-4 flex flex-wrap gap-2">
                <Link
                  class="ui-button-secondary inline-flex items-center rounded-full border px-3 py-2 text-sm font-medium transition"
                  href={Endpoints.admin.user(user.id)}
                >
                  Edit
                </Link>
                {#if !user.email_verified}
                  <Button
                    className="rounded-full"
                    disabled={isVerifying(user.id)}
                    type="button"
                    variant="soft"
                    onclick={() => verifyUser(user.id)}
                  >
                    {isVerifying(user.id) ? "Verifying..." : "Verify email"}
                  </Button>
                {/if}
              </div>
            </Card>
          {/each}
        </section>

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
          onPerPageChange={handlePerPageChange}
        />
      {/if}
    </div>
  </div>
</AdminShell>
