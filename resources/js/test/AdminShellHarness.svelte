<script lang="ts">
  import AdminShell from "@/Layouts/AdminShell.svelte";
  import type { AuthPayload } from "@/types/auth";

  let { auth = {} }: { auth?: AuthPayload } = $props();
</script>

<AdminShell title="Admin" {auth}>
  <div class="admin-content-inner mx-auto w-full max-w-[80rem]">
    <p>Test content</p>
  </div>
</AdminShell>
