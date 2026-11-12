import { fireEvent, render, screen, within } from "@testing-library/svelte";
import { beforeEach, describe, expect, it } from "vitest";
import { router, setPageProps, setPageUrl, page } from "@/test/inertia";
import AppShell from "./AppShell.svelte";

const auth = {
  check: true,
  user: {
    id: "user-1",
    display_name: "Test User",
    email: "test@example.com",
    is_admin: true,
    roles: [],
  },
};

const acme = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  anonymous_access: false,
};

const beta = {
  id: "org-2",
  name: "Beta",
  slug: "beta",
  anonymous_access: false,
};

describe("AppShell", () => {
  beforeEach(() => {
    window.history.pushState({}, "", "/organization/acme/packages");
    setPageProps({
      branding: {
        name: "Packio",
        domain: "registry.test",
        tagline: "Private Composer registry",
        themeStorageKey: "packio-theme",
      },
      organization: acme,
      organizations: [acme, beta],
      current_membership: { role: "owner" },
    });
  });

  it("highlights the section from the Inertia page URL", () => {
    window.history.pushState({}, "", "/");
    setPageUrl("/organization/acme/sources/abc");
    render(AppShell, { props: { title: "Sources", auth } });

    const active = "background-color: var(--accent-soft)";
    expect(
      screen.getByRole("link", { name: "Sources" }).getAttribute("style"),
    ).toContain(active);
    expect(
      screen.getByRole("link", { name: "Packages" }).getAttribute("style") ??
        "",
    ).not.toContain(active);
  });

  it("renders organization-aware navigation links", () => {
    render(AppShell, {
      props: {
        title: "Packages",
        auth,
      },
    });

    expect(screen.getByRole("link", { name: "Packages" })).toHaveAttribute(
      "href",
      "/organization/acme/packages",
    );
    expect(screen.getByRole("link", { name: "Sources" })).toHaveAttribute(
      "href",
      "/organization/acme/sources",
    );
    expect(screen.getByRole("link", { name: "Tokens" })).toHaveAttribute(
      "href",
      "/organization/acme/tokens",
    );
    expect(screen.getByRole("link", { name: "Admin" })).toHaveAttribute(
      "href",
      "/admin",
    );
  });

  it("does not expose a Jobs link in organization navigation, even for admins", () => {
    const { container } = render(AppShell, {
      props: {
        title: "Packages",
        auth,
      },
    });

    expect(
      screen.queryByRole("link", { name: "Jobs" }),
    ).not.toBeInTheDocument();
    const jobsLinks = Array.from(container.querySelectorAll("a")).filter(
      (link) => link.getAttribute("href")?.includes("/jobs"),
    );
    expect(jobsLinks).toEqual([]);
  });

  it("switches organization by replacing current organization segment", async () => {
    render(AppShell, {
      props: {
        title: "Packages",
        auth,
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: /Org Acme/ }));
    await fireEvent.click(screen.getByRole("button", { name: "Beta" }));

    expect(router.get).toHaveBeenCalledWith(
      "/organization/beta/packages",
      {},
      {
        preserveState: false,
        preserveScroll: true,
      },
    );
  });

  it("keeps desktop and mobile organization create forms independent", async () => {
    const { container } = render(AppShell, {
      props: {
        title: "Packages",
        auth,
      },
    });
    // [mobile, desktop]: whether each organization menu shows its create form.
    const forms = () =>
      Array.from(container.querySelectorAll("[data-organization-menu]")).map(
        (menu) => menu.querySelector("form") !== null,
      );

    await fireEvent.click(screen.getByRole("button", { name: "Open menu" }));
    await fireEvent.click(
      screen.getByRole("button", { name: /Organization Acme/ }),
    );
    await fireEvent.click(
      screen.getByRole("button", { name: /Add organization/ }),
    );
    expect(forms()).toEqual([true, false]);

    await fireEvent.click(screen.getByRole("button", { name: /Org Acme/ }));
    expect(forms()).toEqual([true, false]);
    await fireEvent.click(
      screen.getByRole("button", { name: /Add organization/ }),
    );
    expect(forms()).toEqual([true, true]);

    await fireEvent.click(
      within(container.querySelector("[data-organization-menu]")!).getByRole(
        "button",
        { name: "Cancel" },
      ),
    );
    expect(forms()).toEqual([false, true]);
  });

  it("posts logout from the user menu", async () => {
    render(AppShell, {
      props: {
        title: "Packages",
        auth,
      },
    });

    await fireEvent.click(screen.getByRole("button", { name: /Test User/ }));
    const menu = screen.getByRole("menu");
    await fireEvent.click(
      within(menu).getByRole("menuitem", { name: "Logout" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      "/auth/logout",
      {},
      {
        preserveScroll: true,
      },
    );
  });

  it("toggles and stores document theme", async () => {
    render(AppShell, {
      props: {
        title: "Packages",
        auth,
      },
    });

    await fireEvent.click(
      screen.getAllByRole("button", { name: "Switch to dark theme" }).at(-1)!,
    );

    expect(document.documentElement.dataset.theme).toBe("dark");
    expect(localStorage.getItem("packio-theme")).toBe("dark");
  });

  it("renders the flash message in a sticky wrapper below the header on xl", () => {
    Object.assign(page, { flash: { success: "Saved" } });

    try {
      const { container } = render(AppShell, {
        props: {
          title: "Packages",
          auth,
        },
      });

      const flash = container.querySelector(".flash-message");
      expect(flash).not.toBeNull();
      expect(flash?.parentElement).toHaveClass("sticky");
      // No inline offset until the header height is measured in the browser.
      expect(flash?.parentElement?.style.top).toBe("");
    } finally {
      delete (page as unknown as { flash?: unknown }).flash;
    }
  });
});
