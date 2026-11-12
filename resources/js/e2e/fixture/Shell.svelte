<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import type { AuthPayload } from "@/types/auth";

  let { auth = {} }: { auth?: AuthPayload } = $props();
  const page = usePage();
  const Shell = $derived(page.url.startsWith("/admin") ? AdminShell : AppShell);
  let clicks = $state(0);
</script>

<Shell {auth}>
  <h1 data-testid="shell-url">{page.url}</h1>
  <button data-testid="shell-check" onclick={() => clicks++}>
    Clicks: {clicks}
  </button>
</Shell>
