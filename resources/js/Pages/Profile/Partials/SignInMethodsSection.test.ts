import { fireEvent, render, screen } from "@testing-library/svelte";
import { tick } from "svelte";
import { describe, expect, it } from "vitest";
import { Endpoints } from "@/lib/api";
import { router, setPageProps } from "@/test/inertia";
import SignInMethodsSection from "./SignInMethodsSection.svelte";
import type { ProfileOAuthAccountPayload, ProfilePayload } from "@/types/pages";

function profileWith(hasPassword: boolean): ProfilePayload {
  return {
    id: "user-1",
    display_name: "Test User",
    email: "user@example.test",
    has_password: hasPassword,
    roles: ["user"],
    created_at: "2026-01-01T00:00:00Z",
    updated_at: null,
  };
}

type PostOptions = { onStart?: () => void; onSuccess?: () => void };

function lastPost(): { data: Record<string, string>; options: PostOptions } {
  const call = router.post.mock.calls.at(-1)!;
  return { data: call[1], options: call[2] };
}

describe("SignInMethodsSection password row", () => {
  it("shows Not set with Set password and a closed form", () => {
    render(SignInMethodsSection, { props: { profile: profileWith(false) } });

    expect(screen.getAllByText("Sign-in methods")).toHaveLength(1);
    expect(screen.getByText("Not set")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Set password" }),
    ).toBeInTheDocument();
    expect(screen.queryByLabelText("New password")).not.toBeInTheDocument();
  });

  it("opens the set form with two fields and focuses the first", async () => {
    render(SignInMethodsSection, { props: { profile: profileWith(false) } });
    await fireEvent.click(screen.getByRole("button", { name: "Set password" }));
    await tick();

    expect(screen.queryByLabelText("Current password")).not.toBeInTheDocument();
    expect(screen.getByLabelText("New password")).toHaveFocus();
    expect(screen.getByLabelText("Confirm new password")).toBeInTheDocument();
    expect(
      screen.getByText(/Min 8 characters, at least one digit/),
    ).toBeInTheDocument();
    expect(
      screen.getAllByRole("button", { name: "Set password" }),
    ).toHaveLength(2);
  });

  it("opens the change form with three fields and focuses the first", async () => {
    render(SignInMethodsSection, { props: { profile: profileWith(true) } });
    expect(screen.getByText("Set")).toBeInTheDocument();
    await fireEvent.click(
      screen.getByRole("button", { name: "Change password" }),
    );
    await tick();

    expect(screen.getByLabelText("Current password")).toHaveFocus();
    expect(screen.getByLabelText("New password")).toBeInTheDocument();
    expect(screen.getByLabelText("Confirm new password")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Save password" }),
    ).toBeInTheDocument();
  });

  it("returns focus to the row action on cancel", async () => {
    render(SignInMethodsSection, { props: { profile: profileWith(true) } });
    const action = screen.getByRole("button", { name: "Change password" });
    await fireEvent.click(action);
    await tick();
    await fireEvent.click(screen.getByRole("button", { name: "Cancel" }));
    await tick();

    expect(screen.queryByLabelText("New password")).not.toBeInTheDocument();
    expect(action).toHaveFocus();
  });

  it("posts the change payload and closes the form on success", async () => {
    render(SignInMethodsSection, { props: { profile: profileWith(true) } });
    const action = screen.getByRole("button", { name: "Change password" });
    await fireEvent.click(action);
    await tick();
    await fireEvent.input(screen.getByLabelText("Current password"), {
      target: { value: "old" },
    });
    await fireEvent.input(screen.getByLabelText("New password"), {
      target: { value: "New-pass1!" },
    });
    await fireEvent.input(screen.getByLabelText("Confirm new password"), {
      target: { value: "New-pass1!" },
    });
    await fireEvent.click(
      screen.getByRole("button", { name: "Save password" }),
    );

    const { data, options } = lastPost();
    expect(data).toEqual({
      current_password: "old",
      new_password: "New-pass1!",
      new_password_confirmation: "New-pass1!",
    });
    expect(options).toMatchObject({
      preserveScroll: true,
      preserveState: "errors",
    });

    options.onSuccess?.();
    await tick();
    await tick();
    expect(screen.queryByLabelText("New password")).not.toBeInTheDocument();
    expect(action).toHaveFocus();
  });

  it("omits current_password when setting a first password", async () => {
    render(SignInMethodsSection, { props: { profile: profileWith(false) } });
    await fireEvent.click(screen.getByRole("button", { name: "Set password" }));
    await tick();
    const form = screen.getByLabelText("New password").closest("form")!;
    await fireEvent.submit(form);

    expect(Object.keys(lastPost().data)).toEqual([
      "new_password",
      "new_password_confirmation",
    ]);
  });

  it("shows errors by the fields and keeps the form open with values", async () => {
    setPageProps({
      errors: {
        current_password: ["Current password is incorrect."],
        new_password_confirmation: ["Passwords do not match."],
      },
    });
    render(SignInMethodsSection, { props: { profile: profileWith(true) } });

    expect(
      screen.getByText("Current password is incorrect."),
    ).toBeInTheDocument();
    expect(screen.getByText("Passwords do not match.")).toBeInTheDocument();
    expect(screen.getByLabelText("New password")).toBeInTheDocument();
  });
});

