<script lang="ts">
  import AppShell from "@/Layouts/AppShell.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import type { AuthPayload } from "@/types/auth";

  interface Props {
    auth?: AuthPayload;
    title: string;
    html: string;
  }

  let { auth = {}, title, html }: Props = $props();
</script>

<svelte:head>
  <title>{title}</title>
</svelte:head>

<AppShell {title} {auth}>
  <Card className="mx-auto w-full max-w-3xl p-8">
    <div class="ui-markdown">
      <!-- HTML is sanitized during markdown conversion on the backend. -->
      <!-- eslint-disable-next-line svelte/no-at-html-tags -->
      {@html html}
    </div>
  </Card>
</AppShell>
