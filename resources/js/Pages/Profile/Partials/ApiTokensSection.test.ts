import { fireEvent, render, screen } from "@testing-library/svelte";
import { tick } from "svelte";
import { afterEach, describe, expect, it, vi } from "vitest";
import { page, router, setPageProps } from "@/test/inertia";
import ApiTokensSection from "./ApiTokensSection.svelte";
import type { ApiAccessTokenPayload, ProfilePayload } from "@/types/pages";

const profile: ProfilePayload = {
  id: "user-1",
  display_name: "Test User",
  email: "user@example.test",
  has_password: true,
  roles: ["user"],
  created_at: "2026-01-01T00:00:00Z",
};

const token: ApiAccessTokenPayload = {
  id: "t1",
  name: "Forever",
  token_prefix: "packio_api_x",
  scopes: ["read"],
  created_at: "2026-01-02T00:00:00Z",
  last_used_at: null,
  expires_at: null,
  revoked: false,
  revoked_at: null,
};

type PostOptions = {
  onStart?: () => void;
  onFinish?: () => void;
  onSuccess?: () => void;
};

function lastPost(): { data: Record<string, unknown>; options: PostOptions } {
  const call = router.post.mock.calls.at(-1)!;
  return { data: call[1], options: call[2] };
}

async function openForm(): Promise<void> {
  await fireEvent.click(
    screen.getByRole("button", { name: "Create API token" }),
  );
  await tick();
}

async function submit(): Promise<void> {
  await fireEvent.click(
    screen.getByRole("button", { name: "Issue API token" }),
  );
  await tick();
}

afterEach(() => {
  vi.useRealTimers();
});

