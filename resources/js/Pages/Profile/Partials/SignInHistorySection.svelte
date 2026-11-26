<script lang="ts">
  import Card from "@/Components/ui/Card.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import SignInHistoryList from "@/Components/ui/SignInHistoryList.svelte";
  import type { SignInHistoryEntry } from "@/types/pages";

  interface Props {
    sign_in_history?: SignInHistoryEntry[];
  }

  let { sign_in_history = [] }: Props = $props();
</script>

<div
  id="sign-in-history"
  class="scroll-mt-[calc(var(--admin-header-h,5rem)+1rem)]"
>
  <Card className="rounded-[1.75rem] p-6">
    <SectionHeading
      eyebrow="Security"
      title="Sign-in history"
      description="Your 20 most recent successful sign-ins."
    />

    <div class="mt-6">
      <SignInHistoryList entries={sign_in_history} />
    </div>
  </Card>
</div>
