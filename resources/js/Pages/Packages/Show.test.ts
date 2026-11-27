import { fireEvent, render, screen, within } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@inertiajs/svelte";
import { formatDate } from "@/lib/date";
import { setPageProps, setPageUrl } from "@/test/inertia";
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

    expect(screen.getByText("7 available · 54 hidden")).toBeInTheDocument();

    await page.rerender({ organization, packageData, releases: [] });
    expect(screen.getByText("0 available · 0 hidden")).toBeInTheDocument();
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
});

const release = (version: string, active = true) => ({
  active,
  version,
  reference: `ref-${version}`,
  created_at: "2026-01-01T00:00:00+00:00",
  released_at: "2026-01-01T00:00:00+00:00",
  dist_url: "",
  source_url: "",
  download_count: 0,
});

describe("Package summary", () => {
  it("shows the summary and install command", () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData: {
          ...packageData,
          download_count: 42,
          anonymous_access: false,
        },
        releases: [release("1.2.3")],
      },
    });

    expect(
      screen.getByRole("heading", { level: 2, name: "acme/app" }),
    ).toBeInTheDocument();
    expect(screen.getByText("Package description")).toBeInTheDocument();
    expect(screen.getAllByText("1.2.3").length).toBeGreaterThan(0);
    expect(screen.getByText("Token required")).toBeInTheDocument();
    expect(screen.getAllByText("Listed in registry").length).toBeGreaterThan(0);
    expect(screen.getByText("42 downloads")).toBeInTheDocument();
    expect(screen.getByText("composer require acme/app")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Copy" })).toBeInTheDocument();
  });

  it("explains token requirement with a Setup link", () => {
    setPageProps({ current_membership: { role: "owner" } });
    render(PackagesShow, {
      props: {
        organization,
        packageData: { ...packageData, visibility: "private" },
        releases: [release("1.2.3")],
      },
    });

    expect(
      screen.getByText("Installing requires a registry token."),
    ).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Setup" })).toHaveAttribute(
      "href",
      "/organization/acme/registry/setup",
    );
  });

  it("shows no available version when every release is hidden", () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData,
        releases: [release("1.2.3", false)],
      },
    });

    expect(screen.getByText("No available version")).toBeInTheDocument();
    expect(screen.queryByText(/composer require/)).not.toBeInTheDocument();
  });

  it("hides the install command for a package hidden from the registry", () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData: { ...packageData, active: false },
        releases: [release("1.2.3")],
      },
    });

    expect(screen.getByText("Hidden from registry")).toBeInTheDocument();
    expect(screen.queryByText(/composer require/)).not.toBeInTheDocument();
  });

  it("links to the Versions and README sections in order", () => {
    render(PackagesShow, {
      props: { organization, packageData, releases: [release("1.2.3")] },
    });

    expect(screen.getByRole("link", { name: "Versions" })).toHaveAttribute(
      "href",
      "#versions",
    );
    expect(screen.getByRole("link", { name: "README" })).toHaveAttribute(
      "href",
      "#readme",
    );
    const versions = document.getElementById("versions")!;
    const readme = document.getElementById("readme")!;
    expect(
      versions.compareDocumentPosition(readme) &
        Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();
  });

  it("shows an empty README state", () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData: { ...packageData, readme_html: null },
        releases: [],
      },
    });

    expect(document.getElementById("readme")).toHaveTextContent(
      "This package has no README.",
    );
  });
});

describe("Version rows", () => {
  const manager = {
    can_manage_packages: true,
    organization,
    packageData,
  };

  it("shows number, date, status and downloads with details collapsed", async () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData,
        releases: [
          {
            ...release("1.2.3"),
            download_count: 5,
            dist_url: "https://dist.test/a.zip",
          },
        ],
      },
    });

    const summary = document.querySelector("#versions summary") as HTMLElement;
    expect(summary).toHaveTextContent("1.2.3");
    expect(summary).toHaveTextContent(formatDate("2026-01-01T00:00:00+00:00"));
    expect(summary).toHaveTextContent("Listed in registry");
    expect(summary).toHaveTextContent("5 downloads");
    const details = summary.closest("details") as HTMLDetailsElement;
    expect(details.open).toBe(false);
    expect(details).toHaveTextContent("ref-1.2.3");
    expect(details).toHaveTextContent("https://dist.test/a.zip");
    expect(details).toHaveTextContent("Imported");

    await fireEvent.click(summary);
    expect(details.open).toBe(true);
  });

  it("shows an empty state", () => {
    render(PackagesShow, { props: { organization, packageData } });

    expect(screen.getByText("No versions indexed yet.")).toBeInTheDocument();
  });

  it("hides actions without manage permission", () => {
    render(PackagesShow, {
      props: { organization, packageData, releases: [release("1.2.3")] },
    });

    expect(
      screen.queryByRole("button", { name: "Hide release" }),
    ).not.toBeInTheDocument();
  });

  it("confirms hiding a version before posting", async () => {
    vi.mocked(router.post).mockClear();
    render(PackagesShow, {
      props: {
        ...manager,
        releases: [release("dev-feature/x"), release("1.0.0", false)],
      },
    });

    expect(screen.getByText("1 available · 1 hidden")).toBeInTheDocument();
    await fireEvent.click(screen.getByRole("button", { name: "Hide release" }));
    const dialog = screen.getByRole("dialog");
    expect(dialog).toHaveTextContent("Hide release dev-feature/x?");
    expect(dialog).toHaveTextContent(
      "Composer will stop resolving dev-feature/x",
    );

    await fireEvent.click(screen.getByRole("button", { name: "Cancel" }));
    expect(router.post).not.toHaveBeenCalled();
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

    await fireEvent.click(screen.getByRole("button", { name: "Hide release" }));
    await fireEvent.click(
      within(screen.getByRole("dialog")).getByRole("button", {
        name: "Hide release",
      }),
    );
    expect(router.post).toHaveBeenCalledWith(
      "/organization/acme/packages/acme/app/releases/activity",
      { version: "dev-feature/x", active: "0" },
      expect.objectContaining({ preserveScroll: true, preserveUrl: true }),
    );
  });

  it("restores a hidden version", async () => {
    vi.mocked(router.post).mockClear();
    render(PackagesShow, {
      props: { ...manager, releases: [release("dev-feature/y", false)] },
    });

    await fireEvent.click(
      screen.getByRole("button", { name: "Restore release" }),
    );
    expect(screen.getByRole("dialog")).toHaveTextContent(
      "Restore release dev-feature/y?",
    );
    await fireEvent.click(
      within(screen.getByRole("dialog")).getByRole("button", {
        name: "Restore release",
      }),
    );
    expect(router.post).toHaveBeenCalledWith(
      "/organization/acme/packages/acme/app/releases/activity",
      { version: "dev-feature/y", active: "1" },
      expect.objectContaining({ preserveScroll: true, preserveUrl: true }),
    );
  });
});

