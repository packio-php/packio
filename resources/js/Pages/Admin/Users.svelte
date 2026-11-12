<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import Link from "@/Components/core/Link.svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import TableFrame from "@/Components/ui/TableFrame.svelte";
  import { formatDate } from "@/lib/date";
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
  }: Props = $props();

  let verifyingUserIds = new Set<string>();

  function currentQuery(
    overrides: Partial<AdminUsersFilters> = {},
  ): Record<string, string | number> {
    return { ...filters, ...overrides };
  }

  function applyFilters(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);

    router.get(
      "/admin/users",
      currentQuery({
        search: String(formData.get("search") ?? ""),
        status: String(
          formData.get("status") ?? "",
        ) as AdminUsersFilters["status"],
        page: 1,
      }),
      { preserveScroll: true },
    );
  }

  function updateStatus(status: AdminUsersFilters["status"]): void {
    router.get("/admin/users", currentQuery({ status, page: 1 }), {
      preserveScroll: true,
    });
  }

  function handlePageChange(page: number): void {
    router.get("/admin/users", currentQuery({ page }), {
      preserveScroll: true,
    });
  }

  function hasFilters(): boolean {
    return filters.search !== "" || filters.status !== "";
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
    <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
      <SectionHeading
        eyebrow="Administration"
        title="Users"
        description="Local operator accounts provisioned for this Packio installation."
      />

      <form class="mt-6 flex flex-wrap items-end gap-3" onsubmit={applyFilters}>
        <label class="grid gap-2 text-sm ui-muted">
          <span>Search users</span>
          <input
            aria-label="Search users"
            class="rounded-lg border bg-transparent px-3 py-2 text-sm theme-border"
            name="search"
            placeholder="Email or display name"
            value={filters.search}
          />
        </label>
        <label class="grid gap-2 text-sm ui-muted">
          <span>Account status</span>
          <Select
            aria-label="Account status"
            className="rounded-lg px-3 py-2 text-sm"
            name="status"
            value={filters.status}
            onchange={(event) =>
              updateStatus(
                event.currentTarget.value as AdminUsersFilters["status"],
              )}
          >
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="banned">Banned</option>
          </Select>
        </label>
        <Button type="submit" variant="secondary">Apply</Button>
      </form>

      {#if users.length === 0}
        <div
          class="mt-6 rounded-2xl border px-4 py-3 text-sm ui-muted theme-border"
        >
          {hasFilters()
            ? "No users match the current filters."
            : "No users have been created yet."}
        </div>
      {:else}
        <div class="mt-6 grid gap-3 md:hidden">
          {#each users as user (user.id)}
            <div
              class="rounded-xl border p-4 theme-border"
              style="background-color: var(--surface-muted);"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="break-words font-medium ui-title">
                    {user.display_name}
                  </p>
                  <p class="mt-1 break-all text-xs ui-muted">
                    {user.email}
                  </p>
                </div>
                {#if user.email_verified}
                  <span
                    class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                    style="background-color: var(--accent-soft); color: var(--accent-ink);"
                  >
                    Yes
                  </span>
                {:else}
                  <span
                    class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                    style="background-color: var(--flash-error-bg); color: var(--flash-error-text);"
                  >
                    No
                  </span>
                {/if}
              </div>

              <div class="mt-4 flex flex-wrap gap-2">
                <span
                  class="rounded-full px-3 py-1 text-xs font-semibold"
                  style={user.is_banned
                    ? "background-color: var(--flash-error-bg); color: var(--flash-error-text);"
                    : "background-color: var(--accent-soft); color: var(--accent-ink);"}
                >
                  {user.is_banned ? "Banned" : "Active"}
                </span>
                {#each user.roles as role (role)}
                  <span
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    style={role === "admin"
                      ? "background-color: var(--flash-error-bg); color: var(--flash-error-text);"
                      : "background-color: var(--accent-soft); color: var(--accent-ink);"}
                  >
                    {role}
                  </span>
                {/each}
              </div>

              <dl class="mt-4 grid grid-cols-2 gap-3 text-xs ui-muted">
                <div>
                  <dt class="font-medium">Created</dt>
                  <dd class="mt-1">{formatDate(user.created_at)}</dd>
                </div>
                <div>
                  <dt class="font-medium">Updated</dt>
                  <dd class="mt-1">{formatDate(user.updated_at)}</dd>
                </div>
              </dl>

              <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <Link
                  class="ui-button-primary inline-flex w-full items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold no-underline transition sm:w-fit"
                  href={Endpoints.admin.user(user.id)}
                >
                  Edit
                </Link>

                {#if !user.email_verified}
                  <Button
                    type="button"
                    variant="chip"
                    disabled={isVerifying(user.id)}
                    className="w-full justify-center rounded-xl py-2 sm:w-fit"
                    onclick={() => verifyUser(user.id)}
                  >
                    {isVerifying(user.id) ? "Verifying..." : "Verify email"}
                  </Button>
                {/if}
              </div>
            </div>
          {/each}
        </div>

        <TableFrame
          ariaLabel="Admin users"
          className="mt-6 hidden rounded-[1.5rem] md:block"
          tableClassName="min-w-full"
        >
          <thead>
            <tr>
              <th class="px-4 py-3 text-left font-medium">User</th>
              <th class="px-4 py-3 text-left font-medium">Status</th>
              <th class="px-4 py-3 text-left font-medium">Verified</th>
              <th class="px-4 py-3 text-left font-medium">Roles</th>
              <th class="px-4 py-3 text-left font-medium">Created</th>
              <th class="px-4 py-3 text-left font-medium">Updated</th>
              <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {#each users as user (user.id)}
              <tr>
                <td class="px-4 py-3 align-top">
                  <div class="font-medium" style="color: var(--page-ink);">
                    {user.display_name}
                  </div>
                  <div class="mt-1 text-xs ui-muted">{user.email}</div>
                </td>
                <td class="px-4 py-3 align-top">
                  <span
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    style={user.is_banned
                      ? "background-color: var(--flash-error-bg); color: var(--flash-error-text);"
                      : "background-color: var(--accent-soft); color: var(--accent-ink);"}
                  >
                    {user.is_banned ? "Banned" : "Active"}
                  </span>
                </td>
                <td class="px-4 py-3 align-top">
                  {#if user.email_verified}
                    <span
                      class="rounded-full px-3 py-1 text-xs font-semibold"
                      style="background-color: var(--accent-soft); color: var(--accent-ink);"
                    >
                      Yes
                    </span>
                  {:else}
                    <span
                      class="rounded-full px-3 py-1 text-xs font-semibold"
                      style="background-color: var(--flash-error-bg); color: var(--flash-error-text);"
                    >
                      No
                    </span>
                  {/if}
                </td>
                <td class="px-4 py-3 align-top">
                  <div class="flex flex-wrap gap-2">
                    {#each user.roles as role (role)}
                      <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        style={role === "admin"
                          ? "background-color: var(--flash-error-bg); color: var(--flash-error-text);"
                          : "background-color: var(--accent-soft); color: var(--accent-ink);"}
                      >
                        {role}
                      </span>
                    {/each}
                  </div>
                </td>
                <td class="px-4 py-3 align-top ui-muted">
                  {formatDate(user.created_at)}
                </td>
                <td class="px-4 py-3 align-top ui-muted">
                  {formatDate(user.updated_at)}
                </td>
                <td class="px-4 py-3 text-right align-top">
                  <div class="flex justify-end gap-2">
                    <Link
                      class="ui-button-primary inline-flex items-center justify-center rounded-full px-3 py-1.5 text-xs font-semibold no-underline transition"
                      href={Endpoints.admin.user(user.id)}
                    >
                      Edit
                    </Link>

                    {#if !user.email_verified}
                      <Button
                        type="button"
                        variant="chip"
                        disabled={isVerifying(user.id)}
                        className="whitespace-nowrap"
                        onclick={() => verifyUser(user.id)}
                      >
                        {isVerifying(user.id) ? "Verifying..." : "Verify email"}
                      </Button>
                    {/if}
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </TableFrame>

        <div class="mt-6">
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
        </div>
      {/if}
    </Card>
  </div>
</AdminShell>
