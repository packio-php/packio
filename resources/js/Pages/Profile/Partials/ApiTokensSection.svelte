<script lang="ts">
  import { ExternalLink } from "@lucide/svelte";
  import { router, usePage } from "@inertiajs/svelte";
  import { tick } from "svelte";
  import { Endpoints } from "@/lib/api";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { addCalendarMonths, browserTimeZone, formatDate } from "@/lib/date";
  import { hasFieldErrors } from "@/lib/validationErrors";
  import type { AppPageProps } from "@/types/app";
  import type { PackioFlashPayload } from "@/types/flash";
  import type {
    ApiAccessTokenPayload,
    ProfilePayload,
    ValidationErrors,
  } from "@/types/pages";
  import IssuedApiToken from "./IssuedApiToken.svelte";

  interface Props {
    profile?: ProfilePayload | null;
    api_tokens?: ApiAccessTokenPayload[];
  }

  let { profile = null, api_tokens = [] }: Props = $props();

  const expirationOptions = [
    { value: "1-month", label: "1 month", months: 1 },
    { value: "3-months", label: "3 months", months: 3 },
    { value: "6-months", label: "6 months", months: 6 },
    { value: "1-year", label: "1 year", months: 12 },
    { value: "custom", label: "Custom date", months: null },
    { value: "never", label: "No expiration", months: null },
  ] as const;

  const defaultScopes = { read: true, write: false, admin: false };

  let formOpen = $state(false);
  let apiTokenName = $state("");
  let expiration =
    $state<(typeof expirationOptions)[number]["value"]>("1-month");
  let customExpiresAt = $state("");
  let apiTokenScopes = $state({ ...defaultScopes });
  let submitting = $state(false);
  let clientErrors = $state<ValidationErrors>({});
  let dismissedToken = $state("");
  let apiTokenPendingRevoke: ApiAccessTokenPayload | null = $state(null);
  let apiTokenPendingDelete: ApiAccessTokenPayload | null = $state(null);
  let apiTokenRevoking = $state(false);
  let apiTokenDeleting = $state(false);
  let nameInput = $state<HTMLInputElement | null>(null);

  const page = usePage<AppPageProps>();
  let errors = $derived({
    ...((page.props?.errors ?? {}) as unknown as ValidationErrors),
    ...clientErrors,
  });
  let issued = $derived(
    (page as { flash?: PackioFlashPayload }).flash?.issued_api_token ?? null,
  );
  let canIssueAdminApiToken = $derived(
    profile?.roles?.includes("admin") ?? false,
  );
  let presetMonths = $derived(
    expirationOptions.find((option) => option.value === expiration)?.months ??
      null,
  );
  let presetDate = $derived(
    presetMonths === null ? null : addCalendarMonths(new Date(), presetMonths),
  );

  async function openForm(): Promise<void> {
    formOpen = true;
    await tick();
    nameInput?.focus();
  }

  async function closeForm(): Promise<void> {
    resetForm();
    await focusCreateButton();
  }

  async function focusCreateButton(): Promise<void> {
    await tick();
    document.getElementById("api-token-create")?.focus();
  }

  async function dismissIssued(token: string): Promise<void> {
    dismissedToken = token;
    await focusCreateButton();
  }

  function resetForm(): void {
    formOpen = false;
    apiTokenName = "";
    expiration = "1-month";
    customExpiresAt = "";
    apiTokenScopes = { ...defaultScopes };
    clientErrors = {};
  }

  // Returns the ISO value, "" for no expiration, or null when the input is invalid.
  function expiresAtForRequest(): string | null {
    if (expiration === "never") return "";
    if (expiration === "custom") {
      const date = new Date(customExpiresAt);
      if (!customExpiresAt || Number.isNaN(date.getTime())) {
        clientErrors.expires_at = ["Choose an expiration date."];
        return null;
      }
      if (date.getTime() <= Date.now()) {
        clientErrors.expires_at = ["Expiration date must be in the future."];
        return null;
      }
      return date.toISOString();
    }

    // Send exactly the date shown to the user, not a fresh recomputation.
    return presetDate?.toISOString() ?? "";
  }

  function submitApiToken(): void {
    if (submitting) return;
    clientErrors = {};
    if (!apiTokenName.trim()) {
      clientErrors.name = ["Token name is required."];
    }
    const expiresAt = expiresAtForRequest();
    if (expiresAt === null || clientErrors.name) return;

    const scopes = Object.entries(apiTokenScopes)
      .filter(([, enabled]) => enabled)
      .map(([scope]) => scope)
      .filter((scope) => scope !== "admin" || canIssueAdminApiToken);

    router.post(
      Endpoints.profile.issueApiToken(),
      { name: apiTokenName, scopes, expires_at: expiresAt },
      {
        preserveScroll: true,
        preserveState: "errors",
        onStart: () => (submitting = true),
        onFinish: () => (submitting = false),
        onSuccess: resetForm,
      },
    );
  }

  function confirmApiTokenRevoke(): void {
    const token = apiTokenPendingRevoke;
    if (!token || apiTokenRevoking) {
      return;
    }

    apiTokenRevoking = true;
    router.post(
      Endpoints.profile.revokeApiToken(token.id),
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          apiTokenRevoking = false;
          apiTokenPendingRevoke = null;
        },
      },
    );
  }

  function confirmApiTokenDelete(): void {
    const token = apiTokenPendingDelete;
    if (!token || apiTokenDeleting) {
      return;
    }

    apiTokenDeleting = true;
    router.delete(Endpoints.profile.deleteApiToken(token.id), {
      preserveScroll: true,
      onFinish: () => {
        apiTokenDeleting = false;
        apiTokenPendingDelete = null;
      },
    });
  }

  function scopeLabel(scope: string): string {
    if (scope === "read") {
      return "Read";
    }

    if (scope === "write") {
      return "Write";
    }

    if (scope === "admin") {
      return "Admin";
    }

    return scope;
  }
