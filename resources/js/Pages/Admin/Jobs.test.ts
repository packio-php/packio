import { fireEvent, render, screen, within } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import AdminJobs from "./Jobs.svelte";

vi.mock("@/Layouts/AdminShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Admin jobs page", () => {
  it("shows both queue summaries and explains an idle ready queue", async () => {
    const { rerender } = render(AdminJobs, {
      props: {
        queue_progress: {
          pending: 4,
          running: 2,
          failed: 1,
          retrying: 1,
          stuck: 1,
          oldest_ready_age_seconds: 120,
        },
        auth_mail_progress: {
          pending: 1,
          running: 0,
          failed: 0,
          retrying: 0,
          stuck: 0,
          oldest_ready_age_seconds: 600,
        },
      },
    });

    const queue = screen.getByRole("region", { name: "Queue progress" });
    for (const [label, value] of [
      ["Pending", "4"],
      ["Running", "2"],
      ["Failed", "1"],
      ["Retrying", "1"],
      ["Stuck", "1"],
      ["Oldest ready job", "120 seconds"],
    ]) {
      expect(within(queue).getByText(label).parentElement).toHaveTextContent(
        new RegExp(`${label}\\s*${value}`),
      );
    }

    const mail = screen.getByRole("region", { name: "System auth mail" });
    for (const [label, value] of [
      ["Pending", "1"],
      ["Running", "0"],
      ["Failed", "0"],
      ["Retrying", "0"],
      ["Stuck", "0"],
      ["Oldest ready job", "600 seconds"],
    ]) {
      expect(within(mail).getByText(label).parentElement).toHaveTextContent(
        new RegExp(`${label}\\s*${value}`),
      );
    }

    const idle = {
      pending: 0,
      running: 0,
      failed: 0,
      retrying: 0,
      stuck: 0,
      oldest_ready_age_seconds: null,
    };
    await rerender({ queue_progress: idle, auth_mail_progress: idle });

    expect(
      within(screen.getByRole("region", { name: "Queue progress" })).getByText(
        "No ready jobs",
      ),
    ).toBeInTheDocument();
  });

  it("trims text filters before navigating", async () => {
    render(AdminJobs);

    await fireEvent.click(screen.getByRole("button", { name: "Show filters" }));
    await fireEvent.input(screen.getByLabelText("Search"), {
      target: { value: "  job-1  " },
    });
    await fireEvent.click(
      screen.getByRole("button", { name: "Apply filters" }),
    );

    expect(router.get).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({ search: "job-1" }),
      expect.anything(),
    );
  });
});
