<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import { onMount } from "svelte";
  import Button from "@/Components/ui/Button.svelte";

  let {
    threshold = 320,
    label = "↑ Top",
    container = null,
    // @internal Optional anchor-to-content mode: no production shell uses it
    // today (AdminShell pins the button to the viewport right edge), it is kept
    // as a backward-compatible extension covered by unit tests.
    referenceSelector = null,
  }: {
    threshold?: number;
    label?: string;
    container?: HTMLElement | null;
    referenceSelector?: string | null;
  } = $props();

  let visible = $state(false);
  let rightOffset = $state<number | null>(null);
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

  function syncPosition(): void {
    if (typeof window === "undefined" || !referenceSelector) {
      rightOffset = null;
      return;
    }

    const reference = document.querySelector(referenceSelector);

    if (!reference) {
      rightOffset = null;
      return;
    }

    const rect = reference.getBoundingClientRect();
    rightOffset = Math.max(window.innerWidth - rect.right - 16, 24);
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
      syncPosition();
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
    syncPosition();

    const source = scrollSource();
    source.addEventListener("scroll", syncVisibility, {
      passive: true,
    });

    window.addEventListener("resize", syncPosition);
    const offNavigate = router.on("navigate", syncAfterNavigation);

    return () => {
      if (navigationFrame !== null) {
        window.cancelAnimationFrame(navigationFrame);
      }

      source.removeEventListener("scroll", syncVisibility);
      window.removeEventListener("resize", syncPosition);
      offNavigate();
    };
  });
</script>

{#if visible}
  <div
    class="pointer-events-none fixed bottom-[55px] right-6 z-40 hidden md:block sm:bottom-[71px]"
    style={rightOffset !== null ? `right: ${rightOffset}px` : undefined}
  >
    <Button
      aria-label="Scroll to top"
      className="pointer-events-auto rounded-full px-3 py-2 text-xs opacity-80 shadow-[0_18px_48px_color-mix(in_srgb,var(--accent)_22%,transparent)] transition-opacity hover:opacity-95"
      onclick={scrollToTop}
      type="button"
      variant="primary"
    >
      {label}
    </Button>
  </div>
{/if}
