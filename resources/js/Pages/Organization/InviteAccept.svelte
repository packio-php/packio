<script lang="ts">
  import { router } from "@inertiajs/svelte";

  import AppShell from "@/Layouts/AppShell.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import type { AuthPayload } from "@/types/auth";
  import type { OrganizationSummary } from "@/types/organization";
  import type { OrganizationInvitationAcceptPayload } from "@/types/pages";
  import { formatDate } from "@/lib/date";

  interface Props {
    auth?: AuthPayload;
    invitation?: OrganizationInvitationAcceptPayload | null;
    organization_target?: OrganizationSummary | null;
  }

  let {
    auth = {},
    invitation = null,
    organization_target = null,
  }: Props = $props();

  function submit(): void {
    router.post(
      `/organization/invite/${invitation?.token}/accept`,
      {},
      {
        preserveScroll: true,
      },
    );
  }

  let canAccept = $derived(invitation?.["status"] === "pending");
</script>

<svelte:head>
  <title>Accept Invitation</title>
</svelte:head>

<AppShell title="Accept Invitation" {auth}>
  <section class="mx-auto grid max-w-3xl gap-6">
    <Card className="rounded-[1.75rem] p-8">
      <SectionHeading
        eyebrow="Organization invitation"
        title={organization_target?.name || "Organization"}
        description="Join this organization through a single-use invitation flow."
        className="max-w-xl"
      />

      <div class="mt-6 grid gap-4 md:grid-cols-2">
        <InsetCard className="p-4">
          <p class="ui-eyebrow">Role</p>
          <p class="ui-title mt-2 text-xl font-semibold">
            {invitation?.role || "member"}
          </p>
        </InsetCard>
        <InsetCard className="p-4">
          <p class="ui-eyebrow">Status</p>
          <p class="ui-title mt-2 text-xl font-semibold">
            {invitation?.status || "unknown"}
          </p>
        </InsetCard>
      </div>

      <div class="mt-6 grid gap-2 text-sm ui-muted">
        <p>
          <span class="font-medium ui-title">Expires at:</span>
          {formatDate(invitation?.expires_at)}
        </p>
        {#if invitation?.accepted_at}
          <p>
            <span class="font-medium ui-title">Accepted at:</span>
            {formatDate(invitation.accepted_at)}
          </p>
        {/if}
        {#if invitation?.revoked_at}
          <p>
            <span class="font-medium ui-title">Revoked at:</span>
            {formatDate(invitation.revoked_at)}
          </p>
        {/if}
      </div>

      <div class="mt-8">
        <Button
          className="rounded-full px-5"
          type="button"
          variant="primary"
          disabled={!canAccept}
          onclick={submit}
        >
          {canAccept ? "Accept invitation" : "Invitation unavailable"}
        </Button>
      </div>
    </Card>
  </section>
</AppShell>
