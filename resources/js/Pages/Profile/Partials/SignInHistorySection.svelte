<script lang="ts">
  import Card from "@/Components/ui/Card.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import SignInHistoryList from "@/Components/ui/SignInHistoryList.svelte";
  import type { SignInHistoryEntry } from "@/types/pages";

  interface Props {
    sign_in_history?: SignInHistoryEntry[];
  }

  let { sign_in_history = [] }: Props = $props();

  const PREVIEW = 5;
  let expanded = $state(false);
  let shown = $derived(
    expanded ? sign_in_history : sign_in_history.slice(0, PREVIEW),
  );
</script>

<div
  id="sign-in-history"
  class="scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)]"
>
  <Card className="rounded-[1.75rem] p-6">
    <SectionHeading
      eyebrow="Security"
      title="Sign-in history"
      titleTag="h3"
      description="Your most recent successful sign-ins."
    />

    <div class="mt-6">
      <SignInHistoryList entries={shown} />
    </div>
    {#if sign_in_history.length > PREVIEW}
      <div class="mt-4">
        <Button
          type="button"
          variant="secondary"
          aria-expanded={expanded}
          onclick={() => (expanded = !expanded)}
        >
          {expanded
            ? "Show fewer"
            : `Show all ${sign_in_history.length} sign-ins`}
        </Button>
      </div>
    {/if}
  </Card>
</div>
