<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import Link from "@/Components/core/Link.svelte";
  import type { AppPageProps } from "@/types/app";

  const page = usePage<AppPageProps>();
  let terms = $derived(page.props.legal?.terms_url ?? null);
  let privacy = $derived(page.props.legal?.privacy_url ?? null);
</script>

{#snippet termsLink(url: string)}
  <Link class="underline" href={url}>Terms of Service</Link>
{/snippet}
{#snippet privacyLink(url: string)}
  <Link class="underline" href={url}>Privacy Policy</Link>
{/snippet}

{#if terms || privacy}
  <p class="mt-4 text-xs ui-muted">
    {#if terms && privacy}
      By creating an account you agree to the {@render termsLink(terms)} and acknowledge
      the {@render privacyLink(privacy)}.
    {:else if terms}
      By creating an account you agree to the {@render termsLink(terms)}.
    {:else if privacy}
      By creating an account you acknowledge the {@render privacyLink(privacy)}.
    {/if}
  </p>
{/if}
