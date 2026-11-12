<script lang="ts">
  import Link from "@/Components/core/Link.svelte";
  import { usePage } from "@inertiajs/svelte";

  type AdminSidebarVariant = "sidebar" | "drawer";

  let {
    variant = "sidebar",
    onNavigate = () => {},
  }: { variant?: AdminSidebarVariant; onNavigate?: () => void } = $props();

  const page = usePage();

  const links = [
    { href: "/admin", label: "Dashboard" },
    { href: "/admin/users", label: "Users" },
    { href: "/admin/organizations", label: "Organizations" },
    { href: "/admin/packages", label: "Packages" },
    { href: "/admin/sources", label: "Sources" },
    { href: "/admin/jobs", label: "Jobs" },
    { href: "/admin/settings", label: "Settings" },
  ];

  function normalizePath(url: string): string {
    const path = url.split("?")[0] || "/admin";

    return path || "/admin";
  }

  const currentPath = $derived(normalizePath(page.url ?? "/admin"));

  function matchesPath(path: string): boolean {
    if (path === "/admin") {
      return currentPath === "/admin";
    }

    return currentPath === path || currentPath.startsWith(`${path}/`);
  }

  const asideClass = $derived(
    variant === "sidebar"
      ? "admin-sidebar hidden flex-col border-r px-4 py-8 lg:px-5 xl:flex xl:h-full xl:w-[260px] xl:shrink-0 xl:overflow-y-auto"
      : "admin-sidebar-mobile flex min-h-0 flex-col px-3 pb-4 pt-2",
  );
  const navClass = $derived(
    variant === "sidebar" ? "mt-5 grid gap-2" : "mt-4 grid gap-2",
  );
  const linkClass = $derived(
    variant === "sidebar"
      ? "rounded-2xl px-4 py-3 transition"
      : "rounded-xl border px-4 py-3 text-sm font-medium transition",
  );
</script>

<aside
  class={asideClass}
  style={variant === "sidebar"
    ? "border-color: var(--panel-border); background-color: color-mix(in srgb, var(--surface) 82%, transparent);"
    : "background-color: transparent;"}
>
  <p class="px-2 text-xs font-semibold uppercase tracking-[0.24em] ui-muted">
    Admin
  </p>
  <nav class={navClass}>
    {#each links as item (item.href)}
      <Link
        class={linkClass}
        style={matchesPath(item.href)
          ? "background-color: var(--accent-soft); color: var(--accent-ink);"
          : variant === "sidebar"
            ? "color: var(--page-ink);"
            : "border-color: var(--panel-border); background-color: var(--surface-muted); color: var(--page-ink);"}
        href={item.href}
        onclick={onNavigate}
      >
        {item.label}
      </Link>
    {/each}
  </nav>
</aside>