function oauth(id: string, provider: string, label: string) {
  return {
    id,
    provider,
    provider_label: label,
    account_label: `${provider}-user`,
  } as ProfileOAuthAccountPayload;
}

describe("SignInMethodsSection OAuth rows", () => {
  const github = oauth("acc-1", "github", "GitHub");

  it("shows Connected and Disconnect in the row", () => {
    render(SignInMethodsSection, {
      props: { profile: profileWith(true), oauth_accounts: [github] },
    });

    expect(screen.getByText("Connected")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Disconnect" })).toBeEnabled();
  });

  it("asks for confirmation with the provider name and sends nothing on cancel", async () => {
    render(SignInMethodsSection, {
      props: { profile: profileWith(true), oauth_accounts: [github] },
    });
    await fireEvent.click(screen.getByRole("button", { name: "Disconnect" }));
    await tick();

    expect(screen.getByText("Disconnect GitHub?")).toBeInTheDocument();
    await fireEvent.click(screen.getByRole("button", { name: "Cancel" }));
    await tick();

    expect(router.post).not.toHaveBeenCalled();
    expect(screen.queryByText("Disconnect GitHub?")).not.toBeInTheDocument();
  });

  it("posts to the disconnect endpoint after confirmation", async () => {
    render(SignInMethodsSection, {
      props: { profile: profileWith(true), oauth_accounts: [github] },
    });
    await fireEvent.click(screen.getByRole("button", { name: "Disconnect" }));
    await tick();
    const buttons = screen.getAllByRole("button", { name: "Disconnect" });
    await fireEvent.click(buttons.at(-1)!);

    expect(router.post).toHaveBeenCalledTimes(1);
    expect(router.post.mock.calls[0][0]).toBe(
      Endpoints.profile.disconnectOAuth("acc-1"),
    );
  });

  it("disables the last sign-in method with an explanation", () => {
    render(SignInMethodsSection, {
      props: { profile: profileWith(false), oauth_accounts: [github] },
    });

    expect(screen.getByRole("button", { name: "Disconnect" })).toBeDisabled();
    expect(
      screen.getByText(
        "Add another sign-in method before disconnecting this one.",
      ),
    ).toBeInTheDocument();
  });

  it("allows disconnecting one of two providers without a password", () => {
    render(SignInMethodsSection, {
      props: {
        profile: profileWith(false),
        oauth_accounts: [github, oauth("acc-2", "gitlab", "GitLab")],
      },
    });

    for (const b of screen.getAllByRole("button", { name: "Disconnect" })) {
      expect(b).toBeEnabled();
    }
  });
});
