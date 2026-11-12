<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import { router } from "@inertiajs/svelte";
  import { Menu, Moon, Sun, X } from "@lucide/svelte";
  import { onMount } from "svelte";
  import FlashMessage from "@/Components/core/FlashMessage.svelte";
  import ToastContainer from "@/Components/core/ToastContainer.svelte";
  import AdminSidebar from "@/Components/admin/AdminSidebar.svelte";
  import BrandMark from "@/Components/core/BrandMark.svelte";
  import Link from "@/Components/core/Link.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import ScrollTopButton from "@/Components/ui/ScrollTopButton.svelte";
  import { mergeBranding } from "@/lib/branding";
  import {
    applyTheme as applyDocumentTheme,
    readStoredTheme,
    setTheme as setDocumentTheme,
  } from "@/lib/theme";
  import { Endpoints } from "@/lib/api";
  import type { AppPageProps } from "@/types/app";
  import type { AuthPayload } from "@/types/auth";
  import type { Branding } from "@/types/branding";
  import type { PackioFlashPayload } from "@/types/flash";

  interface Props {
    title?: string;
    auth?: AuthPayload;
    children?: import("svelte").Snippet;
  }

  let { title = "Admin", auth = {}, children }: Props = $props();

  let contentScrollEl: HTMLElement | null = $state(null);
  let headerHeight = $state(0);

  let themeMode: "dark" | "light" | "system" = "system";
  let resolvedTheme: "dark" | "light" = $state(
    typeof document !== "undefined" &&
      document.documentElement.dataset.theme === "dark"
      ? "dark"
      : "light",
  );
  let pageProps = $derived(
    (usePage<AppPageProps>().props ?? {}) as AppPageProps,
  );
  let pageFlash = $derived(
    ((usePage<AppPageProps>() as { flash?: PackioFlashPayload }).flash ??
      {}) as PackioFlashPayload,
  );
  let currentPageUrl = $derived(usePage<AppPageProps>().url ?? "");
  let lastPageUrl = usePage<AppPageProps>().url ?? "";
  let mobileMenuOpen = $state(false);

  function toggleTheme(): void {
    themeMode = resolvedTheme === "dark" ? "light" : "dark";
    resolvedTheme = setDocumentTheme(storageKey, themeMode);
  }

  function toggleMobileMenu(): void {
    mobileMenuOpen = !mobileMenuOpen;
  }

  function closeMobileMenu(): void {
    mobileMenuOpen = false;
  }

  function handleKeydown(event: KeyboardEvent): void {
    if (event.key === "Escape") {
      closeMobileMenu();
    }
  }

  function profileInitials(): string {
    const source =
      auth?.user?.display_name?.trim() || auth?.user?.email?.trim() || "A";

    return source.slice(0, 1).toUpperCase();
  }

  function logout(): void {
    closeMobileMenu();
    router.post(Endpoints.auth.logout(), {}, { preserveScroll: true });
  }

  onMount(() => {
    themeMode = readStoredTheme(storageKey);
    resolvedTheme = applyDocumentTheme(themeMode);

    const media = window.matchMedia("(prefers-color-scheme: dark)");
    const syncWithSystem = (): void => {
      if (themeMode === "system") {
        resolvedTheme = applyDocumentTheme("system");
      }
    };

    if (typeof media.addEventListener === "function") {
      media.addEventListener("change", syncWithSystem);
    }

    return () => {
      document.body.style.overflow = "";

      if (typeof media.removeEventListener === "function") {
        media.removeEventListener("change", syncWithSystem);
      }
    };
  });
  $effect(() => {
    if (currentPageUrl !== lastPageUrl) {
      lastPageUrl = currentPageUrl;
      closeMobileMenu();
    }
  });
  let branding = $derived(mergeBranding(pageProps.branding ?? {}) as Branding);
  let appVersion = $derived(pageProps.app?.version ?? null);
  let pageTitle = $derived(title || "Admin");
  let storageKey = $derived(branding.themeStorageKey || "packio-theme");
  let hasFlashMessage = $derived(!!pageFlash.success || !!pageFlash.error);
  $effect(() => {
    if (typeof document !== "undefined") {
      document.body.style.overflow = mobileMenuOpen ? "hidden" : "";
    }
  });
</script>

<svelte:window onkeydown={handleKeydown} />

<div
  class="flex min-h-dvh flex-col bg-[var(--page-bg)] text-[var(--page-ink)] xl:h-dvh xl:overflow-hidden"
  style={headerHeight > 0 ? `--admin-header-h: ${headerHeight}px` : undefined}