describe("ApiTokensSection", () => {
  it("shows the empty state and a closed form", () => {
    render(ApiTokensSection, { props: { profile } });

    expect(screen.getByText("No API tokens yet")).toBeInTheDocument();
    expect(screen.queryByLabelText("Token name")).not.toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "API documentation" }),
    ).toHaveAttribute("href", "/api/doc");
  });

  it("moves focus into the form and back on cancel", async () => {
    render(ApiTokensSection, { props: { profile } });
    const create = screen.getByRole("button", { name: "Create API token" });

    await openForm();
    expect(screen.getByLabelText("Token name")).toHaveFocus();

    await fireEvent.click(screen.getByRole("button", { name: "Cancel" }));
    await tick();
    expect(screen.queryByLabelText("Token name")).not.toBeInTheDocument();
    expect(create).toHaveFocus();
  });

  it("recalculates the preset date, clamping month ends", async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 0, 31, 10, 30, 45));
    render(ApiTokensSection, { props: { profile } });
    await openForm();

    expect(screen.getByText("expires 2026-02-28 10:30:00")).toBeInTheDocument();

    await fireEvent.click(screen.getByLabelText("3 months"));
    await tick();
    expect(screen.getByText("expires 2026-04-30 10:30:00")).toBeInTheDocument();
  });

  it("submits a preset expiry as an ISO UTC string", async () => {
    render(ApiTokensSection, { props: { profile } });
    await openForm();
    await fireEvent.input(screen.getByLabelText("Token name"), {
      target: { value: "CI" },
    });
    await submit();

    const { data, options } = lastPost();
    expect(data.name).toBe("CI");
    expect(data.scopes).toEqual(["read"]);
    expect(String(data.expires_at)).toMatch(/Z$/);
    expect(options).toMatchObject({
      preserveScroll: true,
      preserveState: "errors",
    });
  });

  it("does not post an empty or past custom date", async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 0, 15, 10, 30));
    render(ApiTokensSection, { props: { profile } });
    await openForm();
    await fireEvent.input(screen.getByLabelText("Token name"), {
      target: { value: "CI" },
    });
    await fireEvent.click(screen.getByLabelText("Custom date"));
    await tick();

    await submit();
    expect(router.post).not.toHaveBeenCalled();
    expect(screen.getByText("Choose an expiration date.")).toBeInTheDocument();

    await fireEvent.input(screen.getByLabelText("Custom expiration date"), {
      target: { value: "2026-01-01T10:00" },
    });
    await submit();
    expect(router.post).not.toHaveBeenCalled();
    expect(
      screen.getByText("Expiration date must be in the future."),
    ).toBeInTheDocument();

    await fireEvent.input(screen.getByLabelText("Custom expiration date"), {
      target: { value: "2026-03-01T10:00" },
    });
    await submit();
    expect(lastPost().data.expires_at).toBe(
      new Date(2026, 2, 1, 10, 0).toISOString(),
    );
  });

  it("sends the shown preset date even if time passes before submit", async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 0, 15, 10, 30, 45));
    render(ApiTokensSection, { props: { profile } });
    await openForm();
    await fireEvent.input(screen.getByLabelText("Token name"), {
      target: { value: "CI" },
    });
    vi.advanceTimersByTime(2 * 60 * 1000);
    await submit();

    expect(lastPost().data.expires_at).toBe(
      new Date(2026, 1, 15, 10, 30).toISOString(),
    );
  });

  it("sends an empty expires_at for no expiration", async () => {
    render(ApiTokensSection, { props: { profile } });
    await openForm();
    await fireEvent.input(screen.getByLabelText("Token name"), {
      target: { value: "CI" },
    });
    await fireEvent.click(screen.getByLabelText("No expiration"));
    await submit();

    expect(lastPost().data.expires_at).toBe("");
  });

  it("blocks a second submit while the request runs", async () => {
    render(ApiTokensSection, { props: { profile } });
    await openForm();
    await fireEvent.input(screen.getByLabelText("Token name"), {
      target: { value: "CI" },
    });
    await submit();
    lastPost().options.onStart?.();
    await tick();

    expect(
      screen.getByRole("button", { name: "Issue API token" }),
    ).toBeDisabled();
    await submit();
    expect(router.post).toHaveBeenCalledTimes(1);

    lastPost().options.onFinish?.();
    await tick();
    expect(
      screen.getByRole("button", { name: "Issue API token" }),
    ).not.toBeDisabled();
  });

  it("requires a token name", async () => {
    render(ApiTokensSection, { props: { profile } });
    await openForm();
    await submit();

    expect(router.post).not.toHaveBeenCalled();
    expect(screen.getByText("Token name is required.")).toBeInTheDocument();
  });

  it("shows server errors next to the fields", async () => {
    setPageProps({
      errors: {
        name: ["Name taken."],
        expires_at: ["Bad date."],
        scopes: ["Bad scope."],
        api_token: ["Could not issue."],
      },
    });
    render(ApiTokensSection, { props: { profile } });
    await openForm();

    for (const text of [
      "Name taken.",
      "Bad date.",
      "Bad scope.",
      "Could not issue.",
    ]) {
      expect(screen.getByText(text)).toBeInTheDocument();
    }
  });

  it("keeps the empty form-level live region in the tree", async () => {
    render(ApiTokensSection, { props: { profile } });
    await openForm();

    const alert = screen.getByRole("alert");
    expect(alert.matches(":empty")).toBe(true);
  });

  it("shows the issued token, copies it and hides it on Done", async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.assign(navigator, { clipboard: { writeText } });
    Object.assign(page, {
      flash: { issued_api_token: { name: "CI", token: "packio_secret_value" } },
    });
    render(ApiTokensSection, { props: { profile } });

    expect(screen.getByText("packio_secret_value")).toBeInTheDocument();
    expect(
      screen.getByRole("group", { name: /Token "CI" created/ }),
    ).toHaveFocus();
    await fireEvent.click(screen.getByRole("button", { name: "Copy token" }));
    await tick();
    expect(writeText).toHaveBeenCalledWith("packio_secret_value");
    expect(screen.getByText("Copied")).toBeInTheDocument();

    await fireEvent.click(screen.getByRole("button", { name: "Done" }));
    await tick();
    expect(screen.queryByText("packio_secret_value")).not.toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Create API token" }),
    ).toHaveFocus();
  });

  it("reports a clipboard failure", async () => {
    Object.assign(navigator, {
      clipboard: { writeText: vi.fn().mockRejectedValue(new Error("denied")) },
    });
    Object.assign(page, {
      flash: { issued_api_token: { name: "CI", token: "packio_secret_value" } },
    });
    render(ApiTokensSection, { props: { profile } });

    await fireEvent.click(screen.getByRole("button", { name: "Copy token" }));
    await tick();
    expect(
      screen.getByText("Copy failed. Select the token and copy it manually."),
    ).toBeInTheDocument();
  });

  it("labels tokens without expiry", () => {
    render(ApiTokensSection, { props: { profile, api_tokens: [token] } });

    expect(screen.getByText("Never expires")).toBeInTheDocument();
  });
});
