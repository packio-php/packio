<script lang="ts">
  import { router } from "@inertiajs/svelte";

  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import BackButton from "@/Components/ui/BackButton.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { formatDate } from "@/lib/date";
  import { Endpoints } from "@/lib/api";
  import { hasFieldErrors } from "@/lib/validationErrors";
  import type { AuthPayload } from "@/types/auth";
  import type {
    AdminUserDetailsPageProps,
    ValidationErrors,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    errors?: ValidationErrors;
    user_record?: AdminUserDetailsPageProps["user_record"];
    ban_history?: AdminUserDetailsPageProps["ban_history"];
  }

  let {
    auth = {},
    errors = {},
    user_record = null,
    ban_history = [],
  }: Props = $props();

  const defaultBanReason = "Violation of project rules.";
  const defaultUnbanNote = "Ban lifted by administrator.";
  let banModalOpen = $state(false);
  let unbanModalOpen = $state(false);
  let banReason = $state(defaultBanReason);
  let unbanNote = $state(defaultUnbanNote);
  let moderationSubmitting = $state(false);

  let isSelf = $derived(auth.user?.id === user_record?.id);
  let canBan = $derived(user_record?.can_ban ?? !isSelf);
  let canSubmitBan = $derived(banReason.trim().length >= 3 && canBan);
  let canSubmitUnban = $derived(unbanNote.trim().length >= 3);

  function updateUser(
    userId: string,
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    const form = event.currentTarget;
    const formData = new FormData(form);

    router.post(
      `/admin/users/${userId}`,
      {
        email: String(formData.get("email") ?? ""),
        display_name: String(formData.get("display_name") ?? ""),
        roles: formData.getAll("roles").map(String),
      },
      {
        preserveScroll: true,
      },
    );
  }

  function openBanModal(): void {
    banReason = defaultBanReason;
    banModalOpen = true;
  }

  function submitModeration(action: "ban" | "unban"): void {
    if (!user_record || moderationSubmitting) {
      return;
    }

    const isBan = action === "ban";
    const message = isBan ? "User banned." : "User unbanned.";
    const value = isBan ? banReason.trim() : unbanNote.trim();
    if (value.length < 3) {
      return;
    }

    moderationSubmitting = true;
    router.post(
      isBan
        ? Endpoints.admin.banUser(user_record.id)
        : Endpoints.admin.unbanUser(user_record.id),
      isBan ? { reason: value } : { note: value },
      {
        preserveScroll: true,
        onSuccess: (page) => {
          // A redirect is also used for rejected typed outcomes. Only a known
          // success flash may close the modal, keeping the entered text visible
          // when the server rejects a race-sensitive moderation action.
          if (page.flash?.success === message) {
            banModalOpen = false;
            unbanModalOpen = false;
          }
        },
        onFinish: () => {
          moderationSubmitting = false;
        },
      },
    );
  }

  function historyActionLabel(action: string): string {
    if (action === "identity.user.banned") {
      return "Banned";
    }

    if (action === "identity.user.unbanned") {
      return "Unbanned";
    }

    return "Unknown moderation action";
  }

  function historyActorLabel(actorId: string | null | undefined): string {
    return actorId ? `Administrator (${actorId})` : "Unknown administrator";
  }
</script>

<svelte:head>
  <title>Admin User Details</title>
</svelte:head>

