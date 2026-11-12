<script lang="ts">
  import { router } from "@inertiajs/svelte";

  import AppShell from "@/Layouts/AppShell.svelte";
  import Link from "@/Components/core/Link.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import Select from "@/Components/ui/Select.svelte";
  import { formatDate } from "@/lib/date";
  import type { AuthPayload } from "@/types/auth";
  import type { OrganizationSummary } from "@/types/organization";
  import type {
    OrganizationInvitationListItem,
    OrganizationMemberListItem,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    organization?: OrganizationSummary | null;
    members?: OrganizationMemberListItem[];
    can_manage_members?: boolean;
    invitations?: OrganizationInvitationListItem[];
    owners_count?: number;
  }

  let {
    auth = {},
    organization = null,
    members = [],
    can_manage_members = false,
    invitations = [],
    owners_count = 0,
  }: Props = $props();
  let inviteRole: "member" | "owner" = $state("member");
  let copiedInvitationId: string | null = $state(null);
  let copiedResetTimeout: ReturnType<typeof setTimeout> | null = null;
  let memberPendingRemove: OrganizationMemberListItem | null = $state(null);
  let memberRemoving = $state(false);
  let memberPendingRoleChange: OrganizationMemberListItem | null = $state(null);
  let pendingRole: "member" | "owner" | null = $state(null);
  let roleUpdating = $state(false);
  let invitePendingRevoke: OrganizationInvitationListItem | null = $state(null);
  let inviteRevoking = $state(false);
  let invitePendingDelete: OrganizationInvitationListItem | null = $state(null);
  let inviteDeleting = $state(false);

  function withOrganization(path: string): string {
    if (!organization?.slug) {
      return path;
    }

    return path === "/"
      ? `/organization/${organization.slug}`
      : `/organization/${organization.slug}${path}`;
  }

  function createInvite(): void {
    router.post(
      withOrganization("/invitations"),
      { role: inviteRole },
      {
        preserveScroll: true,
      },
    );
  }

  function confirmMemberRemove(): void {
    const member = memberPendingRemove;
    if (!member || memberRemoving) {
      return;
    }

    memberRemoving = true;
    router.post(
      withOrganization(`/members/${member.id}/remove`),
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          memberRemoving = false;
          memberPendingRemove = null;
        },
      },
    );
  }

  function openRoleChange(
    member: OrganizationMemberListItem,
    role: "member" | "owner",
  ): void {
    memberPendingRoleChange = member;
    pendingRole = role;
  }

  function confirmRoleChange(): void {
    const member = memberPendingRoleChange;
    const role = pendingRole;
    if (!member || !role || roleUpdating) {
      return;
    }

    roleUpdating = true;
    router.post(
      withOrganization(`/members/${member.id}/role`),
      { role },
      {
        preserveScroll: true,
        onFinish: () => {
          roleUpdating = false;
          memberPendingRoleChange = null;
          pendingRole = null;
        },
      },
    );
  }

  function confirmInviteRevoke(): void {
    const invite = invitePendingRevoke;
    if (!invite || inviteRevoking) {
      return;
    }

    inviteRevoking = true;
    router.post(
      withOrganization(`/invitations/${invite.id}/revoke`),
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          inviteRevoking = false;
          invitePendingRevoke = null;
        },
      },
    );
  }

  function confirmInviteDelete(): void {
    const invite = invitePendingDelete;
    if (!invite || inviteDeleting) {
      return;
    }

    inviteDeleting = true;
    router.post(
      withOrganization(`/invitations/${invite.id}/delete`),
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          inviteDeleting = false;
          invitePendingDelete = null;
        },
      },
    );
  }

  async function copy(invitationId: string, text: string): Promise<void> {
    if (!text || !navigator?.clipboard) {
      return;
    }

    await navigator.clipboard.writeText(text);
    copiedInvitationId = invitationId;

    if (copiedResetTimeout) {
      clearTimeout(copiedResetTimeout);
    }

    copiedResetTimeout = setTimeout(() => {
      copiedInvitationId = null;
    }, 1500);
  }
