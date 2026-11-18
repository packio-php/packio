import { render, screen } from "@testing-library/svelte";
import { createRawSnippet } from "svelte";
import { describe, expect, it } from "vitest";
import QueueHealthCard from "./QueueHealthCard.svelte";

const progress = {
  pending: 3,
  running: 1,
  failed: 0,
  retrying: 1,
  stuck: 0,
  oldest_ready_age_seconds: null,
};

describe("QueueHealthCard", () => {
  it("renders queue and auth mail rows", () => {
    render(QueueHealthCard, {
      props: {
        queueProgress: progress,
        authMailProgress: progress,
        description: "All organizations",
      },
    });

    for (const label of ["Retrying", "Stuck", "Pending", "Failed"]) {
      expect(screen.getByText(label)).toBeTruthy();
    }
    expect(screen.getAllByText("Oldest ready")).toHaveLength(2);
    expect(screen.getByText("All organizations")).toBeTruthy();
  });

  it("highlights stuck jobs", () => {
    render(QueueHealthCard, {
      props: {
        queueProgress: { ...progress, stuck: 2 },
        description: "d",
      },
    });

    expect(
      screen.getByText("Stuck").closest("div")?.getAttribute("data-warning"),
    ).toBe("true");
  });

  it("renders actions snippet", () => {
    const actions = createRawSnippet(() => ({
      render: () => `<a href="/admin/jobs">All jobs</a>`,
    }));

    render(QueueHealthCard, { props: { description: "d", actions } });

    expect(screen.getByRole("link", { name: "All jobs" })).toBeTruthy();
  });
});