describe("Source details", () => {
  const managedSource = {
    active: true,
    archived: false,
    default_ref: "main",
    id: "source-1",
    last_failed_sync_at: null,
    last_successful_sync_at: "2026-01-03T00:00:00+00:00",
    last_sync_error: null,
    last_sync_status: "success",
    name: "acme/app-source",
    type: "github",
    url: "https://github.example.test/acme/app-source-repository",
    visibility: "public" as const,
  };

  it("links a managed package to its source in the summary", () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData: { ...packageData, source_summary: managedSource },
        releases: [release("1.2.3")],
      },
    });

    expect(
      screen.getByRole("link", { name: "acme/app-source" }),
    ).toHaveAttribute("href", "/organization/acme/sources/source-1");
    expect(screen.queryByText("Managed by source")).not.toBeInTheDocument();
    expect(
      screen.queryByRole("link", { name: "Open source details" }),
    ).not.toBeInTheDocument();
  });

  it("shows a failed sync and its error in the summary without expanding details", () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData: {
          ...packageData,
          source_summary: {
            ...managedSource,
            last_sync_status: "failed",
            last_sync_error: "Clone failed: repository not found",
          },
        },
        releases: [],
      },
    });

    const summary = screen.getByRole("link", { name: "acme/app-source" })
      .parentElement as HTMLElement;
    expect(within(summary).getByText("Sync failed")).toBeInTheDocument();
    expect(
      screen.getByText("Clone failed: repository not found"),
    ).toBeVisible();
    expect(
      screen.getByText("Source details").closest("details"),
    ).not.toHaveAttribute("open");
  });

  it("lists provider, URL, branch, sync and package dates in Source details", () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData: { ...packageData, source_summary: managedSource },
        releases: [],
      },
    });

    const details = screen.getByText("Source details").closest("details")!;
    expect(details.open).toBe(false);
    expect(within(details).getByText("Provider")).toBeInTheDocument();
    expect(within(details).getByText("github")).toBeInTheDocument();
    expect(within(details).getByText("URL")).toBeInTheDocument();
    expect(within(details).getByText(managedSource.url)).toHaveClass(
      "break-all",
    );
    expect(within(details).getByText("Default branch")).toBeInTheDocument();
    expect(within(details).getByText("main")).toBeInTheDocument();
    expect(
      within(details).getByText("Last successful sync"),
    ).toBeInTheDocument();
    expect(
      within(details).getByText(
        formatDate(managedSource.last_successful_sync_at),
      ),
    ).toBeInTheDocument();
    expect(within(details).getByText("Package type")).toBeInTheDocument();
    expect(within(details).getByText("library")).toBeInTheDocument();
    expect(within(details).getByText("Created")).toBeInTheDocument();
    expect(within(details).getByText("Updated")).toBeInTheDocument();
  });

  it("omits Last successful sync and Updated when they are not set", () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData: {
          ...packageData,
          updated_at: null,
          source_summary: { ...managedSource, last_successful_sync_at: null },
        },
        releases: [],
      },
    });

    const details = screen.getByText("Source details").closest("details")!;
    expect(
      within(details).queryByText("Last successful sync"),
    ).not.toBeInTheDocument();
    expect(within(details).queryByText("Updated")).not.toBeInTheDocument();
  });

  it("shows package details for a manual package without a source link", () => {
    render(PackagesShow, {
      props: { organization, packageData, releases: [] },
    });

    expect(
      screen.queryByRole("link", { name: /acme\/app-source/ }),
    ).not.toBeInTheDocument();
    const details = screen.getByText("Package details").closest("details")!;
    expect(within(details).getByText("Package type")).toBeInTheDocument();
    expect(within(details).getByText("Source URL")).toBeInTheDocument();
    expect(
      within(details).getByText("https://example.test/acme/app"),
    ).toBeInTheDocument();
    expect(within(details).getByText("Created")).toBeInTheDocument();
    expect(within(details).getByText("Updated")).toBeInTheDocument();
    expect(
      within(details).queryByText("Default branch"),
    ).not.toBeInTheDocument();
  });

  it("omits the manual Source URL when it is empty", () => {
    render(PackagesShow, {
      props: {
        organization,
        packageData: { ...packageData, source_url: "" },
        releases: [],
      },
    });

    const details = screen.getByText("Package details").closest("details")!;
    expect(within(details).queryByText("Source URL")).not.toBeInTheDocument();
  });
});