</script>

<svelte:head>
  <title>Organization Members</title>
</svelte:head>

<AppShell title="Organization Members" {auth}>
  <section class="grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
    <Card className="rounded-[1.75rem] p-6">
      <SectionHeading
        eyebrow="Members"
        title={organization?.name || "Organization"}
        description="Owners can manage the current team. Invite flow comes next; this page focuses on visibility and owner-safe removal."
        className="max-w-2xl"
      />

      <div class="mt-6 grid gap-3">
        {#if members.length === 0}
          <p class="text-sm ui-muted">No organization members found.</p>
        {:else}
          {#each members as member (member.id)}
            <InsetCard as="article" className="p-5">
              <div
                class="flex min-w-0 flex-col items-start justify-between gap-4 sm:flex-row"
              >
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="ui-title text-lg font-semibold">
                      {member.display_name}
                    </p>
                    <span
                      class={`rounded-full px-3 py-1 text-xs font-medium ${
                        member.role === "owner"
                          ? "ui-badge-gold"
                          : "ui-badge-muted"
                      }`}
                    >
                      {member.role}
                    </span>
                    {#if member.is_current_user}
                      <span
                        class="ui-badge-accent rounded-full px-3 py-1 text-xs font-medium"
                      >
                        you
                      </span>
                    {/if}
                  </div>
                  <p class="ui-muted mt-2 break-words text-sm">
                    {member.email}
                  </p>
                  <p class="ui-muted mt-1 text-sm">
                    joined {formatDate(member.created_at)}
                  </p>
                </div>

                <div class="flex shrink-0 flex-wrap justify-end gap-2">
                  {#if member.can_promote}
                    <Button
                      className="rounded-full px-3 py-1.5 text-xs"
                      type="button"
                      variant="chip"
                      onclick={() => openRoleChange(member, "owner")}
                    >
                      Make owner
                    </Button>
                  {/if}

                  {#if member.can_demote}
                    <Button
                      className="rounded-full px-3 py-1.5 text-xs"
                      type="button"
                      variant="chip"
                      onclick={() => openRoleChange(member, "member")}
                    >
                      Make member
                    </Button>
                  {/if}

                  <Button
                    className="rounded-full px-3 py-1.5 text-xs"
                    type="button"
                    variant="chipDanger"
                    disabled={!member.can_remove}
                    onclick={() => (memberPendingRemove = member)}
                  >
                    Remove
                  </Button>
                </div>
              </div>

              {#if member.role === "owner" && !member.can_remove && !member.is_current_user}
                <p
                  class="mt-3 text-sm"
                  style="color: var(--flash-warning-text, var(--flash-error-text));"
                >
                  The last owner cannot be removed.
                </p>
              {/if}
              {#if member.is_current_user}
                <p class="mt-3 text-sm ui-muted">
                  You cannot change your own membership.
                </p>
              {/if}
            </InsetCard>
          {/each}
        {/if}
      </div>
    </Card>

    <div class="grid gap-6">
      <Card className="rounded-[1.75rem] p-6">
        <SectionHeading eyebrow="Membership rules" className="mb-4" />
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-1">
          <InsetCard className="p-4">
            <p class="ui-eyebrow">Owners</p>
            <p class="ui-title mt-2 text-3xl font-semibold">
              {owners_count}
            </p>
          </InsetCard>
          <InsetCard className="p-4 text-sm ui-muted">
            <p class="font-medium ui-title">Current scope</p>
            <p class="mt-2">
              Owners can manage members and issue invitation links.
            </p>
          </InsetCard>
        </div>
      </Card>

      {#if can_manage_members}
        <Card className="rounded-[1.75rem] p-6">
          <SectionHeading eyebrow="Invites" className="mb-4" />
          <div class="mt-4 flex flex-wrap items-end gap-3">
            <label class="grid gap-2">
              <span class="text-sm font-medium ui-title">Role</span>
              <Select bind:value={inviteRole} name="role">
                <option value="member">Member</option>
                <option value="owner">Owner</option>
              </Select>
            </label>
            <Button
              className="rounded-full px-4 py-2 text-sm"
              type="button"
              variant="primary"
              onclick={createInvite}
            >
              Create invite
            </Button>
          </div>

          <div class="mt-4 grid gap-3">
            {#if invitations.length === 0}
              <p class="text-sm ui-muted">
                No organization invites created yet.
              </p>
            {:else}
              {#each invitations as invite (invite.id)}
                <InsetCard className="p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <span
                          class={`rounded-full px-3 py-1 text-xs font-medium ${
                            invite.status === "pending"
                              ? "ui-badge-accent"
                              : invite.status === "accepted"
                                ? "ui-badge-gold"
                                : "ui-badge-muted"
                          }`}
                        >
                          {invite.status}
                        </span>
                        <span
                          class="ui-code rounded-full px-3 py-1 text-xs font-medium"
                        >
                          {invite.role}
                        </span>
                      </div>
                      <p class="ui-muted mt-2 text-sm">
                        invited by {invite.invited_by}
                      </p>
                      <p class="ui-muted mt-1 text-sm">
                        expires {formatDate(invite.expires_at)}
                      </p>
                    </div>
                    {#if invite.status === "pending"}
                      <Button
                        className="rounded-full px-3 py-1.5 text-xs"
                        type="button"
                        variant="chip"
                        onclick={() => (invitePendingRevoke = invite)}
                      >
                        Revoke
                      </Button>
                    {:else}
                      <Button
                        className="rounded-full px-3 py-1.5 text-xs"
                        type="button"
                        variant="chip"
                        onclick={() => (invitePendingDelete = invite)}
                      >
                        Delete
                      </Button>
                    {/if}
                  </div>

                  {#if invite.accept_url}
                    {@const acceptUrl = invite.accept_url}
                    <div class="mt-3 flex gap-2">
                      <Button
                        className="rounded-full px-3 py-1.5 text-xs"
                        type="button"
                        variant="chip"
                        onclick={() => copy(invite.id, acceptUrl)}
                      >
                        {copiedInvitationId === invite.id
                          ? "Copied"
                          : "Copy link"}
                      </Button>
                    </div>
                  {/if}
                </InsetCard>
              {/each}
            {/if}
          </div>
        </Card>
      {/if}

      <Card className="rounded-[1.75rem] p-6">
        <SectionHeading eyebrow="Next step" className="mb-4" />
        <p class="ui-muted text-sm">
          Review anonymous access and organization settings.
        </p>
        <Link
          class="ui-button-primary mt-4 inline-flex h-11 items-center justify-center rounded-lg px-5 text-sm font-semibold no-underline"
          href={withOrganization("/settings")}
        >
          Open settings
        </Link>
      </Card>
    </div>
  </section>

  <ConfirmationModal
    open={memberPendingRemove !== null}
    eyebrow="Organization member"
    title="Remove member?"
    description="This removes the user from the organization and revokes their organization access. Registry tokens are shared by the organization: reissue registry tokens the user may have copied."
    confirmLabel="Remove member"
    cancelLabel="Keep member"
    confirming={memberRemoving}
    onCancel={() => (memberPendingRemove = null)}
    onConfirm={confirmMemberRemove}
  >
    {#if memberPendingRemove}
      <div class="ui-inset grid gap-2 rounded-xl border p-4">
        <p class="font-medium ui-title">
          {memberPendingRemove.display_name}
        </p>
        <p class="break-all text-xs ui-muted">{memberPendingRemove.email}</p>
        <span
          class="ui-badge-muted w-fit rounded-full px-2.5 py-1 text-[11px] font-medium"
        >
          {memberPendingRemove.role}
        </span>
      </div>
    {/if}
  </ConfirmationModal>

  <ConfirmationModal
    open={memberPendingRoleChange !== null}
    eyebrow="Organization member"
    title={pendingRole === "owner" ? "Make owner?" : "Make member?"}
    description={pendingRole === "owner"
      ? "This grants owner permissions for the organization."
      : "This removes owner permissions for the organization."}
    confirmLabel={pendingRole === "owner" ? "Make owner" : "Make member"}
    cancelLabel="Keep role"
    variant="primary"
    confirming={roleUpdating}
    onCancel={() => {
      memberPendingRoleChange = null;
      pendingRole = null;
    }}
    onConfirm={confirmRoleChange}
  >
    {#if memberPendingRoleChange}
      <div class="ui-inset grid gap-2 rounded-xl border p-4">
        <p class="font-medium ui-title">
          {memberPendingRoleChange.display_name}
        </p>
        <p class="break-all text-xs ui-muted">
          {memberPendingRoleChange.email}
        </p>
        <div class="flex flex-wrap gap-2">
          <span
            class="ui-badge-muted w-fit rounded-full px-2.5 py-1 text-[11px] font-medium"
          >
            Current: {memberPendingRoleChange.role}
          </span>
          {#if pendingRole}
            <span
              class="ui-badge-accent w-fit rounded-full px-2.5 py-1 text-[11px] font-medium"
            >
              New: {pendingRole}
            </span>
          {/if}
        </div>
      </div>
    {/if}
  </ConfirmationModal>

  <ConfirmationModal
    open={invitePendingRevoke !== null}
    eyebrow="Organization invite"
    title="Revoke invite?"
    description="This immediately invalidates the invitation link. The recipient will no longer be able to join the organization."
    confirmLabel="Revoke invite"
    cancelLabel="Keep invite"
    confirming={inviteRevoking}
    onCancel={() => (invitePendingRevoke = null)}
    onConfirm={confirmInviteRevoke}
  >
    {#if invitePendingRevoke}
      <div class="ui-inset grid gap-2 rounded-xl border p-4">
        <div class="flex flex-wrap items-center gap-2">
          <span
            class="ui-badge-accent w-fit rounded-full px-2.5 py-1 text-[11px] font-medium"
          >
            {invitePendingRevoke.role}
          </span>
          <span
            class="ui-badge-muted w-fit rounded-full px-2.5 py-1 text-[11px] font-medium"
          >
            invited by {invitePendingRevoke.invited_by}
          </span>
        </div>
        <p class="text-xs ui-muted">
          expires {formatDate(invitePendingRevoke.expires_at)}
        </p>
      </div>
    {/if}
  </ConfirmationModal>

  <ConfirmationModal
    open={invitePendingDelete !== null}
    eyebrow="Organization invite"
    title="Delete invite?"
    description="This permanently removes the invitation record from the organization."
    confirmLabel="Delete invite"
    cancelLabel="Keep record"
    confirming={inviteDeleting}
    onCancel={() => (invitePendingDelete = null)}
    onConfirm={confirmInviteDelete}
  >
    {#if invitePendingDelete}
      <div class="ui-inset grid gap-2 rounded-xl border p-4">
        <div class="flex flex-wrap items-center gap-2">
          <span
            class="ui-badge-muted w-fit rounded-full px-2.5 py-1 text-[11px] font-medium"
          >
            {invitePendingDelete.role}
          </span>
          <span
            class="ui-badge-muted w-fit rounded-full px-2.5 py-1 text-[11px] font-medium"
          >
            invited by {invitePendingDelete.invited_by}
          </span>
        </div>
        <p class="text-xs ui-muted">
          expires {formatDate(invitePendingDelete.expires_at)}
        </p>
      </div>
    {/if}
  </ConfirmationModal>
</AppShell>
