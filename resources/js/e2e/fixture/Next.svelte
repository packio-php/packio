<script module lang="ts">
  import Layout from "./Layout.svelte";

  export const layout = Layout;
</script>

<script lang="ts">
  import { Link, usePage } from "@inertiajs/svelte";

  let pageUrl = $derived(usePage().url);
</script>

<main>
  <h1>Next</h1>
  <p data-testid="page-url">{pageUrl}</p>
  <Link href="/" data-testid="home-link">Home</Link>
</main>