>
  <header
    bind:offsetHeight={headerHeight}
    class="sticky top-0 z-50 border-b theme-border xl:shrink-0"
    style="background-color: var(--header-bg);"
  >
    <div
      class="flex w-full items-center justify-between gap-3 px-4 py-3 sm:px-6 sm:py-4 xl:px-8 xl:py-5"
    >
      <Link class="flex min-w-0 items-center gap-3 no-underline" href="/admin">
        <BrandMark
          idPrefix="admin"
          className="size-9 shrink-0 rounded-xl sm:size-10"
          svgClassName="size-9 sm:size-10"
        />
        <div class="min-w-0">
          <p
            class="hidden truncate text-xs uppercase tracking-[0.32em] sm:block"
            style="color: var(--page-muted);"
          >
            {branding.domain}
          </p>
          <h1 class="truncate text-base font-semibold sm:mt-1 sm:text-lg">
            {pageTitle}
          </h1>
        </div>
      </Link>

      <div class="hidden items-center gap-2 xl:flex">
        <Link
          class="rounded-full border px-4 py-2 text-sm font-medium transition"
          href="/"
          style="border-color: var(--panel-border); background-color: var(--surface-muted); color: var(--page-ink);"
        >
          Site
        </Link>
        <div
          class="hidden items-center gap-3 rounded-full border px-3 py-2 sm:flex"
          style="border-color: var(--panel-border); background-color: var(--surface-muted);"
        >
          <span
            class="grid h-9 w-9 place-items-center rounded-full text-xs font-semibold text-white"
            style="background-color: var(--accent);"
          >
            {profileInitials()}
          </span>
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold">
              {auth?.user?.display_name ?? auth?.user?.email ?? "Admin"}
            </p>
            <p class="truncate text-xs" style="color: var(--page-muted);">
              {auth?.user?.email ?? ""}
            </p>
          </div>
        </div>
        <Button
          aria-label={resolvedTheme === "dark"
            ? "Switch to light theme"
            : "Switch to dark theme"}
          onclick={toggleTheme}
          type="button"
          variant="icon"
        >
          {#if resolvedTheme === "dark"}
            <Sun aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
          {:else}
            <Moon aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
          {/if}
        </Button>
        <Button
          className="rounded-full px-4"
          onclick={logout}
          type="button"
          variant="soft"
        >
          Logout
        </Button>
      </div>

      <div class="flex items-center gap-2 xl:hidden">
        <Button
          aria-label={resolvedTheme === "dark"
            ? "Switch to light theme"
            : "Switch to dark theme"}
          onclick={toggleTheme}
          type="button"
          variant="icon"
        >
          {#if resolvedTheme === "dark"}
            <Sun aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
          {:else}
            <Moon aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
          {/if}
        </Button>
        <Button
          aria-label={mobileMenuOpen ? "Close admin menu" : "Open admin menu"}
          onclick={toggleMobileMenu}
          type="button"
          variant="icon"
        >
          {#if mobileMenuOpen}
            <X aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
          {:else}
            <Menu aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
          {/if}
        </Button>
      </div>
    </div>
  </header>

  {#if mobileMenuOpen}
    <button
      aria-label="Close admin menu"
      class="fixed inset-0 z-40 cursor-default bg-black/35 xl:hidden"
      type="button"
      onclick={closeMobileMenu}
    ></button>
    <div
      class="fixed inset-x-3 top-[4.5rem] z-50 max-h-[calc(100dvh-5.5rem)] overflow-y-auto rounded-2xl border p-3 shadow-2xl xl:hidden"
      style="border-color: var(--panel-border); background-color: var(--surface);"
    >
      <div
        class="flex items-center gap-3 rounded-xl border px-3 py-3"
        style="border-color: var(--panel-border); background-color: var(--surface-muted);"
      >
        <span
          class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-semibold text-white"
          style="background-color: var(--accent);"
        >
          {profileInitials()}
        </span>
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm font-semibold">
            {auth?.user?.display_name ?? auth?.user?.email ?? "Admin"}
          </span>
          <span
            class="block truncate text-xs"
            style="color: var(--page-muted);"
          >
            {auth?.user?.email ?? ""}
          </span>
        </span>
      </div>

      <AdminSidebar variant="drawer" onNavigate={closeMobileMenu} />

      <div class="grid gap-2 border-t px-3 pt-4 theme-border">
        <Link
          class="rounded-xl border px-4 py-3 text-sm font-medium transition no-underline"
          href="/"
          onclick={closeMobileMenu}
          style="border-color: var(--panel-border); background-color: var(--surface-muted); color: var(--page-ink);"
        >
          Site
        </Link>
        <button
          class="cursor-pointer rounded-xl border px-4 py-3 text-left text-sm font-medium transition"
          type="button"
          onclick={logout}
          style="border-color: var(--button-danger-soft-border); background-color: var(--button-danger-soft-bg); color: var(--button-danger-soft-text);"
        >
          Logout
        </button>
      </div>
    </div>
  {/if}

  <div class="flex min-h-0 flex-1 flex-col xl:flex-row">
    <AdminSidebar variant="sidebar" />

    <div
      bind:this={contentScrollEl}
      class="admin-content-scroll flex w-full min-w-0 flex-1 flex-col xl:h-full xl:overflow-y-auto"
    >
      {#if hasFlashMessage}
        <div
          class="sticky top-[var(--admin-header-h)] z-10 bg-[var(--page-bg)] xl:top-0"
        >
          <div
            class="mx-auto w-full max-w-[80rem] px-4 pb-[5px] pt-[5px] sm:px-6 xl:px-8"
          >
            <FlashMessage flash={pageFlash} />
          </div>
        </div>
      {/if}

      <main class="flex w-full flex-1 flex-col">
        {@render children?.()}
      </main>

      <footer
        class="border-t theme-border"
        style="background-color: var(--footer-bg);"
      >
        <div
          class="flex w-full flex-col gap-2 px-6 py-4 text-sm sm:flex-row sm:items-center sm:justify-between xl:px-8"
          style="color: var(--page-muted);"
        >
          <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-6">
            <span>{branding.name} Admin</span>
            <span>Global administration surface for Packio operators</span>
          </div>
          <div
            class="flex items-center gap-2 sm:flex-col sm:items-end sm:gap-0.5"
          >
            <span class="text-xs">{branding.domain}</span>
            {#if appVersion?.display}
              <span class="text-[10px] tracking-[0.14em] opacity-75"
                >v{appVersion.display}</span
              >
            {/if}
          </div>
        </div>
      </footer>
    </div>
  </div>

  <ToastContainer />
  <ScrollTopButton container={contentScrollEl} />
</div>
