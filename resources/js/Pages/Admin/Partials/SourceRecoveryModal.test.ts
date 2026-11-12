import { render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import SourceRecoveryModal from "./SourceRecoveryModal.svelte";

vi.mock("@/lib/api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api")>()),
  apiClient: { get: vi.fn(() => new Promise(() => {})) },
}));

// No-op onMount: the test then sees the initial state, i.e. the first frame.
vi.mock("svelte", async (o) => ({
  ...(await o<typeof import("svelte")>()),
  onMount: () => {},
}));

describe("SourceRecoveryModal", () => {
  it("shows Loading... and no zero counts right after opening the delete action", () => {
    const { container } = render(SourceRecoveryModal, {
      source: { id: "s1", name: "repo" } as never,
      action: "delete",
      returnUrl: "/",
      onClose: () => {},
      onAction: () => {},
    });

    expect(screen.getAllByText("Loading...")).toHaveLength(2);
    expect(container.textContent).not.toMatch(/\b0\b/);
  });
});