</script>

<div
  id="api-tokens"
  class="min-w-0 scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)]"
>
  <Card className="rounded-[1.75rem] p-6">
    <SectionHeading
      eyebrow="API"
      title="API tokens"
      titleTag="h3"
      description="Create personal tokens for REST API automation."
    />

    <div class="mt-6 grid gap-3">
      {#if api_tokens.length === 0}
        <div class="rounded-xl border border-dashed px-4 py-6">
          <p class="text-sm ui-muted">No API tokens yet</p>
        </div>
      {:else}
        {#each api_tokens as token (token.id)}
          <InsetCard as="article" className="p-4">
            <div
              class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
            >
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold ui-title">{token.name}</p>
                  <Badge tone={token.revoked ? "danger" : "accent"}>
                    {token.revoked ? "Revoked" : "Active"}
                  </Badge>
                </div>

                <div
                  class="mt-3 flex min-w-0 items-center rounded-xl border px-3 py-2 theme-border"
                  style="background-color: var(--surface-muted);"
                >
                  <code class="min-w-0 truncate font-mono text-xs ui-muted">
                    {token.token_prefix}...
                  </code>
                </div>
              </div>

              {#if token.revoked}
                <Button
                  className="w-fit"
                  variant="chipDanger"
                  type="button"
                  onclick={() => (apiTokenPendingDelete = token)}
                >
                  Delete
                </Button>
              {:else}
                <Button
                  className="w-fit"
                  variant="chipDanger"
                  type="button"
                  onclick={() => (apiTokenPendingRevoke = token)}
                >
                  Revoke
                </Button>
              {/if}
            </div>

            <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
              <div class="grid gap-1 text-xs ui-muted">
                <p>Created {formatDate(token.created_at)}</p>
                <p>
                  {token.last_used_at
                    ? `Last used ${formatDate(token.last_used_at)}`
                    : "Never used"}
                </p>
                <p>
                  {token.expires_at
                    ? `Expires ${formatDate(token.expires_at)}`
                    : "Never expires"}
                </p>
                {#if token.revoked_at}
                  <p>Revoked {formatDate(token.revoked_at)}</p>
                {/if}
              </div>

              <div class="flex flex-wrap gap-1.5">
                {#each token.scopes as scope (scope)}
                  <span
                    class="ui-code rounded-full px-2.5 py-1 text-xs font-medium"
                  >
                    {scopeLabel(scope)}
                  </span>
                {/each}
              </div>
            </div>
          </InsetCard>
        {/each}
      {/if}
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3">
      <Button
        id="api-token-create"
        type="button"
        variant="primary"
        onclick={openForm}
      >
        Create API token
      </Button>
      <a
        class="inline-flex items-center gap-1.5 text-sm font-medium underline-offset-4 hover:underline ui-muted"
        href="/api/doc"
        rel="external noopener noreferrer"
        target="_blank"
      >
        API documentation
        <ExternalLink class="size-3.5" aria-hidden="true" />
      </a>
    </div>

    {#if formOpen}
      <form
        class="mt-6 grid gap-4 rounded-2xl border p-4 theme-border"
        onsubmit={(event) => {
          event.preventDefault();
          submitApiToken();
        }}
      >
        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title">Token name</span>
          <Input
            bind:value={apiTokenName}
            bind:element={nameInput}
            name="name"
            placeholder="CI automation"
            invalid={hasFieldErrors(errors, "name")}
          />
          <FieldErrors {errors} field="name" />
        </label>

        <fieldset class="grid gap-2">
          <legend class="text-sm font-medium ui-title">Expiration</legend>
          {#each expirationOptions as option (option.value)}
            <label class="flex items-center gap-2 text-sm ui-title">
              <input
                type="radio"
                name="expiration"
                value={option.value}
                bind:group={expiration}
              />
              {option.label}
              {#if option.value === expiration && presetDate}
                <span class="text-xs ui-muted">
                  expires {formatDate(presetDate.toISOString())}
                </span>
              {/if}
            </label>
          {/each}
          {#if expiration === "custom"}
            <Input
              aria-label="Custom expiration date"
              bind:value={customExpiresAt}
              name="expires_at"
              type="datetime-local"
              invalid={hasFieldErrors(errors, "expires_at")}
            />
          {/if}
          {#if expiration !== "never"}
            <span class="text-xs ui-muted">Time zone: {browserTimeZone()}</span>
          {/if}
          <FieldErrors {errors} field="expires_at" />
        </fieldset>

        <fieldset class="grid gap-3">
          <legend class="text-sm font-medium ui-title">Scopes</legend>
          <label
            class="flex items-start gap-3 rounded-xl border px-4 py-3 theme-border"
          >
            <input
              bind:checked={apiTokenScopes.read}
              class="mt-1"
              name="scopes"
              type="checkbox"
              value="read"
            />
            <span class="grid gap-1">
              <span class="text-sm font-medium ui-title">Read</span>
              <span class="text-xs ui-muted">List and inspect API data.</span>
            </span>
          </label>

          <label
            class="flex items-start gap-3 rounded-xl border px-4 py-3 theme-border"
          >
            <input
              bind:checked={apiTokenScopes.write}
              class="mt-1"
              name="scopes"
              type="checkbox"
              value="write"
            />
            <span class="grid gap-1">
              <span class="text-sm font-medium ui-title">Write</span>
              <span class="text-xs ui-muted">
                Create, update, sync, and revoke API resources.
              </span>
            </span>
          </label>

          {#if canIssueAdminApiToken}
            <label
              class="flex items-start gap-3 rounded-xl border px-4 py-3 theme-border"
            >
              <input
                bind:checked={apiTokenScopes.admin}
                class="mt-1"
                name="scopes"
                type="checkbox"
                value="admin"
              />
              <span class="grid gap-1">
                <span class="text-sm font-medium ui-title">Admin</span>
                <span class="text-xs ui-muted">
                  Access administrator API endpoints.
                </span>
              </span>
            </label>
          {/if}
        </fieldset>
        <FieldErrors {errors} field="scopes" />
        <!-- One grid item with the actions: the always-present live region adds no gap while empty. -->
        <div>
          <div role="alert" class="not-empty:mb-4">
            <FieldErrors {errors} field="api_token" />
          </div>
          <div class="flex flex-wrap gap-3">
            <Button
              className="w-fit rounded-full px-5"
              type="submit"
              variant="primary"
              disabled={submitting}
            >
              Issue API token
            </Button>
            <Button type="button" variant="secondary" onclick={closeForm}>
              Cancel
            </Button>
          </div>
        </div>
      </form>
    {/if}

    {#if issued && issued.token !== dismissedToken}
      <IssuedApiToken
        name={issued.name}
        token={issued.token}
        ondone={() => dismissIssued(issued.token)}
      />
    {/if}
  </Card>
</div>
<ConfirmationModal
  open={apiTokenPendingRevoke !== null}
  eyebrow="API token"
  title="Revoke token?"
  description="This immediately removes REST API access for this token."
  confirmLabel="Revoke token"
  cancelLabel="Keep token"
  confirming={apiTokenRevoking}
  onCancel={() => (apiTokenPendingRevoke = null)}
  onConfirm={confirmApiTokenRevoke}
>
  {#if apiTokenPendingRevoke}
    <div class="ui-inset grid gap-2 rounded-xl border p-4">
      <p class="font-medium ui-title">{apiTokenPendingRevoke.name}</p>
      <p class="break-all font-mono text-xs ui-muted">
        {apiTokenPendingRevoke.token_prefix}...
      </p>
    </div>
  {/if}
</ConfirmationModal>

<ConfirmationModal
  open={apiTokenPendingDelete !== null}
  eyebrow="API token"
  title="Delete revoked token?"
  description="This permanently removes the revoked API token record from your profile."
  confirmLabel="Delete token"
  cancelLabel="Keep record"
  confirming={apiTokenDeleting}
  onCancel={() => (apiTokenPendingDelete = null)}
  onConfirm={confirmApiTokenDelete}
>
  {#if apiTokenPendingDelete}
    <div class="ui-inset grid gap-2 rounded-xl border p-4">
      <p class="font-medium ui-title">{apiTokenPendingDelete.name}</p>
      <p class="break-all font-mono text-xs ui-muted">
        {apiTokenPendingDelete.token_prefix}...
      </p>
    </div>
  {/if}
</ConfirmationModal>
