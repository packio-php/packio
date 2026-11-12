<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import { router } from "@inertiajs/svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import CopyButton from "@/Components/ui/CopyButton.svelte";
  import { mergeBranding } from "@/lib/branding";
  import { formatDate } from "@/lib/date";
  import type { AuthPayload } from "@/types/auth";
  import type { AppPageProps } from "@/types/app";
  import type { OrganizationSummary } from "@/types/organization";
  import type {
    RegistryTokenPayload,
    TokensIndexPageProps,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    tokens?: RegistryTokenPayload[];
    branding?: TokensIndexPageProps["branding"];
    organization?: OrganizationSummary | null;
    can_manage_tokens?: boolean;
  }

  let {
    auth = {},
    tokens = [],
    branding = {},
    organization = null,
    can_manage_tokens = false,
  }: Props = $props();

  let pageProps = $derived(
    ((usePage<AppPageProps>() as { props?: AppPageProps } | undefined)?.props ??
      {}) as AppPageProps,
  );
  let currentMembership = $derived(pageProps.current_membership ?? null);
  let isOrganizationOwner = $derived(currentMembership?.role === "owner");
  let resolvedCanManage = $derived(can_manage_tokens || isOrganizationOwner);

  let resolvedBranding = $derived(mergeBranding(branding ?? {}));
  let tokenPendingRevoke: RegistryTokenPayload | null = $state(null);
  let tokenRevoking = $state(false);

  function withOrganization(path: string): string {
    if (!organization?.slug) {
      return path;
    }

    return path === "/"
      ? `/organization/${organization.slug}`
      : `/organization/${organization.slug}${path}`;
  }

  function tokenPlainValue(token: RegistryTokenPayload): string | null {
    return (
      (token as RegistryTokenPayload & { plain_token?: string | null })
        .plain_token ?? null
    );
  }

  function permissionLabel(permission: string): string {
    if (permission === "packages.read.private") {
      return "Private packages";
    }

    return permission
      .split(".")
      .filter((part) => part.length > 0)
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(" / ");
  }

  function submit(event: Event): void {
    const form = event.currentTarget as HTMLFormElement;

    router.post(form.action, Object.fromEntries(new FormData(form)), {
      preserveScroll: true,
    });
  }

  function confirmTokenRevoke(): void {
    const token = tokenPendingRevoke;
    if (!token || tokenRevoking) {
      return;
    }

    tokenRevoking = true;
    router.post(
      withOrganization(`/tokens/${token.id}/revoke`),
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          tokenRevoking = false;
          tokenPendingRevoke = null;
        },
      },
    );
  }
</script>

<svelte:head>
  <title>Registry Tokens</title>
</svelte:head>

<AppShell title="Registry Tokens" {auth}>
  <section class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
    <Card className="rounded-[1.75rem] p-6">
      <SectionHeading eyebrow="Issued tokens" className="mb-4" />
      <div class="mt-4 grid gap-3">
        {#if tokens.length === 0}
          <p class="text-sm ui-muted">
            No {resolvedBranding.name} registry tokens issued yet.
          </p>
        {:else}
          {#each tokens as token (token.id)}
            <InsetCard as="article" className="p-4">
              <!-- name + revoke -->
              <div class="flex items-center justify-between gap-4">
                <p class="ui-title font-semibold">{token.name}</p>
                {#if resolvedCanManage}
                  <Button
                    variant="chipDanger"
                    type="button"
                    onclick={() => (tokenPendingRevoke = token)}
                  >
                    Revoke
                  </Button>
                {/if}
              </div>

              <!-- masked token + copy -->
              {#if token.masked_token}
                <div
                  class="mt-3 flex items-center gap-2 rounded-xl border px-3 py-2 theme-border"
                  style="background-color: var(--surface-muted);"
                >
                  <code
                    class="min-w-0 flex-1 truncate font-mono text-xs ui-muted"
                  >
                    {token.masked_token}
                  </code>
                  {#if tokenPlainValue(token)}
                    <CopyButton
                      value={tokenPlainValue(token) ?? ""}
                      label="Copy token"
                    />
                  {/if}
                </div>
              {/if}

              <!-- dates + permissions -->
              <div
                class="mt-3 flex flex-wrap items-start justify-between gap-3"
              >
                <div class="grid gap-1 text-xs ui-muted">
                  <p>Created {formatDate(token.created_at)}</p>
                  <p>
                    {token.last_used_at
                      ? `Last used ${formatDate(token.last_used_at)}`
                      : "Never used"}
                  </p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                  {#each token.permissions as permission (permission)}
                    <span
                      class="ui-code rounded-full px-2.5 py-1 text-xs font-medium"
                    >
                      {permissionLabel(permission)}
                    </span>
                  {/each}
                </div>
              </div>
            </InsetCard>
          {/each}
        {/if}
      </div>
    </Card>

    <Card className="rounded-[1.75rem] self-start p-6">
      <SectionHeading
        eyebrow="Issue token"
        title={`Create a ${resolvedBranding.name} registry token`}
        description={`Use tokens to access private ${resolvedBranding.name} registry endpoints through \`Authorization: Bearer ...\` or Composer basic auth.`}
        className="max-w-xl"
      />

      {#if resolvedCanManage}
        <form
          class="mt-6 grid gap-4"
          method="POST"
          action={withOrganization("/tokens")}
          onsubmit={(event) => {
            event.preventDefault();
            submit(event);
          }}
        >
          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title"> Token name </span>
            <Input name="name" placeholder="CI deploy token" required />
          </label>
          <Button
            className="w-fit rounded-full px-5"
            type="submit"
            variant="primary"
          >
            Issue token
          </Button>
        </form>
      {:else}
        <p class="mt-6 text-sm ui-muted">
          Owner membership is required to issue organization tokens.
        </p>
      {/if}
    </Card>
  </section>

  <ConfirmationModal
    open={tokenPendingRevoke !== null}
    eyebrow="Registry token"
    title="Revoke token?"
    description={`This immediately removes registry access for this ${resolvedBranding.name} token.`}
    confirmLabel="Revoke token"
    cancelLabel="Keep token"
    confirming={tokenRevoking}
    onCancel={() => (tokenPendingRevoke = null)}
    onConfirm={confirmTokenRevoke}
  >
    {#if tokenPendingRevoke}
      <div class="ui-inset grid gap-2 rounded-xl border p-4">
        <p class="font-medium ui-title">{tokenPendingRevoke.name}</p>
        {#if tokenPendingRevoke.masked_token}
          <p class="break-all font-mono text-xs ui-muted">
            {tokenPendingRevoke.masked_token}
          </p>
        {/if}
      </div>
    {/if}
  </ConfirmationModal>
</AppShell>
