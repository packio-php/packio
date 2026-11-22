<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import { onMount } from "svelte";

  let {
    threshold = 320,
    label = "↑ Top",
    container = null,
  }: {
    threshold?: number;
    label?: string;
    container?: HTMLElement | null;
  } = $props();

  let visible = $state(false);
  let navigationFrame: number | null = null;

  function scrollSource(): HTMLElement | Window {
    return container ?? window;
  }

  function syncVisibility(): void {
    if (typeof window === "undefined") {
      visible = false;
      return;
    }

    visible = container
      ? container.scrollTop > threshold
      : window.scrollY > threshold;
  }

  function syncAfterNavigation(): void {
    if (typeof window === "undefined") {
      return;
    }

    if (navigationFrame !== null) {
      window.cancelAnimationFrame(navigationFrame);
    }

    navigationFrame = window.requestAnimationFrame(() => {
      navigationFrame = null;
      syncVisibility();
    });
  }

  function scrollToTop(): void {
    if (typeof window === "undefined") {
      return;
    }

    scrollSource().scrollTo({
      top: 0,
      behavior: "smooth",
    });
  }

  onMount(() => {
    syncVisibility();

    const source = scrollSource();
    source.addEventListener("scroll", syncVisibility, {
      passive: true,
    });

    const offNavigate = router.on("navigate", syncAfterNavigation);

    return () => {
      if (navigationFrame !== null) {
        window.cancelAnimationFrame(navigationFrame);
      }

      source.removeEventListener("scroll", syncVisibility);
      offNavigate();
    };
  });
</script>

{#if visible}
  <div
    class="pointer-events-none fixed inset-x-0 top-24 z-40 hidden justify-center md:flex"
  >
    <button
      aria-label="Scroll to top"
      class="pointer-events-auto cursor-pointer rounded-full border px-3 py-1 text-xs opacity-80 shadow-sm backdrop-blur transition-opacity hover:opacity-100 theme-border"
      style="background-color: var(--header-bg);"
      onclick={scrollToTop}
      type="button"
    >
      {label}
    </button>
  </div>
{/if}
