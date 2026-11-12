import { fireEvent, render, screen } from "@testing-library/svelte";
import { tick } from "svelte";
import { describe, expect, it, vi } from "vitest";
import { router, setPageProps } from "@/test/inertia";
import TokensIndex from "./Index.svelte";
import type { RegistryTokenPayload } from "@/types/pages";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const organization = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  route_key: "acme",
  anonymous_access: false,
};

const token: RegistryTokenPayload & { plain_token?: string } = {
  id: "token-1",
  name: "CI token",
  masked_token: "rt_****1234",
  permissions: ["packages.read.private", "packages.write"],
  created_at: "2024-01-02T03:04:05",
  last_used_at: null,
  plain_token: "rt_plain",
};

describe("Tokens index page", () => {
  it("allows managers to issue tokens", async () => {
    render(TokensIndex, {
      props: {
        organization,
        can_manage_tokens: true,
      },
    });

    await fireEvent.input(screen.getByLabelText("Token name"), {
      target: { value: "Deploy token" },
    });
    await fireEvent.submit(screen.getByRole("button", { name: "Issue token" }));

    expect(router.post).toHaveBeenCalledWith(
      expect.stringContaining("/organization/acme/tokens"),
      { name: "Deploy token" },
      {
        preserveScroll: true,
      },
    );
  });

  it("allows organization owners to revoke tokens and copy plain token", async () => {
    setPageProps({ current_membership: { role: "owner" } });
    render(TokensIndex, {
      props: {
        organization,
        tokens: [token],
      },
    });

    expect(screen.getByText("Private packages")).toBeInTheDocument();
    expect(screen.getByText("Packages / Write")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Copy token" }),
    ).toBeInTheDocument();

    await fireEvent.click(screen.getByRole("button", { name: "Revoke" }));
    await fireEvent.click(screen.getByRole("button", { name: "Revoke token" }));

    expect(router.post).toHaveBeenCalledWith(
      "/organization/acme/tokens/token-1/revoke",
      {},
      expect.objectContaining({ preserveScroll: true }),
    );

    const options = router.post.mock.calls[0][2] as { onFinish?: () => void };
    options.onFinish?.();
    await tick();

    expect(
      screen.queryByRole("dialog", { name: "Revoke token?" }),
    ).not.toBeInTheDocument();
  });

  it("hides management controls from non-managers", () => {
    setPageProps({ current_membership: { role: "member" } });
    render(TokensIndex, {
      props: {
        organization,
        tokens: [token],
      },
    });

    expect(
      screen.queryByRole("button", { name: "Revoke" }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Issue token" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByText(
        "Owner membership is required to issue organization tokens.",
      ),
    ).toBeInTheDocument();
  });
});
