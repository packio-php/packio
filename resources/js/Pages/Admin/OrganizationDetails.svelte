<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import Link from "@/Components/core/Link.svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import BackButton from "@/Components/ui/BackButton.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import Pagination from "@/Components/ui/Pagination.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { Endpoints } from "@/lib/api";
  import { formatDate } from "@/lib/date";
  import type { AuthPayload } from "@/types/auth";
  import type { AdminOrganizationDetailsPageProps } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    organization_record?: AdminOrganizationDetailsPageProps["organization_record"];
  }

  let { auth = {}, organization_record = null }: Props = $props();

  let deleteModalOpen = $state(false);
  let deleteConfirmationName = $state("");
  let deleteSubmitting = $state(false);

  let isDeleting = $derived(organization_record?.status === "deleting");
  let membersPagination = $derived(
    organization_record?.members_pagination ?? {
      items: [],
      total: 0,
      current_page: 1,
      per_page: 20,
      last_page: 1,
      has_more_pages: false,
      has_previous_pages: false,
      from: 0,
      to: 0,
    },
  );
  let canSubmitDelete = $derived(
    deleteConfirmationName === organization_record?.name &&
      !deleteSubmitting &&
      !isDeleting,
  );

  function statusTone(status: string | undefined): string {
    return status === "deleting" ? "danger" : "accent";
  }

  function roleTone(role: string): string {
    return role === "owner" ? "gold" : "muted";
  }

  function openDeleteModal(): void {
    deleteConfirmationName = "";
    deleteModalOpen = true;
  }

  function submitDelete(): void {
    if (!organization_record || deleteSubmitting || !canSubmitDelete) {
      return;
    }

    const successMessage = "Organization deletion queued.";
    deleteSubmitting = true;
    router.post(
      Endpoints.admin.organizationDelete(organization_record.id),
      { confirmationName: deleteConfirmationName },
      {
        preserveScroll: true,
        onSuccess: (page) => {
          // A redirect is also used for rejected typed outcomes. Only a known
          // success flash may close the modal, keeping the entered text visible
          // when the server rejects the confirmation.
          if (page.flash?.success === successMessage) {
            deleteModalOpen = false;
          }
        },
        onFinish: () => {
          deleteSubmitting = false;
        },
      },
    );
  }

  function changeMembersPage(page: number): void {
    if (!organization_record) {
      return;
    }

    router.get(
      `/admin/organizations/${organization_record.id}`,
      {
        members_page: page,
        members_per_page: membersPagination.per_page,
      },
      { preserveScroll: true },
    );
  }

  function changeMembersPerPage(perPage: number): void {
    if (!organization_record) {
      return;
    }

    router.get(
      `/admin/organizations/${organization_record.id}`,
      { members_page: 1, members_per_page: perPage },
      { preserveScroll: true },
    );
  }
</script>

<svelte:head>
  <title>Admin Organization Details</title>
</svelte:head>

