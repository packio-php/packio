import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import OrganizationsIndex from "./Index.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Organizations index page", () => {
  it("renders create organization field errors", () => {
    render(OrganizationsIndex, {
      props: {
        errors: {
          name: ["Organization name is reserved."],
        },
        old: {
          name: "Admin",
        },
      },
    });

    expect(screen.getByLabelText("Organization name")).toHaveValue("Admin");
    expect(screen.getByLabelText("Organization name")).toHaveAttribute(
      "aria-invalid",
      "true",
    );
    expect(
      screen.getByText("Organization name is reserved."),
    ).toBeInTheDocument();
  });

  it("posts create organization payload through the Inertia client", async () => {
    render(OrganizationsIndex);

    await fireEvent.input(screen.getByLabelText("Organization name"), {
      target: { value: "Platform Team" },
    });
    await fireEvent.submit(
      screen.getByRole("button", { name: "Create organization" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      "/organizations",
      { name: "Platform Team" },
      expect.objectContaining({
        preserveScroll: true,
      }),
    );
  });
});
