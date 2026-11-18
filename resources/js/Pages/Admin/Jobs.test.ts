import { fireEvent, render, screen, within } from "@testing-library/svelte";
import { tick } from "svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import AdminJobs from "./Jobs.svelte";

vi.mock("@/Layouts/AdminShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const emptyFilters = {
  organization_id: "",
  status: [],
  type: [],
  search: "",
  job_id: "",
  idempotency_key: "",
  deletion_run_id: "",
  source_id: "",
  worker_id: "",
  created_from: "",
  created_to: "",
  scheduled_from: "",
  scheduled_to: "",
  has_warnings: false,
  has_error: false,
  sort: "createdAt",
  direction: "desc" as const,
  page: 1,
  per_page: 20,
};

describe("Admin jobs page", () => {
  it("shows queue health without duplicating status counts", async () => {
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

    const health = screen.getByRole("region", { name: "Queue health" });
    const queue = within(health).getByLabelText("Queue");
    const mail = within(health).getByLabelText("Auth mail");
    const expectMetric = (
      list: HTMLElement,
      label: string,
      value: string,
      warn: boolean,
    ) => {
      const tile = within(list).getByText(label).parentElement;
      expect(tile).toHaveTextContent(new RegExp(`${label}\\s*${value}`));
      expect(tile?.dataset.warning === "true").toBe(warn);
    };

    expectMetric(queue, "Retrying", "1", false);
    expectMetric(queue, "Stuck", "1", true);
    expectMetric(queue, "Oldest ready", "2m 0s", false);
    expectMetric(mail, "Pending", "1", false);
    expectMetric(mail, "Failed", "0", false);
    expectMetric(mail, "Oldest ready", "10m 0s", true);
    expect(within(queue).queryByText("Running")).not.toBeInTheDocument();

    const idle = {
      pending: 0,
      running: 0,
      failed: 0,
      retrying: 0,
      stuck: 0,
      oldest_ready_age_seconds: null,
    };
    await rerender({ queue_progress: idle, auth_mail_progress: idle });

    expect(within(health).getAllByText("None")).toHaveLength(2);
  });

  it("shows status counts and whether filters apply to them", async () => {
    const { rerender } = render(AdminJobs, {
      props: {
        status_counts: {
          pending: 3,
          running: 1,
          completed: 9,
          failed: 2,
          canceled: 0,
          held: 0,
          audited: 0,
        },
      },
    });

    const statuses = screen.getByRole("region", { name: "Statuses" });
    expect(
      within(statuses).getByText("completed").parentElement,
    ).toHaveTextContent(/completed\s*9/);
    expect(within(statuses).getByText("All jobs")).toBeInTheDocument();

    await rerender({ filters: { ...emptyFilters, status: ["failed"] } });
    expect(within(statuses).getByText("All jobs")).toBeInTheDocument();

    await rerender({ filters: { ...emptyFilters, search: "acme" } });
    expect(
      within(statuses).getByText("Matching current filters"),
    ).toBeInTheDocument();
  });

  it("reloads the page data on refresh and blocks repeated clicks", async () => {
    router.reload.mockClear();
    render(AdminJobs);

    const button = screen.getByRole("button", { name: "Refresh" });
    await fireEvent.click(button);

    expect(router.reload).toHaveBeenCalledTimes(1);
    const options = router.reload.mock.calls[0]?.[0] as {
      onStart: () => void;
      onFinish: () => void;
    };
    options.onStart();
    await tick();
    expect(button).toBeDisabled();

    options.onFinish();
    await tick();
    expect(button).toBeEnabled();
  });

  it("hides pagination when there are no jobs", () => {
    render(AdminJobs);

    expect(screen.getByText("No jobs found.")).toBeInTheDocument();
    expect(screen.queryByText("Per page:")).not.toBeInTheDocument();
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
