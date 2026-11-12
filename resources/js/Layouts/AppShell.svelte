<script lang="ts">
  import { usePage } from "@inertiajs/svelte";
  import { router } from "@inertiajs/svelte";
  import { ChevronDown, Menu, Moon, Sun, X } from "@lucide/svelte";
  import { onMount } from "svelte";
  import BrandMark from "@/Components/core/BrandMark.svelte";
  import FlashMessage from "@/Components/core/FlashMessage.svelte";
  import ToastContainer from "@/Components/core/ToastContainer.svelte";
  import Link from "@/Components/core/Link.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Input from "@/Components/ui/Input.svelte";
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
  import type {
    OrganizationMembership,
    OrganizationSummary,
  } from "@/types/organization";

  interface Props {
    title?: string;
    auth?: AuthPayload;
    flashClassName?: string;
    mainClassName?: string;
    children?: import("svelte").Snippet;
  }

  let {
    title = "",
    auth = {},
    flashClassName = "",
    mainClassName = "mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-6 py-8",
    children,
  }: Props = $props();

  let themeMode: "dark" | "light" | "system" = "system";
  let resolvedTheme: "dark" | "light" = $state(
    typeof document !== "undefined" &&
      document.documentElement.dataset.theme === "dark"
      ? "dark"
      : "light",
  );
  const page = usePage();
  let currentPath = $derived(normalizePath(page.url ?? "/"));
  let organizationMenuOpen = $state(false);
  let mobileOrganizationMenuOpen = $state(false);
  let organizationCreateOpen = $state(false);
  let mobileOrganizationCreateOpen = $state(false);
  let userMenuOpen = $state(false);
  let mobileMenuOpen = $state(false);
  let mobileAccountMenuOpen = $state(false);

  let pageProps = $derived(
    ((usePage<AppPageProps>() as { props?: AppPageProps } | undefined)?.props ??
      {}) as AppPageProps,
  );
  let pageFlash = $derived(
    ((usePage<AppPageProps>() as { flash?: PackioFlashPayload } | undefined)
      ?.flash ?? {}) as PackioFlashPayload,
  );
  let hasFlashMessage = $derived(!!pageFlash.success || !!pageFlash.error);
  let headerHeight = $state(0);
  let branding = $derived(mergeBranding(pageProps.branding ?? {}) as Branding);
  let appVersion = $derived(pageProps.app?.version ?? null);
  let organization = $derived(
    (pageProps.organization ?? null) as OrganizationSummary | null,
  );
  let organizations = $derived(
    (pageProps.organizations ?? []) as OrganizationSummary[],
  );
  let currentMembership = $derived(
    (pageProps.current_membership ?? null) as OrganizationMembership | null,
  );
  let hasOrganizationAccess = $derived(!!currentMembership);
  let isAdmin = $derived(!!auth?.user?.is_admin);
  let isOrganizationOwner = $derived(currentMembership?.role === "owner");
  let hasOrganizations = $derived(organizations.length > 0);
  let hasCurrentOrganization = $derived(!!organization?.slug);
  let organizationButtonLabel = $derived(
    !hasOrganizations
      ? "Create organization"
      : organization?.name || "Choose organization",
  );
  let pageTitle = $derived(title || branding.name || "Application");
  let storageKey = $derived(branding.themeStorageKey || "packio-theme");

  function toggleTheme(): void {
    themeMode = resolvedTheme === "dark" ? "light" : "dark";
    resolvedTheme = setDocumentTheme(storageKey, themeMode);
  }

  function normalizePath(url: string): string {
    if (!url) {
      return "/";
    }

    const path = url.split("?")[0] || "/";

    if (!path.startsWith("/organization/")) {
      return path;
    }

    return path.replace(/^\/organization\/[^/]+/, "") || "/";
  }

  function matchesPath(path: string): boolean {
    if (path === "/") {
      return currentPath === "/";
    }

    return currentPath === path || currentPath.startsWith(`${path}/`);
  }

  function profileInitials(): string {
    const source =
      auth?.user?.display_name?.trim() || auth?.user?.email?.trim() || "P";

    return source.slice(0, 1).toUpperCase();
  }

  function withOrganization(path: string): string {
    if (!organization?.slug) {
      return auth?.check ? "/organizations" : "/auth/login";
    }

    if (path.startsWith("/organization/")) {
      return path;
    }

    return path === "/"
      ? `/organization/${organization.slug}`
      : `/organization/${organization.slug}${path}`;
  }

  function profilePath(): string {
    return organization?.slug
      ? `/profile?organization=${encodeURIComponent(organization.slug)}`
      : "/profile";
  }

  function appHomePath(): string {
    if (auth?.check) {
      return hasCurrentOrganization ? withOrganization("/") : "/organizations";
    }

    return hasCurrentOrganization
      ? withOrganization("/registry/setup")
      : "/auth/login";
  }

  function navigateToOrganization(slug: string): void {
    const current = window.location.pathname;
    const nextPath = current.startsWith("/organization/")
      ? current.replace(/^\/organization\/[^/]+/, `/organization/${slug}`)
      : `/organization/${slug}`;

    organizationMenuOpen = false;
    mobileOrganizationMenuOpen = false;

    router.get(
      nextPath,
      {},
      {
        preserveState: false,
        preserveScroll: true,
      },
    );
  }

  function toggleOrganizationMenu(): void {
    organizationMenuOpen = !organizationMenuOpen;
    if (organizationMenuOpen) {
      organizationCreateOpen = false;
    }
  }

  function toggleMobileOrganizationMenu(): void {
    mobileOrganizationMenuOpen = !mobileOrganizationMenuOpen;
    if (mobileOrganizationMenuOpen) {
      mobileOrganizationCreateOpen = false;
    }
  }

  function openOrganizationCreate(): void {
    organizationCreateOpen = true;
    organizationMenuOpen = true;
  }

  function openMobileOrganizationCreate(): void {
    mobileOrganizationCreateOpen = true;
    mobileOrganizationMenuOpen = true;
  }

  function submitOrganization(
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ): void {
    const form = event.currentTarget;

    router.post(form.action, Object.fromEntries(new FormData(form)), {
      preserveScroll: true,
      onSuccess: () => {
        organizationMenuOpen = false;
        mobileOrganizationMenuOpen = false;
        organizationCreateOpen = false;
        mobileOrganizationCreateOpen = false;
      },
    });
  }

  function logout(): void {
    userMenuOpen = false;
    mobileMenuOpen = false;
    mobileAccountMenuOpen = false;
    organizationMenuOpen = false;
    mobileOrganizationMenuOpen = false;
    organizationCreateOpen = false;
    mobileOrganizationCreateOpen = false;

    router.post(Endpoints.auth.logout(), {}, { preserveScroll: true });
  }

  function toggleUserMenu(): void {
    userMenuOpen = !userMenuOpen;
  }

  function toggleMobileMenu(): void {
    mobileMenuOpen = !mobileMenuOpen;
  }

  function toggleMobileAccountMenu(): void {
    mobileAccountMenuOpen = !mobileAccountMenuOpen;
  }

  function targetWithinSelector(
    target: EventTarget | null,
    selector: string,
  ): Element | null {
    return target instanceof Element ? target.closest(selector) : null;
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

    const handleDocumentClick = (event: MouseEvent): void => {
      if (!targetWithinSelector(event.target, "[data-organization-menu]")) {
        organizationMenuOpen = false;
        mobileOrganizationMenuOpen = false;
        organizationCreateOpen = false;
        mobileOrganizationCreateOpen = false;
      }

      if (!targetWithinSelector(event.target, "[data-user-menu]")) {
        userMenuOpen = false;
      }
    };

    document.addEventListener("click", handleDocumentClick);

    const offNavigate = router.on("navigate", () => {
      userMenuOpen = false;
      mobileMenuOpen = false;
      mobileAccountMenuOpen = false;
      organizationMenuOpen = false;
      mobileOrganizationMenuOpen = false;
      organizationCreateOpen = false;
      mobileOrganizationCreateOpen = false;
    });

    return () => {
      if (typeof media.removeEventListener === "function") {
        media.removeEventListener("change", syncWithSystem);
      }

      document.removeEventListener("click", handleDocumentClick);
      offNavigate();
    };
  });
