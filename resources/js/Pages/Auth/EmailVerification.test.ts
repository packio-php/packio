import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { router } from "@/test/inertia";
import EmailVerification from "./EmailVerification.svelte";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Email verification page", () => {
  it("submits only a six-digit code", async () => {
    render(EmailVerification);

    const verify = screen.getByRole("button", { name: "Verify" });
    expect(verify).toBeDisabled();

    await fireEvent.input(screen.getByLabelText("Verification code"), {
      target: { value: "123456" },
    });
    await fireEvent.submit(verify);

    expect(router.post).toHaveBeenCalledWith(
      "/auth/verification/verify",
      { code: "123456", website_url: "" },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("requests a new code", async () => {
    render(EmailVerification);

    await fireEvent.click(screen.getByRole("button", { name: "Resend code" }));

    expect(router.post).toHaveBeenCalledWith(
      "/auth/verification/resend",
      { website_url: "" },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("hides resend while the cooldown runs", () => {
    render(EmailVerification, { props: { resend_available_in: 30 } });

    expect(screen.getByText("Resend code in 30 seconds")).toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Resend code" }),
    ).not.toBeInTheDocument();
  });
});
