import { fireEvent, render, screen } from "@testing-library/svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { setPageProps } from "@/test/inertia";
import Setup from "./Setup.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const host = "acme.packio.test:8443";
const baseProps = {
  base_url: `https://${host}`,
  public_bootstrap: `https://${host}/public`,
  private_bootstrap: `https://${host}/private`,
  organization: { name: "Acme", slug: "acme", anonymous_access: true },
  public_packages: [{ name: "acme/pub" }],
};

function step(n: number): HTMLElement {
  return screen
    .getByRole("heading", { name: new RegExp(`^${n}\\.`) })
    .closest("li") as HTMLElement;
}

function copyValue() {
  return vi.mocked(navigator.clipboard.writeText);
}

async function copied(name: string, index = 0): Promise<string> {
  await fireEvent.click(screen.getAllByRole("button", { name })[index]);
  const calls = copyValue().mock.calls;
  return calls[calls.length - 1][0];
}

describe("Registry setup page", () => {
  beforeEach(() => setPageProps({ current_membership: { role: "member" } }));

  it("shows the local command with host and port and copies the shown text", async () => {
    render(Setup, { props: baseProps as never });

    const command = `composer config --global --auth http-basic.${host} token YOUR_REGISTRY_TOKEN`;
    expect(screen.getByText(command)).toBeInTheDocument();
    expect(await copied("Copy command")).toBe(command);
  });

  it("switches to CI JSON without touching steps 2 and 3", async () => {
    render(Setup, { props: baseProps as never });
    const before = [step(2).textContent, step(3).textContent];

    await fireEvent.click(screen.getByRole("button", { name: "CI" }));

    expect([step(2).textContent, step(3).textContent]).toEqual(before);

    expect(screen.getByRole("button", { name: "CI" })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
    const json = JSON.parse(await copied("Copy JSON for COMPOSER_AUTH"));
    expect(json["http-basic"][host].username).toBe("token");
  });

  it("copies the repository JSON and the bare URL", async () => {
    render(Setup, { props: baseProps as never });

    const json = await copied("Copy JSON for the repository");
    expect(json).toContain(`"url": "https://${host}/private"`);
    expect(JSON.parse(json).repositories[0].type).toBe("composer");
    expect(await copied("Copy URL of the repository")).toBe(
      `https://${host}/private`,
    );
  });

  it("falls back to the public URL when there is no private one", async () => {
    render(Setup, {
      props: { ...baseProps, private_bootstrap: null } as never,
    });

    expect(step(2).textContent).toContain(`"url": "https://${host}/public"`);
    expect(await copied("Copy URL of the repository")).toBe(
      `https://${host}/public`,
    );
  });

  it("prefers the registry host over the base URL", () => {
    render(Setup, {
      props: { ...baseProps, registry_host: "registry.packio.test" } as never,
    });

    expect(document.body.textContent).toContain(
      "http-basic.registry.packio.test token",
    );
  });

  it("links to tokens and packages", () => {
    render(Setup, { props: baseProps as never });

    expect(
      screen.getByRole("link", { name: "Get registry token" }),
    ).toHaveAttribute("href", "/organization/acme/tokens");
    expect(
      screen.getByRole("link", { name: "Browse packages" }),
    ).toHaveAttribute("href", "/organization/acme/packages");
  });

  it("expands the public block when anonymous access is enabled with public packages", () => {
    render(Setup, { props: baseProps as never });

    expect(document.querySelector("details")).toHaveAttribute("open");
    expect(document.body.textContent).toContain("without a token");
    expect(screen.getByText("Anonymous access enabled")).toBeInTheDocument();
  });

  it("explains disabled anonymous access and shows settings link to owners only", () => {
    const props = {
      ...baseProps,
      organization: { ...baseProps.organization, anonymous_access: false },
    };
    setPageProps({ current_membership: { role: "owner" } });
    const owner = render(Setup, { props: props as never });

    expect(screen.getByText("Anonymous access disabled")).toBeInTheDocument();
    expect(document.body.textContent).toContain("answers 401 even");
    expect(document.querySelector("details")).not.toHaveAttribute("open");
    expect(
      screen.getByRole("link", { name: "Open organization settings" }),
    ).toHaveAttribute("href", "/organization/acme/settings");
    owner.unmount();

    setPageProps({ current_membership: { role: "member" } });
    render(Setup, { props: props as never });
    expect(
      screen.queryByRole("link", { name: "Open organization settings" }),
    ).toBeNull();
  });

  it("collapses the public block when there are no public packages", () => {
    render(Setup, {
      props: { ...baseProps, public_packages: [] } as never,
    });

    expect(document.querySelector("details")).not.toHaveAttribute("open");
    expect(screen.getAllByText(/No public packages yet/)).toHaveLength(1);
  });

  it("copies the public URL and public composer.json snippet exactly as shown", async () => {
    render(Setup, { props: baseProps as never });

    const details = document.querySelector("details") as HTMLElement;
    const shownUrl = details.querySelector("code")?.textContent?.trim();
    const shownJson = details.querySelector("pre")?.textContent;

    expect(shownUrl).toBe(`https://${host}/public`);
    expect(await copied("Copy URL of the public repository")).toBe(shownUrl);
    expect(await copied("Copy JSON for the public repository")).toBe(shownJson);
  });

  it("offers the settings link to owners when anonymous access is disabled and no public packages exist", () => {
    setPageProps({ current_membership: { role: "owner" } });
    render(Setup, {
      props: {
        ...baseProps,
        organization: { ...baseProps.organization, anonymous_access: false },
        public_packages: [],
      } as never,
    });

    expect(
      screen.getByText(
        "Anonymous access is disabled in organization settings.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Open organization settings" }),
    ).toHaveAttribute("href", "/organization/acme/settings");
  });
});
