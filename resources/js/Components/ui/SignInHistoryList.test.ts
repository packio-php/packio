import { render, screen } from "@testing-library/svelte";
import { describe, expect, it } from "vitest";
import SignInHistoryList from "./SignInHistoryList.svelte";

describe("SignInHistoryList", () => {
  it("renders method, IP and browser", () => {
    render(SignInHistoryList, {
      props: {
        entries: [
          {
            id: "1",
            method: "github",
            method_label: "GitHub",
            occurred_at: "2026-11-22T10:00:00Z",
            ip: "203.0.113.5",
            user_agent:
              "Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0",
          },
        ],
      },
    });

    expect(screen.getByText("GitHub")).toBeInTheDocument();
    expect(screen.getByText("203.0.113.5")).toBeInTheDocument();
    expect(screen.getByText("Firefox on Linux")).toBeInTheDocument();
  });

  it("shows placeholders for missing data", () => {
    render(SignInHistoryList, {
      props: {
        entries: [
          {
            id: "1",
            method: "password",
            method_label: "Password",
            occurred_at: "2026-11-22T10:00:00Z",
            ip: null,
            user_agent: null,
          },
        ],
      },
    });

    expect(screen.getByText("—")).toBeInTheDocument();
    expect(screen.getByText("Unknown browser")).toBeInTheDocument();
  });

  it("shows the empty state", () => {
    render(SignInHistoryList, { props: { entries: [] } });

    expect(screen.getByText("No sign-ins recorded yet.")).toBeInTheDocument();
  });

  it("labels the time zone once and keeps full IPv6 and field labels", () => {
    const ipv6 = "2001:0db8:85a3:0000:0000:8a2e:0370:7334";
    const { container } = render(SignInHistoryList, {
      props: {
        entries: [1, 2].map((n) => ({
          id: String(n),
          method: "password",
          method_label: "Password",
          occurred_at: "2026-11-22T10:00:00Z",
          ip: ipv6,
          user_agent: null,
        })),
      },
    });

    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    expect(screen.getAllByText(`Local time (${zone})`)).toHaveLength(1);
    expect(screen.getAllByText(ipv6)).toHaveLength(2);
    // jsdom ignores CSS: the narrow layout is the same cells with data-label
    const labels = [...container.querySelectorAll("tbody tr:first-child td")];
    expect(labels.map((td) => td.getAttribute("data-label"))).toEqual([
      "Time",
      "Method",
      "IP address",
      "Browser",
    ]);
    expect(container.querySelector(".break-all")).not.toBeNull();
  });
});
