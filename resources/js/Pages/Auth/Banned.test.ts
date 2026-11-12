import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import Banned from "./Banned.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Banned account page", () => {
  it("shows the ban reason and only offers logout", async () => {
    render(Banned, {
      props: {
        banned_at: "2026-08-22T10:00:00+00:00",
        reason: "Violation of project rules.",
      },
    });

    expect(
      screen.getByText("Your account has been banned."),
    ).toBeInTheDocument();
    expect(screen.getByText("Violation of project rules.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Log out" })).toBeInTheDocument();

    await fireEvent.click(screen.getByRole("button", { name: "Log out" }));

    expect(router.post).toHaveBeenCalledWith(
      "/auth/logout",
      {},
      expect.objectContaining({ onFinish: expect.any(Function) }),
    );
  });
});
