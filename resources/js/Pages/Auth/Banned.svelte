<script lang="ts">
  import { router } from "@inertiajs/svelte";

  import AppShell from "@/Layouts/AppShell.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { Endpoints } from "@/lib/api/endpoints";
  import type { AuthPayload } from "@/types/auth";
  import type { BannedAccountPageProps } from "@/types/pages";

  interface Props {
    reason?: BannedAccountPageProps["reason"];
    banned_at?: BannedAccountPageProps["banned_at"];
  }

  let { reason = null, banned_at = null }: Props = $props();

  const guestAuth: AuthPayload = {};
  let isSubmitting = $state(false);

  function logout(): void {
    if (isSubmitting) return;

    isSubmitting = true;
    router.post(
      Endpoints.auth.logout(),
      {},
      {
        onFinish: () => {
          isSubmitting = false;
        },
      },
    );
  }
</script>

<svelte:head>
  <title>Account banned</title>
</svelte:head>

<AppShell
  title="Account banned"
  auth={guestAuth}
  mainClassName="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center px-6 py-8"
>
  <Card className="w-full rounded-[1.75rem] p-8">
    <SectionHeading
      eyebrow="Account status"
      title="Your account has been banned."
      description="You cannot access project resources while this restriction is active."
    />

    <dl class="mt-6 grid gap-4 text-sm">
      <div class="grid gap-1">
        <dt class="font-medium ui-title">Reason</dt>
        <dd>{reason ?? "Violation of project rules."}</dd>
      </div>
      {#if banned_at}
        <div class="grid gap-1">
          <dt class="font-medium ui-title">Banned at</dt>
          <dd>{banned_at}</dd>
        </div>
      {/if}
    </dl>

    <Button
      className="mt-8 w-full"
      variant="primary"
      onclick={logout}
      disabled={isSubmitting}
    >
      {isSubmitting ? "Signing out…" : "Log out"}
    </Button>
  </Card>
</AppShell>
