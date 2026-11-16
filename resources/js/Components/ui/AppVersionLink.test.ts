import { render, screen } from "@testing-library/svelte";
import { describe, expect, it } from "vitest";
import AppVersionLink from "./AppVersionLink.svelte";
import type { AppVersionInfo } from "@/types/app";

const stable: AppVersionInfo = {
  version: "0.3.2",
  number: "0.3.2",
  channel: "stable",
  sha: "abc1234",
  branch: "main",
  built_at: "2026-11-16T00:00:00Z",
  distance: 0,
  dirty: false,
  tag: "v0.3.2",
  display: "0.3.2 (abc1234)",
};

describe("AppVersionLink", () => {
  it("links a stable version to its release and the hash to the commit", () => {
    render(AppVersionLink, { props: { version: stable } });

    expect(screen.getByRole("link", { name: "v0.3.2" })).toHaveAttribute(
      "href",
      "https://github.com/packio-php/packio/releases/tag/v0.3.2",
    );
    expect(screen.getByRole("link", { name: "abc1234" })).toHaveAttribute(
      "href",
      "https://github.com/packio-php/packio/commit/abc1234",
    );
  });

  it("links only the hash for a dev build", () => {
    render(AppVersionLink, {
      props: {
        version: {
          ...stable,
          channel: "dev",
          number: "0.3.3-dev.2+abc1234",
          distance: 2,
        },
      },
    });

    expect(screen.getAllByRole("link")).toHaveLength(1);
    expect(screen.getByText(/v0\.3\.3-dev\.2\+abc1234/)).toBeInTheDocument();
  });
});
