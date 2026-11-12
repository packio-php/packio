<script lang="ts">
  let {
    name = "website_url",
    value = $bindable(""),
  }: { name?: string; value?: string } = $props();
</script>

<input
  type="text"
  {name}
  bind:value
  tabindex="-1"
  autocomplete="off"
  class="honeypot-field"
/>

<style>
  .honeypot-field {
    position: absolute;
    left: -9999px;
    opacity: 0;
    pointer-events: none;
    height: 0;
    width: 0;
  }
</style>