<AdminShell title="Admin User" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    {#if !user_record}
      <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
        <p class="text-sm ui-muted">User not found.</p>
      </Card>
    {:else}
      <div class="grid gap-6">
        <BackButton label="Back to users" href="/admin/users" />

        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <div class="grid gap-4 border-b pb-5 theme-border">
            <SectionHeading
              eyebrow="Administration"
              title={user_record.display_name}
              description="Edit account identity and administrator access for this local user."
            />

            <div class="min-w-0">
              <p class="break-all text-sm ui-muted">{user_record.email}</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <span
                  class="rounded-full px-3 py-1 text-xs font-semibold"
                  style={user_record.email_verified
                    ? "background-color: var(--accent-soft); color: var(--accent-ink);"
                    : "background-color: var(--flash-error-bg); color: var(--flash-error-text);"}
                >
                  {user_record.email_verified ? "Verified" : "Unverified"}
                </span>
                {#each user_record.roles as role (role)}
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
            </div>
          </div>

          <div
            class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]"
          >
            <form
              class="grid gap-4"
              onsubmit={(event) => {
                event.preventDefault();
                updateUser(
                  user_record.id,
                  event as SubmitEvent & {
                    currentTarget: EventTarget & HTMLFormElement;
                  },
                );
              }}
            >
              <div class="grid gap-4 md:grid-cols-2">
                <label class="grid gap-2 text-sm">
                  <span class="font-medium" style="color: var(--page-ink);">
                    Display name
                  </span>
                  <Input
                    name="display_name"
                    type="text"
                    maxlength={100}
                    required
                    invalid={hasFieldErrors(errors, "display_name")}
                    value={user_record.display_name}
                  />
                  <FieldErrors {errors} field="display_name" />
                </label>

                <label class="grid gap-2 text-sm">
                  <span class="font-medium" style="color: var(--page-ink);">
                    Email
                  </span>
                  <Input
                    name="email"
                    type="email"
                    required
                    invalid={hasFieldErrors(errors, "email")}
                    value={user_record.email}
                  />
                  <FieldErrors {errors} field="email" />
                </label>
              </div>

              <fieldset
                class="grid gap-3 rounded-[1.25rem] border p-4 theme-border"
                style="background-color: var(--surface-muted);"
              >
                <legend class="px-1 text-sm font-semibold ui-title">
                  Roles
                </legend>
                <label class="flex items-center gap-3 text-sm ui-title">
                  <input
                    checked={user_record.roles.includes("user")}
                    class="h-4 w-4 rounded border-(--panel-border)"
                    name="roles"
                    type="checkbox"
                    value="user"
                  />
                  User
                </label>
                <label class="flex items-center gap-3 text-sm ui-title">
                  <input
                    checked={user_record.roles.includes("admin")}
                    class="h-4 w-4 rounded border-(--panel-border)"
                    name="roles"
                    type="checkbox"
                    value="admin"
                  />
                  Admin
                </label>
                <FieldErrors {errors} field="roles" />
              </fieldset>

              <div class="flex flex-wrap gap-3">
                <Button
                  className="w-full rounded-full px-5 sm:w-fit"
                  type="submit"
                  variant="primary"
                >
                  Save user
                </Button>
              </div>
            </form>

            <div class="ui-inset rounded-xl border p-4 sm:p-5 theme-border">
              <SectionHeading
                eyebrow="Account"
                title="Audit details"
                description="Immutable identifiers and timestamps for this user record."
              />

              <dl class="mt-5 grid gap-4 text-sm">
                <div>
                  <dt class="font-medium ui-title">User ID</dt>
                  <dd class="mt-1 break-all ui-muted">{user_record.id}</dd>
                </div>
                <div>
                  <dt class="font-medium ui-title">Created</dt>
                  <dd class="mt-1 ui-muted">
                    {formatDate(user_record.created_at)}
                  </dd>
                </div>
                <div>
                  <dt class="font-medium ui-title">Updated</dt>
                  <dd class="mt-1 ui-muted">
                    {formatDate(user_record.updated_at)}
                  </dd>
                </div>
              </dl>
            </div>
          </div>
        </Card>

        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <div
            class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start"
          >
            <SectionHeading
              eyebrow="Moderation"
              title="Account access"
              description="Ban immediately blocks this account from protected Packio resources."
            />

            <div class="flex flex-wrap gap-3 lg:justify-end">
              {#if user_record.is_banned}
                <Button
                  className="w-full rounded-full px-5 sm:w-fit"
                  variant="primary"
                  onclick={() => (unbanModalOpen = true)}
                >
                  Lift ban
                </Button>
              {:else}
                <Button
                  className="w-full rounded-full px-5 sm:w-fit"
                  disabled={!canBan}
                  variant="danger"
                  onclick={openBanModal}
                >
                  Ban user
                </Button>
              {/if}
            </div>
          </div>

          <div
            class="mt-5 grid gap-4 rounded-xl border p-4 theme-border ui-inset sm:grid-cols-3"
          >
            <div>
              <p
                class="text-xs font-semibold uppercase tracking-[0.14em] ui-muted"
              >
                Status
              </p>
              <p class="mt-1 font-semibold ui-title">
                {user_record.is_banned ? "Banned" : "Active"}
              </p>
            </div>
            <div>
              <p
                class="text-xs font-semibold uppercase tracking-[0.14em] ui-muted"
              >
                Banned at
              </p>
              <p class="mt-1 ui-muted">{formatDate(user_record.banned_at)}</p>
            </div>
            <div>
              <p
                class="text-xs font-semibold uppercase tracking-[0.14em] ui-muted"
              >
                Banned by
              </p>
              <p class="mt-1 break-all ui-muted">
                {user_record.banned_by_user_id ?? "—"}
              </p>
            </div>
            <div class="sm:col-span-3">
              <p
                class="text-xs font-semibold uppercase tracking-[0.14em] ui-muted"
              >
                Reason
              </p>
              <p class="mt-1 whitespace-pre-wrap ui-muted">
                {user_record.ban_reason ?? "—"}
              </p>
            </div>
          </div>

          {#if user_record.moderation_hint}
            <p class="mt-4 text-sm" style="color: var(--flash-error-text);">
              {user_record.moderation_hint}
            </p>
          {/if}

          <div class="mt-8 border-t pt-6 theme-border">
            <SectionHeading
              eyebrow="History"
              title="Moderation history"
              description="Recorded ban and unban actions for this account."
            />

            {#if ban_history.length === 0}
              <p class="mt-4 text-sm ui-muted">
                No moderation actions have been recorded.
              </p>
            {:else}
              <ol class="mt-5 grid gap-3">
                {#each ban_history as entry (entry.id)}
                  <li
                    class="rounded-xl border p-4 theme-border"
                    style="background-color: var(--surface-muted);"
                  >
                    <div
                      class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
                    >
                      <p class="font-semibold ui-title">
                        {historyActionLabel(entry.action)}
                      </p>
                      <time class="text-xs ui-muted"
                        >{formatDate(entry.occurred_at)}</time
                      >
                    </div>
                    <p class="mt-2 break-all text-sm ui-muted">
                      {historyActorLabel(entry.actor_id)}
                    </p>
                    <p class="mt-2 whitespace-pre-wrap text-sm ui-muted">
                      {entry.reason ?? "No reason recorded."}
                    </p>
                  </li>
                {/each}
              </ol>
            {/if}
          </div>
        </Card>
      </div>
    {/if}
  </div>
</AdminShell>

<ConfirmationModal
  open={banModalOpen}
  title="Ban user"
  description="The account will lose access to protected resources on its next request."
  confirmLabel="Ban user"
  confirmDisabled={!canSubmitBan}
  confirming={moderationSubmitting}
  onConfirm={() => submitModeration("ban")}
  onCancel={() => (banModalOpen = false)}
>
  <label class="grid gap-2 text-sm" for="ban-reason">
    <span class="font-medium ui-title">Reason</span>
    <textarea
      bind:value={banReason}
      class="min-h-28 rounded-xl border bg-transparent px-3 py-2 text-sm theme-border"
      id="ban-reason"
      maxlength={500}
      minlength={3}
      required
    ></textarea>
    <span class="text-xs ui-muted"
      >3–500 characters. This reason is shown to the user.</span
    >
  </label>
</ConfirmationModal>

<ConfirmationModal
  open={unbanModalOpen}
  title="Lift ban"
  description="This restores the account's existing access, roles, and valid credentials."
  confirmLabel="Lift ban"
  variant="primary"
  confirmDisabled={!canSubmitUnban}
  confirming={moderationSubmitting}
  onConfirm={() => submitModeration("unban")}
  onCancel={() => (unbanModalOpen = false)}
>
  <label class="grid gap-2 text-sm" for="unban-note">
    <span class="font-medium ui-title">Administrative note</span>
    <textarea
      bind:value={unbanNote}
      class="min-h-28 rounded-xl border bg-transparent px-3 py-2 text-sm theme-border"
      id="unban-note"
      maxlength={500}
      minlength={3}
      required
    ></textarea>
    <span class="text-xs ui-muted"
      >3–500 characters. This note is retained in moderation history.</span
    >
  </label>
</ConfirmationModal>
