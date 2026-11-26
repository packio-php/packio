import { fireEvent, render, screen } from "@testing-library/svelte";
import { describe, expect, it } from "vitest";
import type { SignInHistoryEntry } from "@/types/pages";
import SignInHistorySection from "./SignInHistorySection.svelte";

const make = (n: number): SignInHistoryEntry[] =>
  Array.from({ length: n }, (_, i) => ({
    id: String(i),
    method: "password",
    method_label: "Password",
    occurred_at: "2026-11-22T10:00:00Z",
    ip: "203.0.113.5",
    user_agent: null,
  }));

const rows = () => screen.queryAllByRole("row").length - 1;

describe("SignInHistorySection", () => {
  it("shows the empty state without a button", () => {
    render(SignInHistorySection, { props: { sign_in_history: [] } });
    expect(screen.getByText("No sign-ins recorded yet.")).toBeInTheDocument();
    expect(screen.queryByRole("button")).toBeNull();
  });

  it("has no button for five entries", () => {
    render(SignInHistorySection, { props: { sign_in_history: make(5) } });
    expect(rows()).toBe(5);
    expect(screen.queryByRole("button")).toBeNull();
  });

  it("offers to show all six", () => {
    render(SignInHistorySection, { props: { sign_in_history: make(6) } });
    expect(rows()).toBe(5);
    expect(
      screen.getByRole("button", { name: "Show all 6 sign-ins" }),
    ).toBeInTheDocument();
  });

  it("expands and collapses twenty entries", async () => {
    render(SignInHistorySection, { props: { sign_in_history: make(20) } });
    expect(rows()).toBe(5);

    await fireEvent.click(
      screen.getByRole("button", { name: "Show all 20 sign-ins" }),
    );
    expect(rows()).toBe(20);

    await fireEvent.click(screen.getByRole("button", { name: "Show fewer" }));
    expect(rows()).toBe(5);
  });
});
