<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import { Endpoints } from "@/lib/api";
  import Badge from "@/Components/ui/Badge.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { formatDate } from "@/lib/date";
  import type { ApiAccessTokenPayload, ProfilePayload } from "@/types/pages";

  interface Props {
    profile?: ProfilePayload | null;
    api_tokens?: ApiAccessTokenPayload[];
  }

  let { profile = null, api_tokens = [] }: Props = $props();

  const apiTokenExpirationPresets = [
    { value: "1-month", label: "1 month", months: 1 },
    { value: "3-months", label: "3 months", months: 3 },
    { value: "6-months", label: "6 months", months: 6 },
    { value: "1-year", label: "1 year", months: 12 },
  ] as const;

  let apiTokenName = $state("");
  let apiTokenExpiresAt = $state("");
  let apiTokenExpirationPreset = $state("");
  let apiTokenScopes = $state({
    read: true,
    write: false,
    admin: false,
  });
  let apiTokenPendingRevoke: ApiAccessTokenPayload | null = $state(null);
  let apiTokenPendingDelete: ApiAccessTokenPayload | null = $state(null);
  let apiTokenRevoking = $state(false);
  let apiTokenDeleting = $state(false);

  let canIssueAdminApiToken = $derived(
    profile?.roles?.includes("admin") ?? false,
  );

  function submitApiToken(): void {
    const scopes = Object.entries(apiTokenScopes)
      .filter(([, enabled]) => enabled)
      .map(([scope]) => scope)
      .filter((scope) => scope !== "admin" || canIssueAdminApiToken);

    router.post(
      Endpoints.profile.issueApiToken(),
      {
        name: apiTokenName,
        scopes,
        expires_at: apiTokenExpiresAtForRequest(),
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          apiTokenName = "";
          apiTokenExpiresAt = "";
          apiTokenExpirationPreset = "";
          apiTokenScopes = {
            read: true,
            write: false,
            admin: false,
          };
        },
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

  function setApiTokenExpirationPreset(
    preset: (typeof apiTokenExpirationPresets)[number] | null,
  ): void {
    apiTokenExpirationPreset = preset?.value ?? "";

    if (!preset) {
      return;
    }

    const now = new Date();
    const expiresAt = new Date(
      now.getFullYear(),
      now.getMonth() + preset.months,
      now.getDate(),
      now.getHours(),
      now.getMinutes(),
    );
    apiTokenExpiresAt = formatDatetimeLocal(expiresAt);
  }

  function apiTokenExpiresAtForRequest(): string {
    return formatIsoMinuteValue(apiTokenExpiresAt);
  }

  function formatDatetimeLocal(date: Date): string {
    const pad = (value: number): string => value.toString().padStart(2, "0");

    return [
      `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`,
      `${pad(date.getHours())}:${pad(date.getMinutes())}`,
    ].join("T");
  }

  function formatIsoMinuteValue(value: string): string {
    return value.trim().replace("T", " ");
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
  class="scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)] grid gap-6 xl:grid-cols-[1.1fr_0.9fr]"
>
  <Card className="rounded-[1.75rem] p-6">
    <SectionHeading
      eyebrow="API"
      title="API tokens"
      description="Create personal tokens for REST API automation."
    />
    <a
      class="mt-4 inline-flex w-fit items-center rounded-full border px-4 py-2 text-sm font-medium transition hover:border-[var(--accent)] hover:text-[var(--accent)] theme-border ui-title"
      href="/api/doc"
      rel="external noopener noreferrer"
      target="_blank"
    >
      Open API documentation
    </a>

    <div class="mt-6 grid gap-3">
      {#if api_tokens.length === 0}
        <div class="rounded-xl border border-dashed px-4 py-6">
          <p class="text-sm ui-muted">No API tokens issued yet.</p>
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
                {#if token.expires_at}
                  <p>Expires {formatDate(token.expires_at)}</p>
                {/if}
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
  </Card>

  <Card className="rounded-[1.75rem] p-6">
    <SectionHeading
      eyebrow="Issue token"
      title="Create API token"
      description="Create a new personal access token for REST API automation."
    />

    <form
      class="mt-6 grid gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        submitApiToken();
      }}
    >
      <div class="rounded-2xl border px-4 py-3 theme-border">
        <p class="text-sm font-medium ui-title">Store the token now</p>
        <p class="mt-1 text-xs leading-5 ui-muted">
          For security, the full API token is displayed only once when it is
          created.
        </p>
      </div>

      <label class="grid gap-2">
        <span class="text-sm font-medium ui-title">Token name</span>
        <Input
          bind:value={apiTokenName}
          name="name"
          placeholder="CI automation"
          required
        />
      </label>

      <div class="grid gap-2">
        <label class="text-sm font-medium ui-title" for="api-token-expires-at">
          Expiration date
        </label>
        <Input
          id="api-token-expires-at"
          bind:value={apiTokenExpiresAt}
          name="expires_at"
          type="datetime-local"
          oninput={() => (apiTokenExpirationPreset = "")}
        />
        <span class="text-xs ui-muted">
          Leave blank to create a token that never expires.
        </span>
        <span class="flex flex-wrap gap-2">
          {#each apiTokenExpirationPresets as preset (preset.value)}
            <label
              class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium theme-border ui-muted"
            >
              <input
                checked={apiTokenExpirationPreset === preset.value}
                type="checkbox"
                onchange={(event) =>
                  setApiTokenExpirationPreset(
                    event.currentTarget.checked ? preset : null,
                  )}
              />
              {preset.label}
            </label>
          {/each}
        </span>
      </div>

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

      <Button
        className="w-fit rounded-full px-5"
        type="submit"
        variant="primary"
      >
        Issue API token
      </Button>
    </form>
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
