import { render, screen, waitFor } from "@testing-library/svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { page, setPageProps } from "@/test/inertia";
import AdminShellHarness from "@/test/AdminShellHarness.svelte";

vi.mock("@/Components/ui/ScrollTopButton.svelte", async () => ({
  default: (await import("@/test/ScrollTopButtonStub.svelte")).default,
}));

const auth = {
  user: {
    id: "admin-1",
    display_name: "Admin User",
    email: "admin@example.test",
    roles: ["admin"],
  },
};

beforeEach(() => {
  setPageProps({
    branding: {
      name: "Packio",
      domain: "registry.test",
      themeStorageKey: "packio-theme",
    },
  });
});

describe("AdminShell", () => {
  it("follows page prop updates after navigation", async () => {
    render(AdminShellHarness, { props: { auth } });
    expect(screen.getAllByText("registry.test").length).toBeGreaterThan(0);

    page.props = { branding: { name: "Packio", domain: "next.test" } };

    await waitFor(() =>
      expect(screen.getAllByText("next.test").length).toBeGreaterThan(0),
    );
  });

  it("renders the xl app shell with a fixed sidebar and content scroll column", () => {
    const { container } = render(AdminShellHarness, { props: { auth } });
    const root = container.firstElementChild as HTMLElement;

    expect(root).toHaveClass("xl:h-dvh");
    expect(root).toHaveClass("xl:overflow-hidden");
    expect(root.querySelector("aside.admin-sidebar")).not.toBeNull();

    const contentScroll = root.querySelector("div.admin-content-scroll");
    expect(contentScroll).not.toBeNull();
    expect(contentScroll).toHaveClass("xl:overflow-y-auto");

    const inner = root.querySelector(".admin-content-inner");
    expect(inner).not.toBeNull();
    expect(inner).toHaveClass("max-w-[80rem]");
    expect(inner?.closest("main")).not.toBeNull();
    expect(inner?.closest("div.admin-content-scroll")).not.toBeNull();
  });

  it("still exposes the admin jobs link in the sidebar", () => {
    render(AdminShellHarness, { props: { auth } });

    const jobsLink = document.querySelector('a[href="/admin/jobs"]');
    expect(jobsLink).not.toBeNull();
    expect(jobsLink?.textContent?.trim()).toContain("Jobs");
  });

  it("wires ScrollTopButton to the content scroll container without a content reference", () => {
    render(AdminShellHarness, { props: { auth } });
    const stub = screen.getByTestId("scroll-top-button-stub");

    expect(stub.getAttribute("data-reference-selector")).toBe("");
    expect(stub.getAttribute("data-container-class") ?? "").toContain(
      "admin-content-scroll",
    );
  });

  it("renders the flash message as the first child of the content scroll column", () => {
    Object.assign(page, { flash: { success: "Changes saved" } });

    try {
      const { container } = render(AdminShellHarness, { props: { auth } });
      const contentScroll = container.querySelector("div.admin-content-scroll");

      expect(contentScroll?.firstElementChild).not.toBeNull();
      expect(contentScroll?.firstElementChild).toHaveClass("sticky");
      expect(contentScroll?.firstElementChild).toHaveClass("xl:top-0");
      expect(contentScroll?.firstElementChild).toHaveClass(
        "bg-[var(--page-bg)]",
      );
      expect(
        contentScroll?.firstElementChild?.querySelector(".flash-message"),
      ).not.toBeNull();
      expect(
        contentScroll?.firstElementChild?.querySelector(".flash-message")
          ?.parentElement,
      ).toHaveClass("max-w-[80rem]");
    } finally {
      delete (page as unknown as { flash?: unknown }).flash;
    }
  });
});