</script>

{#snippet themeButton()}
  <Button
    aria-label={resolvedTheme === "dark"
      ? "Switch to light theme"
      : "Switch to dark theme"}
    onclick={toggleTheme}
    variant="icon"
    type="button"
  >
    {#if resolvedTheme === "dark"}
      <Sun aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
    {:else}
      <Moon aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
    {/if}
  </Button>
{/snippet}

{#snippet sectionLinks(mobile: boolean)}
  {@const linkClass = mobile
    ? "rounded-2xl px-4 py-3 transition"
    : "rounded-full px-3 py-2 transition"}
  {@const active =
    "background-color: var(--accent-soft); color: var(--accent-ink);"}
  {@const inactive = mobile
    ? "border: 1px solid var(--panel-border); background-color: var(--surface-muted); color: var(--page-ink);"
    : "color: var(--page-muted);"}
  {#snippet section(match: string, href: string, label: string)}
    <Link
      class={linkClass}
      style={matchesPath(match) ? active : inactive}
      {href}>{label}</Link
    >
  {/snippet}
  {#if hasOrganizationAccess}
    {@render section("/packages", withOrganization("/packages"), "Packages")}
    {@render section("/registry", withOrganization("/registry/setup"), "Setup")}
  {/if}
  {#if auth?.check}
    {#if hasOrganizationAccess}
      {@render section("/sources", withOrganization("/sources"), "Sources")}
      {@render section("/tokens", withOrganization("/tokens"), "Tokens")}
    {/if}
    {#if isAdmin}
      {@render section("/admin", "/admin", "Admin")}
    {/if}
  {:else}
    <Link
      class={`inline-flex ${mobile ? "min-h-11" : "h-10"} items-center justify-center rounded-lg px-5 text-sm font-semibold no-underline transition`}
      style="background-color: var(--button-primary-bg); color: var(--button-primary-text);"
      href="/auth/login">Login</Link
    >
  {/if}
{/snippet}

{#snippet organizationMenu(mobile: boolean)}
  {@const menuOpen = mobile ? mobileOrganizationMenuOpen : organizationMenuOpen}
  {@const createOpen = mobile
    ? mobileOrganizationCreateOpen
    : organizationCreateOpen}
  {@const active =
    "background-color: var(--accent-soft); color: var(--accent-ink);"}
  {@const inactive = mobile
    ? "background-color: var(--surface-muted); color: var(--page-ink);"
    : "color: var(--page-ink);"}
  {@const createLabel = hasOrganizations
    ? "Add organization"
    : "Create organization"}
  {@const itemClass = mobile ? "rounded-2xl px-4 py-3" : "rounded-xl px-3 py-2"}
  {#snippet organizationItems()}
    {#if hasOrganizations}
      {#each organizations as item (item.slug)}
        <button
          class={`flex w-full cursor-pointer items-center justify-between ${itemClass} text-left text-sm transition`}
          style={item.slug === organization?.slug ? active : inactive}
          type="button"
          onclick={(event) => {
            event.stopPropagation();
            navigateToOrganization(item.slug);
          }}
        >
          <span class="font-medium">{item.name}</span>
          {#if item.slug === organization?.slug}
            <span
              class={`${mobile ? "text-xs" : "text-[11px]"} uppercase tracking-[0.18em]`}
            >
              current
            </span>
          {/if}
        </button>
      {/each}
    {:else if mobile}
      <div
        class="rounded-2xl px-4 py-3 text-sm"
        style="background-color: var(--surface-muted); color: var(--page-muted);"
      >
        Create your first organization to unlock packages, sources, and tokens.
      </div>
    {:else}
      <div class="px-3 py-2 text-sm" style="color: var(--page-muted);">
        No organizations yet. Create your first one.
      </div>
    {/if}
  {/snippet}
  {#snippet ownerLinks()}
    {#each [["/members", "Members"], ["/settings", "Settings"]] as [path, label] (path)}
      <Link
        class={`block ${itemClass} text-sm transition no-underline`}
        style={matchesPath(path) ? active : inactive}
        href={withOrganization(path)}>{label}</Link
      >
    {/each}
  {/snippet}
  {#snippet organizationCreate()}
    {#if !createOpen}
      <button
        class={mobile
          ? "w-full cursor-pointer rounded-2xl border px-4 py-3 text-left text-sm font-medium transition"
          : "flex w-full cursor-pointer items-center justify-between rounded-xl px-3 py-2 text-left text-sm font-medium transition"}
        style={mobile
          ? "border-color: var(--panel-border); background-color: var(--surface-muted); color: var(--page-ink);"
          : "color: var(--page-ink);"}
        type="button"
        onclick={(event) => {
          event.stopPropagation();
          (mobile ? openMobileOrganizationCreate : openOrganizationCreate)();
        }}
      >
        {#if mobile}
          {createLabel}
        {:else}
          <span>{createLabel}</span>
          <span class="text-lg leading-none">+</span>
        {/if}
      </button>
    {:else}
      <form
        class={mobile
          ? "grid gap-3 rounded-2xl border p-3"
          : "grid gap-3 rounded-xl p-2"}
        style={mobile
          ? "border-color: var(--panel-border); background-color: var(--surface-muted);"
          : undefined}
        method="POST"
        action="/organizations"
        onsubmit={(event) => {
          event.preventDefault();
          submitOrganization(
            event as SubmitEvent & {
              currentTarget: EventTarget & HTMLFormElement;
            },
          );
        }}
      >
        <Input
          maxlength={50}
          name="name"
          placeholder="Organization name"
          required
        />
        <div class="flex gap-2">
          <Button
            className="rounded-full px-4 py-2 text-sm"
            type="submit"
            variant="primary"
          >
            Create
          </Button>
          <Button
            className="rounded-full px-4 py-2 text-sm"
            type="button"
            variant="soft"
            onclick={() =>
              mobile
                ? (mobileOrganizationCreateOpen = false)
                : (organizationCreateOpen = false)}
          >
            Cancel
          </Button>
        </div>
      </form>
    {/if}
  {/snippet}
  {#if auth?.check}
    <div
      class={mobile ? "grid gap-2" : "relative mr-auto"}
      data-organization-menu
    >
      <button
        class={mobile
          ? "flex w-full cursor-pointer items-center justify-between rounded-2xl border px-4 py-3 text-left transition"
          : "flex min-w-0 max-w-full cursor-pointer items-center gap-3 rounded-full border px-3 py-2 text-left transition"}
        style={!mobile && menuOpen
          ? "border-color: var(--accent); background-color: var(--accent-soft); color: var(--accent-ink);"
          : "border-color: var(--panel-border); background-color: var(--surface-muted); color: var(--page-ink);"}
        type="button"
        onclick={(event) => {
          event.stopPropagation();
          (mobile ? toggleMobileOrganizationMenu : toggleOrganizationMenu)();
        }}
        aria-expanded={mobile ? undefined : menuOpen}
        aria-haspopup={mobile ? undefined : "menu"}
      >
        {#if mobile}
          <span>
            <span
              class="block text-[11px] font-medium uppercase tracking-[0.24em] ui-muted"
            >
              Organization
            </span>
            <span class="mt-1 block text-sm font-semibold">
              {organizationButtonLabel}
            </span>
          </span>
        {:else}
          <span class="uppercase tracking-[0.22em] text-xs ui-muted">
            Org
          </span>
          <span class="max-w-40 truncate text-sm font-semibold">
            {organizationButtonLabel}
          </span>
        {/if}
        <ChevronDown
          aria-hidden="true"
          class={`h-4 w-4 shrink-0 transition ${menuOpen ? "rotate-180" : ""}`}
          strokeWidth={1.8}
        />
      </button>
      {#if menuOpen && mobile}
        <div
          class="grid gap-2 rounded-3xl border p-3"
          style="border-color: var(--panel-border); background-color: var(--surface);"
        >
          {@render organizationItems()}
          {#if isOrganizationOwner && hasCurrentOrganization}
            <div
              class="my-1 border-t"
              style="border-color: var(--panel-border);"
            ></div>
            {@render ownerLinks()}
          {/if}
          {@render organizationCreate()}
        </div>
      {:else if menuOpen}
        <div
          class="absolute left-0 top-[calc(100%+0.6rem)] z-20 min-w-[18rem] overflow-hidden rounded-2xl border shadow-xl"
          style="border-color: var(--panel-border); background-color: var(--surface); box-shadow: 0 20px 48px color-mix(in srgb, var(--page-bg) 55%, transparent);"
          role="menu"
        >
          <div
            class="border-b px-4 py-3"
            style="border-color: var(--panel-border);"
          >
            <p
              class="text-[11px] font-medium uppercase tracking-[0.24em] ui-muted"
            >
              Organizations
            </p>
          </div>
          <div class="grid gap-1 p-2">
            {@render organizationItems()}
          </div>
          {#if isOrganizationOwner && hasCurrentOrganization}
            <div
              class="border-t p-2"
              style="border-color: var(--panel-border);"
            >
              {@render ownerLinks()}
            </div>
          {/if}
          <div class="border-t p-2" style="border-color: var(--panel-border);">
            {@render organizationCreate()}
          </div>
        </div>
      {/if}
    </div>
  {/if}
{/snippet}

<div class="flex min-h-screen flex-col bg-(--page-bg) text-(--page-ink)">
  <header
    bind:offsetHeight={headerHeight}
    class="sticky top-0 z-50 border-b theme-border"
    style="background-color: var(--header-bg);"
  >
    <div
      class="flex w-full flex-col gap-4 px-6 py-5 lg:flex-row lg:items-center lg:justify-between xl:px-8"
    >
      <div class="flex items-center justify-between gap-3">
        <Link
          class="flex min-w-0 items-center gap-3 no-underline lg:flex-none"
          href={appHomePath()}
        >
          <BrandMark />
          <div>
            <p
              class="text-xs uppercase tracking-[0.32em]"
              style="color: var(--page-muted);"
            >
              {branding.domain}
            </p>
            <h1 class="mt-1 text-lg font-semibold">{pageTitle}</h1>
          </div>
        </Link>

        <div class="flex items-center gap-2 lg:hidden">
          {@render themeButton()}
          <Button
            aria-label={mobileMenuOpen ? "Close menu" : "Open menu"}
            onclick={toggleMobileMenu}
            variant="icon"
            type="button"
          >
            {#if mobileMenuOpen}
              <X aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
            {:else}
              <Menu aria-hidden="true" class="h-5 w-5" strokeWidth={1.8} />
            {/if}
          </Button>
        </div>
      </div>

      {#if mobileMenuOpen}
        <div class="grid gap-3 lg:hidden">
          <nav class="grid gap-2">
            {@render organizationMenu(true)}
            {@render sectionLinks(true)}
          </nav>

          {#if auth?.check}
            <div
              class="rounded-3xl border p-3"
              style="border-color: var(--panel-border); background-color: var(--surface-muted);"
            >
              <button
                class="flex w-full cursor-pointer items-center gap-3 rounded-2xl px-2 py-2 text-left transition"
                style={mobileAccountMenuOpen || matchesPath("/profile")
                  ? "background-color: var(--accent-soft); color: var(--accent-ink);"
                  : "color: var(--page-ink);"}
                type="button"
                onclick={toggleMobileAccountMenu}
              >
                <span
                  class="grid h-10 w-10 place-items-center rounded-full text-xs font-semibold text-white"
                  style="background-color: var(--accent);"
                >
                  {profileInitials()}
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-semibold">
                    {auth?.user?.display_name ?? auth?.user?.email ?? "Account"}
                  </span>
                  <span
                    class="block truncate text-xs"
                    style="color: var(--page-muted);"
                  >
                    {auth?.user?.email ?? ""}
                  </span>
                </span>
                <ChevronDown
                  aria-hidden="true"
                  class={`h-4 w-4 shrink-0 transition ${mobileAccountMenuOpen ? "rotate-180" : ""}`}
                  strokeWidth={1.8}
                />
              </button>
              {#if mobileAccountMenuOpen}
                <div class="mt-3 grid gap-2">
                  <Link
                    class="rounded-2xl px-4 py-3 transition"
                    style={matchesPath("/profile")
                      ? "background-color: var(--accent-soft); color: var(--accent-ink);"
                      : "border: 1px solid var(--panel-border); background-color: var(--surface); color: var(--page-ink);"}
                    href={profilePath()}
                  >
                    Profile
                  </Link>
                  <button
                    class="w-full cursor-pointer rounded-2xl px-4 py-3 text-left text-sm transition"
                    style="border: 1px solid var(--panel-border); background-color: var(--surface); color: var(--flash-error-text);"
                    type="button"
                    onclick={logout}
                  >
                    Logout
                  </button>
                </div>
              {/if}
            </div>
          {/if}
        </div>
      {/if}

      <div
        class="hidden w-full min-w-0 flex-wrap items-center justify-start gap-2 text-sm lg:flex lg:w-auto lg:flex-1 lg:justify-end"
      >
        {@render organizationMenu(false)}
        <nav
          class="flex min-w-0 flex-wrap items-center justify-start gap-2 text-sm lg:justify-end"
        >
          {@render sectionLinks(false)}
        </nav>
        {#if auth?.check}
          <div class="relative" data-user-menu>
            <button
              class="flex min-w-0 max-w-full cursor-pointer items-center gap-3 rounded-full border px-2 py-2 pr-3 transition lg:max-w-60"
              style={userMenuOpen || matchesPath("/profile")
                ? "border-color: var(--accent); background-color: var(--accent-soft); color: var(--accent-ink); box-shadow: 0 10px 24px color-mix(in srgb, var(--accent) 16%, transparent);"
                : "border-color: var(--panel-border); background-color: var(--surface-muted); color: var(--page-ink);"}
              type="button"
              onclick={toggleUserMenu}
              aria-expanded={userMenuOpen}
              aria-haspopup="menu"
            >
              <span
                class="grid h-9 w-9 place-items-center rounded-full text-xs font-semibold text-white"
                style="background-color: var(--accent);"
              >
                {profileInitials()}
              </span>
              <span
                class="max-w-32 truncate text-left text-sm font-semibold lg:max-w-36"
              >
                {auth?.user?.display_name ?? auth?.user?.email ?? "Account"}
              </span>
              <ChevronDown
                aria-hidden="true"
                class={`h-4 w-4 shrink-0 transition ${userMenuOpen ? "rotate-180" : ""}`}
                strokeWidth={1.8}
              />
            </button>

            {#if userMenuOpen}
              <div
                class="absolute right-0 top-[calc(100%+0.6rem)] z-20 min-w-48 overflow-hidden rounded-2xl border shadow-xl"
                style="border-color: var(--panel-border); background-color: var(--surface); box-shadow: 0 20px 48px color-mix(in srgb, var(--page-bg) 55%, transparent);"
                role="menu"
              >
                <Link
                  class="block px-4 py-3 text-sm transition no-underline"
                  style={matchesPath("/profile")
                    ? "background-color: var(--accent-soft); color: var(--accent-ink);"
                    : "color: var(--page-ink);"}
                  href={profilePath()}
                >
                  Profile
                </Link>
                <button
                  class="block w-full cursor-pointer px-4 py-3 text-left text-sm transition"
                  style="color: var(--flash-error-text);"
                  type="button"
                  onclick={logout}
                  role="menuitem"
                >
                  Logout
                </button>
              </div>
            {/if}
          </div>
        {/if}
        {@render themeButton()}
      </div>
    </div>
  </header>

  <main class={mainClassName}>
    {#if hasFlashMessage}
      <div
        class="sticky z-30 bg-(--page-bg) py-[5px]"
        style={headerHeight > 0 ? `top: ${headerHeight}px` : undefined}
      >
        <FlashMessage flash={pageFlash} className={flashClassName} />
      </div>
    {/if}
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
        <span>{branding.name}, {branding.tagline}</span>
      </div>
      <div class="flex items-center gap-2 sm:flex-col sm:items-end sm:gap-0.5">
        <span class="text-xs">{branding.domain}</span>
        {#if appVersion?.display}
          <span class="text-[10px] tracking-[0.14em] opacity-75"
            >v{appVersion.display}</span
          >
        {/if}
      </div>
    </div>
  </footer>

  <ToastContainer />
  <ScrollTopButton />
</div>