<AdminShell title="Admin Organization" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    {#if !organization_record}
      <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
        <p class="text-sm ui-muted">Organization not found.</p>
      </Card>
    {:else}
      <div class="grid gap-6">
        <BackButton label="Back to organizations" href="/admin/organizations" />

        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <div
            class="grid gap-4 border-b pb-5 theme-border lg:grid-cols-[minmax(0,1fr)_auto]"
          >
            <SectionHeading
              eyebrow="Administration"
              title={organization_record.name}
              description="Read-only organization record and membership overview."
            />

            <div class="flex flex-wrap items-start gap-2 lg:justify-end">
              <Badge tone={statusTone(organization_record.status)}>
                {organization_record.status ?? "active"}
              </Badge>
              <Badge
                tone={organization_record.anonymous_access ? "accent" : "muted"}
              >
                {organization_record.anonymous_access
                  ? "Anonymous access"
                  : "Private registry"}
              </Badge>
            </div>
          </div>

          <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="ui-inset rounded-xl border p-4 theme-border">
              <p class="ui-eyebrow">Slug</p>
              <p class="mt-2 break-all text-sm font-semibold ui-title">
                /{organization_record.slug}
              </p>
            </div>
            <div class="ui-inset rounded-xl border p-4 theme-border">
              <p class="ui-eyebrow">Members</p>
              <p class="mt-2 text-2xl font-semibold ui-title">
                {organization_record.members_count}
              </p>
            </div>
            <div class="ui-inset rounded-xl border p-4 theme-border">
              <p class="ui-eyebrow">Invitations</p>
              <p class="mt-2 text-2xl font-semibold ui-title">
                {organization_record.invitations_count}
              </p>
            </div>
            <div class="ui-inset rounded-xl border p-4 theme-border">
              <p class="ui-eyebrow">Organization ID</p>
              <p class="mt-2 break-all text-sm font-semibold ui-title">
                {organization_record.id}
              </p>
            </div>
          </div>

          <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border p-4 theme-border">
              <p class="ui-eyebrow">Created</p>
              <p class="mt-2 text-sm ui-title">
                {formatDate(organization_record.created_at)}
              </p>
            </div>
            <div class="rounded-xl border p-4 theme-border">
              <p class="ui-eyebrow">Updated</p>
              <p class="mt-2 text-sm ui-title">
                {formatDate(organization_record.updated_at)}
              </p>
            </div>
          </div>
        </Card>

        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <SectionHeading
            eyebrow="Members"
            title="Users"
            description="Organization members are listed without membership controls. Open a user to view the global admin record."
          />

          {#if (organization_record.members?.length ?? 0) === 0}
            <div
              class="mt-4 rounded-xl border px-4 py-3 text-sm ui-muted theme-border"
            >
              No organization members found.
            </div>
          {:else}
            <div class="mt-4">
              <Pagination
                currentPage={membersPagination.current_page}
                lastPage={membersPagination.last_page}
                hasMorePages={membersPagination.has_more_pages}
                hasPreviousPages={membersPagination.has_previous_pages}
                from={membersPagination.from}
                to={membersPagination.to}
                total={membersPagination.total}
                perPage={membersPagination.per_page}
                onPageChange={changeMembersPage}
                onPerPageChange={changeMembersPerPage}
              />
            </div>
            <div class="mt-4 grid gap-3">
              {#each organization_record.members ?? [] as member (member.id)}
                <Link
                  class="block rounded-xl border p-4 no-underline transition hover:opacity-90 theme-border"
                  href={`/admin/users/${member.user_id}`}
                >
                  <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                  >
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <p class="break-words text-base font-semibold ui-title">
                          {member.display_name}
                        </p>
                        <Badge tone={roleTone(member.role)}>
                          {member.role}
                        </Badge>
                      </div>
                      <p class="mt-2 break-all text-sm ui-muted">
                        {member.email}
                      </p>
                    </div>
                    <div class="text-sm ui-muted sm:text-right">
                      <p>Joined</p>
                      <p class="mt-1">{formatDate(member.created_at)}</p>
                    </div>
                  </div>
                </Link>
              {/each}
            </div>
          {/if}
        </Card>

        <Card
          className="rounded-xl border border-(--flash-error-bg) p-4 sm:rounded-[1.75rem] sm:p-6"
        >
          <div
            class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start"
          >
            <SectionHeading
              eyebrow="Danger zone"
              title="Delete organization"
              description="Deletion runs as a staged background cleanup: members, invitations, registry tokens, sources, and packages are removed before the organization row is finalized."
            />

            <div class="flex flex-wrap gap-3 lg:justify-end">
              <Button
                className="w-full rounded-full px-5 sm:w-fit"
                disabled={isDeleting}
                type="button"
                variant="danger"
                onclick={openDeleteModal}
              >
                Delete organization
              </Button>
            </div>
          </div>

          {#if isDeleting}
            <p
              class="mt-4 rounded-xl border px-4 py-3 text-sm ui-muted theme-border"
            >
              This organization is already deleting. Staged cleanup is in
              progress and cannot be re-initiated from here.
            </p>
          {/if}
        </Card>
      </div>
    {/if}
  </div>
</AdminShell>

<ConfirmationModal
  open={deleteModalOpen}
  eyebrow="Danger zone"
  title="Delete organization?"
  description="This permanently removes the organization and all linked data. The action is queued as a staged background cleanup and cannot be undone."
  confirmLabel="Delete organization"
  confirming={deleteSubmitting}
  confirmDisabled={!canSubmitDelete}
  onConfirm={submitDelete}
  onCancel={() => (deleteModalOpen = false)}
>
  <div class="grid gap-3">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <div class="ui-inset rounded-xl border p-3 theme-border">
        <p class="ui-eyebrow">Sources</p>
        <p class="mt-2 text-xl font-semibold ui-title">
          {organization_record?.sources_count ?? 0}
        </p>
      </div>
      <div class="ui-inset rounded-xl border p-3 theme-border">
        <p class="ui-eyebrow">Packages</p>
        <p class="mt-2 text-xl font-semibold ui-title">
          {organization_record?.packages_count ?? 0}
        </p>
      </div>
      <div class="ui-inset rounded-xl border p-3 theme-border">
        <p class="ui-eyebrow">Members</p>
        <p class="mt-2 text-xl font-semibold ui-title">
          {organization_record?.members_count ?? 0}
        </p>
      </div>
      <div class="ui-inset rounded-xl border p-3 theme-border">
        <p class="ui-eyebrow">Invitations</p>
        <p class="mt-2 text-xl font-semibold ui-title">
          {organization_record?.invitations_count ?? 0}
        </p>
      </div>
    </div>

    <label class="grid gap-2 text-sm" for="delete-organization-name">
      <span class="font-medium ui-title">Typed confirmation</span>
      <input
        bind:value={deleteConfirmationName}
        class="ui-button-secondary rounded-xl px-3 py-2 text-sm"
        id="delete-organization-name"
        maxlength={255}
        placeholder="Type the exact organization name"
        type="text"
      />
      <span class="text-xs ui-muted">
        Type <strong>{organization_record?.name}</strong> to confirm.
      </span>
    </label>
  </div>
</ConfirmationModal>
