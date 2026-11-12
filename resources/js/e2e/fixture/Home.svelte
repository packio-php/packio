<script module lang="ts">
  import Layout from "./Layout.svelte";

  export const layout = Layout;
</script>

<script lang="ts">
  import { Link, router, usePage } from "@inertiajs/svelte";

  interface Props {
    jobs?: string[];
    old?: { email?: string };
    errors?: { email?: string };
  }

  let { jobs = [], old = {}, errors = {} }: Props = $props();
  let email = $derived(old.email ?? "");
  let pageUrl = $derived(usePage().url);

  function submit(): void {
    router.post("/form", { email });
  }

  function reloadJobs(): void {
    router.reload({ only: ["jobs"] });
  }

  function triggerExpiredSession(): void {
    router.post("/expired");
  }
</script>

<main>
  <h1>Home</h1>
  <p data-testid="page-url">{pageUrl}</p>
  <Link href="/next" data-testid="next-link">Next page</Link>

  <button type="button" data-testid="reload-jobs" onclick={reloadJobs}>
    Reload jobs
  </button>
  <p data-testid="jobs">{jobs.join(",")}</p>

  <label>
    Email
    <input data-testid="email" bind:value={email} />
  </label>
  <button type="button" data-testid="submit-form" onclick={submit}
    >Submit form</button
  >
  {#if errors.email}
    <p data-testid="email-error">{errors.email}</p>
  {/if}

  <button
    type="button"
    data-testid="expired-session"
    onclick={triggerExpiredSession}
  >
    Expire session
  </button>
</main>
