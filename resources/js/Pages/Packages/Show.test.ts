import { render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { setPageUrl } from "@/test/inertia";
import PackagesShow from "./Show.svelte";
import type { AuthPayload } from "@/types/auth";
import type { OrganizationSummary } from "@/types/organization";
import type { PackageDetailsPayload } from "@/types/pages";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const organization: OrganizationSummary = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  anonymous_access: true,
};

const packageData: PackageDetailsPayload = {
  access_hint: "Public",
  active: true,
  anonymous_access: true,
  created_at: "2026-01-01T00:00:00+00:00",
  description: "Package description",
  download_count: 3,
  name: "acme/app",
  source_summary: null,
  source_type: "manual",
  source_url: "https://example.test/acme/app",
  type: "library",
  updated_at: "2026-01-02T00:00:00+00:00",
  visibility: "public",
};

function renderPage(auth: AuthPayload): void {
  render(PackagesShow, {
    props: {
      auth,
      organization,
      packageData,
      releases: [],
    },
  });
}

describe("Package details page", () => {
  it("counts listed and hidden releases across all pages and updates the totals", async () => {
    const releases = Array.from({ length: 61 }, (_, index) => ({
      active: index < 7,
      version: index === 0 ? "dev-main" : `1.0.${index}`,
      reference: `ref-${index}`,
      created_at: "2026-01-01T00:00:00+00:00",
      dist_url: "",
      source_url: "",
      download_count: 0,
    }));
    const page = render(PackagesShow, {
      props: { organization, packageData, releases },
    });

    expect(screen.getByText("7 listed in registry")).toBeInTheDocument();
    expect(screen.getByText("54 hidden from registry")).toBeInTheDocument();
    expect(screen.getByText("61 total releases")).toBeInTheDocument();

    await page.rerender({ organization, packageData, releases: [] });
    expect(screen.getByText("0 listed in registry")).toBeInTheDocument();
    expect(screen.getByText("0 hidden from registry")).toBeInTheDocument();
    expect(screen.getByText("0 total releases")).toBeInTheDocument();
  });

  it("does not reflect admin package return URLs for non-admin users", () => {
    setPageUrl(
      "/organization/acme/packages/acme/app?return_to=%2Fadmin%2Fpackages%3Fvisibility%5B%5D%3Dprivate",
    );

    renderPage({
      check: true,
      user: {
        display_name: "Member",
        email: "member@example.test",
        id: "user-1",
        is_admin: false,
        roles: ["user"],
      },
    });

    expect(
      screen.getByRole("link", { name: "← Back to packages" }),
    ).toHaveAttribute("href", "/organization/acme/packages");
  });

  it("keeps admin package return URLs for admin users", () => {
    setPageUrl(
      "/organization/acme/packages/acme/app?return_to=%2Fadmin%2Fpackages%3Fvisibility%5B%5D%3Dprivate%26page%3D2",
    );

    renderPage({
      check: true,
      user: {
        display_name: "Admin",
        email: "admin@example.test",
        id: "admin-1",
        is_admin: true,
        roles: ["admin"],
      },
    });

    expect(
      screen.getByRole("link", { name: "← Back to packages" }),
    ).toHaveAttribute("href", "/admin/packages?visibility[]=private&page=2");
  });

  it("posts release activity with the version in the body", () => {
    render(PackagesShow, {
      props: {
        auth: {
          check: true,
          user: {
            display_name: "Owner",
            email: "owner@example.test",
            id: "user-1",
            is_admin: false,
            roles: ["user"],
          },
        },
        can_manage_packages: true,
        organization,
        packageData,
        releases: [
          {
            active: true,
            version: "dev-feature/x",
            reference: "ref",
            created_at: "2026-01-01T00:00:00+00:00",
            dist_url: "",
            source_url: "",
            download_count: 0,
          },
        ],
      },
    });

    const form = screen
      .getByRole("button", { name: "Hide release" })
      .closest("form") as HTMLFormElement;
    expect(form.getAttribute("action")).toBe(
      "/organization/acme/packages/acme/app/releases/activity",
    );
    expect(
      form.querySelector<HTMLInputElement>("input[name=version]")?.value,
    ).toBe("dev-feature/x");
  });
});
