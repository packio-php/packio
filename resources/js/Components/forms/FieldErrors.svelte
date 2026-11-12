<script lang="ts">
  import { fieldErrors } from "@/lib/validationErrors";
  import type { ValidationErrors } from "@/types/pages";

  let {
    errors = {},
    field = "",
  }: { errors?: ValidationErrors; field?: string } = $props();

  const messages = $derived(fieldErrors(errors, field));
</script>

{#if messages.length > 0}
  <div class="grid gap-1">
    {#each messages as message}
      <span class="text-sm" style="color: var(--flash-error-text);">
        {message}
      </span>
    {/each}
  </div>
{/if}
