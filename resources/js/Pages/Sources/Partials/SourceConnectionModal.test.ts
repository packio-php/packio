import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { apiClient } from "@/lib/api";
import { toast } from "@/lib/toast";
import { router } from "@/test/inertia";
import SourceConnectionModal from "./SourceConnectionModal.svelte";
import type {
  ProviderAuthorizationPayload,
  ProviderResourcePayload,
  SourceProviderPayload,
  SourceProviderInstancePayload,
  UserOAuthAccountPayload,
} from "@/types/pages";

const organization = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  anonymous_access: false,
};

const githubProvider: SourceProviderPayload = {
  key: "github",
  name: "GitHub",
  description: "GitHub repositories",
  oauth_available: true,
  oauth_enabled: true,
  oauth_provider: "github",
  supports_self_hosted_instances: false,
  capabilities: { supports_synchronization: true },
};

const genericGitProvider: SourceProviderPayload = {
  key: "generic_git",
  name: "Generic Git",
  description: "Any Git repository",
  oauth_available: false,
  oauth_enabled: false,
  oauth_provider: null,
  supports_self_hosted_instances: false,
  capabilities: { supports_synchronization: true },
};

const disabledProvider: SourceProviderPayload = {
  key: "coming_soon",
  name: "Coming Soon",
  description: "Unavailable",
  oauth_available: false,
  oauth_enabled: false,
  oauth_provider: null,
  supports_self_hosted_instances: false,
  capabilities: { supports_synchronization: false },
};

const gitlabProvider: SourceProviderPayload = {
  key: "gitlab_self_hosted",
  name: "GitLab",
  description: "Self-hosted GitLab repositories",
  oauth_available: true,
  oauth_enabled: true,
  oauth_provider: "gitlab",
  supports_self_hosted_instances: true,
  capabilities: { supports_synchronization: true },
};

const gitlabInstance: SourceProviderInstancePayload = {
  id: "gitlab-instance-1",
  label: "Acme GitLab",
  base_url: "https://gitlab.acme.test",
  provider_type: "gitlab_self_hosted",
  auth_mode: "oauth2",
  status: "active",
};

const forgejoProvider: SourceProviderPayload = {
  key: "forgejo_self_hosted",
  name: "Forgejo",
  description: "Self-hosted Forgejo repositories",
  oauth_available: true,
  oauth_enabled: true,
  oauth_provider: "forgejo_self_hosted",
  supports_self_hosted_instances: true,
  capabilities: { supports_synchronization: true },
};

const forgejoInstance: SourceProviderInstancePayload = {
  id: "forgejo-instance-1",
  label: "Acme Forgejo",
  base_url: "https://git.acme.test",
  provider_type: "forgejo_self_hosted",
  auth_mode: "oauth2_and_token",
  status: "active",
};

const authorization: ProviderAuthorizationPayload = {
  id: "auth-1",
  provider: "github",
  auth_strategy: "oauth",
  account_name: "octocat",
  configured: true,
  masked_value: "gho_****",
  status: "active",
  auth_health: "healthy",
};

type ModalTestProps = {
  activeModal: "access" | "connect";
  providers: SourceProviderPayload[];
  providerAuthorizationsAll: ProviderAuthorizationPayload[];
  providerResourcesAll: Record<string, ProviderResourcePayload[]>;
  sourceProviderInstances: SourceProviderInstancePayload[];
  existingSourceUrls: Set<string>;
  organization: typeof organization | null;
  isSubmitting: boolean;
  user_oauth_accounts: UserOAuthAccountPayload[];
  initialProvider: string;
  initialProviderInstanceId: string;
  returnTo: string;
  connectResult?: {
    queued: string[];
    skipped: string[];
    failed: { repository: string; message: string }[];
  } | null;
  accessErrors?: Record<string, string[]>;
  accessSaved?: number;
  onClose: () => void;
  onConnectSubmit: (
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
    state: Record<string, unknown>,
  ) => void;
  onAccessSubmit: (
    event: SubmitEvent & { currentTarget: EventTarget & HTMLFormElement },
  ) => void;
  onDeleteAuthorization: (auth: ProviderAuthorizationPayload) => void;
  onAuthorizationCreated: (authorization: ProviderAuthorizationPayload) => void;
  withOrganization: (path: string) => string;
};

function renderModal(overrides: Partial<ModalTestProps> = {}) {
  const onClose = vi.fn();
  const onConnectSubmit = vi.fn();
  const onAccessSubmit = vi.fn();
  const onDeleteAuthorization = vi.fn();
  const onAuthorizationCreated = vi.fn();
  const props: ModalTestProps = {
    activeModal: "connect" as const,
    providers: [githubProvider, disabledProvider],
    providerAuthorizationsAll: [authorization],
    providerResourcesAll: {},
    sourceProviderInstances: [],
    existingSourceUrls: new Set<string>(),
    organization,
    isSubmitting: false,
    user_oauth_accounts: [],
    initialProvider: "",
    initialProviderInstanceId: "",
    returnTo: "",
    onClose,
    onConnectSubmit,
    onAccessSubmit,
    onDeleteAuthorization,
    onAuthorizationCreated,
    withOrganization: (path: string) => `/organization/acme${path}`,
    ...overrides,
  };

  const { rerender } = render(SourceConnectionModal, { props });

  return {
    rerender: (next: Partial<ModalTestProps>) => rerender(next),
    props,
    onClose,
    onConnectSubmit,
    onAccessSubmit,
    onDeleteAuthorization,
    onAuthorizationCreated,
  };
}

describe("SourceConnectionModal", () => {
  beforeEach(() => {
    vi.spyOn(apiClient, "get").mockReset();
    vi.spyOn(apiClient, "post").mockReset();
  });

  it("keeps Tab focus inside the dialog", async () => {
    renderModal();
    const dialog = screen.getByRole("dialog");
    const focusable = [
      ...dialog.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ),
    ];
    const first = focusable[0];
    const last = focusable.at(-1)!;

    last.focus();
    await fireEvent.keyDown(dialog, { key: "Tab" });
    expect(document.activeElement).toBe(first);

    await fireEvent.keyDown(dialog, { key: "Tab", shiftKey: true });
    expect(document.activeElement).toBe(last);
  });

  it("handles Escape and Tab after the focused control unmounts", async () => {
    const view = renderModal({ providerAuthorizationsAll: [] });
    const configure = screen.getByRole("button", {
      name: "Configure provider access",
    });
    configure.focus();
    await fireEvent.click(configure);
    expect(document.activeElement).not.toBe(configure);
    await waitFor(() =>
      expect(document.activeElement).toBe(
        document.querySelector("#source-provider"),
      ),
    );

    (document.activeElement as HTMLElement).blur();
    expect(document.activeElement).toBe(document.body);
    await fireEvent.keyDown(document.body, { key: "Tab" });
    expect(screen.getByRole("dialog").contains(document.activeElement)).toBe(
      true,
    );

    (document.activeElement as HTMLElement).blur();
    await fireEvent.keyDown(document.body, { key: "Escape" });
    expect(view.onClose).toHaveBeenCalledTimes(1);
  });

  it("leaves Escape to a dialog stacked on top", async () => {
    const view = renderModal();
    const other = document.createElement("div");
    other.setAttribute("role", "dialog");
    document.body.append(other);
    await fireEvent.keyDown(other, { key: "Escape" });
    other.remove();
    expect(view.onClose).not.toHaveBeenCalled();
  });

  it("disables the footer Save while submitting", async () => {
    renderModal({ activeModal: "access", isSubmitting: true });
    await fireEvent.click(
      screen.getByRole("button", { name: "Add provider access" }),
    );
    expect(screen.getByRole("button", { name: "Saving…" })).toBeDisabled();
  });

  it("filters providers, loads resources, and submits selected modal state", async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        resources: [
          {
            id: "repo-1",
            name: "vendor/package",
            canonical_url: "https://github.com/vendor/package",
            default_ref: "main",
            private: true,
            provider: "github",
          },
        ],
      },
    });
    const modal = renderModal();

    expect(screen.getAllByText("GitHub").length).toBeGreaterThan(0);
    expect(screen.queryByText("Coming Soon")).not.toBeInTheDocument();
    expect(screen.getByText("0 = no limit")).toBeInTheDocument();
    expect(screen.getByRole("combobox", { name: "Provider" })).toHaveFocus();
    expect(screen.getByText("Saved access available")).toBeInTheDocument();

    await fireEvent.click(
      screen.getByRole("button", { name: "Load repositories" }),
    );

    await waitFor(() => {
      expect(apiClient.get).toHaveBeenCalledWith(
        "/organization/acme/sources/authorizations/auth-1/resources?provider=github",
      );
    });

    expect(await screen.findAllByText("vendor/package")).not.toHaveLength(0);

    await fireEvent.click(
      screen.getByRole("button", { name: "Connect source" }),
    );

    expect(modal.onConnectSubmit).toHaveBeenCalledOnce();
    expect(modal.onConnectSubmit.mock.calls[0][1]).toMatchObject({
      activeModal: "connect",
      selectedProvider: "github",
      selectedAuthorizationId: "auth-1",
      selectedResources: ["https://github.com/vendor/package"],
      connectVisibility: "private",
      keepLastReleases: "0",
    });
  });

  it("resets visibility to private when switching providers", async () => {
    renderModal({ providers: [githubProvider, gitlabProvider] });
    const visibility = screen.getByRole("combobox", { name: "Visibility" });

    await fireEvent.change(visibility, { target: { value: "public" } });
    await fireEvent.change(screen.getByRole("combobox", { name: "Provider" }), {
      target: { value: "gitlab_self_hosted" },
    });

    expect(screen.getByRole("combobox", { name: "Visibility" })).toHaveValue(
      "private",
    );
  });

  it("warns when a private repository is connected as public", async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        resources: [
          {
            id: "repo-1",
            name: "vendor/package",
            canonical_url: "https://github.com/vendor/package",
            default_ref: "main",
            private: true,
            provider: "github",
          },
        ],
      },
    });
    renderModal();
    const warning = /its packages will be publicly available/;

    await fireEvent.click(
      screen.getByRole("button", { name: "Load repositories" }),
    );
    expect(await screen.findAllByText("vendor/package")).not.toHaveLength(0);
    expect(screen.queryByText(warning)).not.toBeInTheDocument();

    const visibility = screen.getByRole("combobox", { name: "Visibility" });
    await fireEvent.change(visibility, { target: { value: "public" } });
    expect(screen.getByRole("alert")).toHaveTextContent(warning);
    expect(
      screen.getByRole("button", { name: "Connect source" }),
    ).toBeEnabled();

    await fireEvent.change(visibility, { target: { value: "private" } });
    expect(screen.queryByText(warning)).not.toBeInTheDocument();
  });

  it("blocks reconnect-required authorizations from loading resources", () => {
    renderModal({
      providerAuthorizationsAll: [
        {
          ...authorization,
          auth_health: "reauthorization_required",
          auth_failure_reason: "Token expired",
        },
      ],
    });

    expect(screen.getAllByText(/Token expired/).length).toBeGreaterThan(0);
    expect(
      screen.queryByRole("button", { name: "Load repositories" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Connect source" }),
    ).toBeDisabled();
  });

  it("manages inactive authorizations for the selected GitLab instance", async () => {
    const inactiveAuthorization: ProviderAuthorizationPayload = {
      ...authorization,
      id: "gitlab-auth-inactive",
      provider: "gitlab_self_hosted",
      provider_instance_id: gitlabInstance.id,
      account_name: "former-admin",
      configured: false,
      status: "inactive",
      auth_health: "reauthorization_required",
      auth_failure_reason: "Token revoked",
    };
    const modal = renderModal({
      activeModal: "access",
      providers: [gitlabProvider],
      providerAuthorizationsAll: [inactiveAuthorization],
      sourceProviderInstances: [gitlabInstance],
    });

    expect(screen.getByText("former-admin")).toBeInTheDocument();
    expect(screen.getAllByText("Reauthorization required")).not.toHaveLength(0);
    expect(screen.getByText("Token revoked")).toBeInTheDocument();

    await fireEvent.click(
      screen.getByRole("button", { name: "Delete authorization" }),
    );

    expect(modal.onDeleteAuthorization).toHaveBeenCalledWith(
      inactiveAuthorization,
    );
  });

  it("keeps disabled GitLab instances available for authorization management only", async () => {
    const disabledInstance: SourceProviderInstancePayload = {
      ...gitlabInstance,
      id: "gitlab-instance-disabled",
      label: "Retired GitLab",
      status: "inactive",
    };
    const inactiveAuthorization: ProviderAuthorizationPayload = {
      ...authorization,
      id: "retired-auth",
      provider: "gitlab_self_hosted",
      provider_instance_id: disabledInstance.id,
      account_name: "retired-admin",
      configured: false,
      status: "inactive",
      auth_health: "reauthorization_required",
    };
    const modal = renderModal({
      activeModal: "access",
      providers: [gitlabProvider],
      providerAuthorizationsAll: [inactiveAuthorization],
      sourceProviderInstances: [gitlabInstance, disabledInstance],
    });

    await fireEvent.change(screen.getByLabelText("GitLab instance"), {
      target: { value: disabledInstance.id },
    });
    expect(screen.getByText("retired-admin")).toBeInTheDocument();

    await fireEvent.click(
      screen.getByRole("button", { name: "Delete authorization" }),
    );
    expect(modal.onDeleteAuthorization).toHaveBeenCalledWith(
      inactiveAuthorization,
    );
  });

  it("does not offer disabled GitLab instances in the connect flow", () => {
    renderModal({
      providers: [gitlabProvider],
      sourceProviderInstances: [
        gitlabInstance,
        {
          ...gitlabInstance,
          id: "disabled-instance",
          label: "Disabled",
          status: "inactive",
        },
      ],
      providerAuthorizationsAll: [],
    });

    const select = screen.getByLabelText("GitLab instance");
    expect(select).toHaveTextContent("Acme GitLab");
    expect(select).not.toHaveTextContent("Disabled");
  });

  it("does not offer an active authorization scoped to an inactive-only GitLab instance", () => {
    const inactiveInstance: SourceProviderInstancePayload = {
      ...gitlabInstance,
      id: "inactive-instance",
      status: "inactive",
    };
    renderModal({
      providers: [gitlabProvider],
      sourceProviderInstances: [inactiveInstance],
      providerAuthorizationsAll: [
        {
          ...authorization,
          provider: "gitlab_self_hosted",
          provider_instance_id: inactiveInstance.id,
        },
      ],
    });

    expect(screen.getByText(/No saved GitLab access yet/)).toBeInTheDocument();
    expect(screen.queryByText("octocat")).not.toBeInTheDocument();
  });

  it("does not offer an unscoped legacy authorization when an active GitLab instance is selected", () => {
    renderModal({
      providers: [gitlabProvider],
      sourceProviderInstances: [gitlabInstance],
      providerAuthorizationsAll: [
        {
          ...authorization,
          provider: "gitlab_self_hosted",
          provider_instance_id: null,
        },
      ],
    });

    expect(screen.getByText(/No saved GitLab access yet/)).toBeInTheDocument();
    expect(screen.queryByText("octocat")).not.toBeInTheDocument();
  });

  it("offers an unscoped legacy authorization when no active GitLab instance exists", () => {
    renderModal({
      providers: [gitlabProvider],
      sourceProviderInstances: [
        { ...gitlabInstance, id: "inactive-instance", status: "inactive" },
      ],
      providerAuthorizationsAll: [
        {
          ...authorization,
          provider: "gitlab_self_hosted",
          provider_instance_id: null,
        },
      ],
    });

    expect(screen.getAllByText("octocat").length).toBeGreaterThan(0);
  });

  it("does not offer inactive authorizations for source connection", () => {
    renderModal({
      providers: [gitlabProvider],
      providerAuthorizationsAll: [
        {
          ...authorization,
          id: "gitlab-auth-inactive",
          provider: "gitlab_self_hosted",
          provider_instance_id: gitlabInstance.id,
          configured: false,
          status: "inactive",
          auth_health: "reauthorization_required",
        },
      ],
      sourceProviderInstances: [gitlabInstance],
    });

    expect(screen.getByText(/No saved GitLab access yet/)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Configure provider access" }),
    ).toBeInTheDocument();
    expect(
      screen.queryByText("Reauthorization required"),
    ).not.toBeInTheDocument();
  });

  it("handles source OAuth popup messages and creates authorization", async () => {
    const popup = {
      closed: false,
      close: vi.fn(),
    };
    vi.spyOn(window, "open").mockReturnValue(popup as unknown as Window);
    vi.mocked(apiClient.post).mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        authorization: { ...authorization, id: "auth-new" },
        toast: { type: "success", message: "Connected" },
      },
    });
    vi.mocked(apiClient.get).mockResolvedValue({
      ok: true,
      status: 200,
      data: { resources: [] },
    });
    const modal = renderModal({
      providerAuthorizationsAll: [],
    });
    await fireEvent.click(
      screen.getByRole("button", { name: "Configure provider access" }),
    );

    await fireEvent.click(
      screen.getByRole("button", { name: /Connect with GitHub/ }),
    );

    expect(window.open).toHaveBeenCalledWith(
      "/organization/acme/auth/oauth/github/source-redirect?popup=1",
      "packio-source-oauth",
      "popup=yes,width=720,height=760",
    );
    expect(sessionStorage.getItem("sources_modal_state")).toContain(
      '"activeModal":"access"',
    );

    window.dispatchEvent(
      new MessageEvent("message", {
        origin: window.location.origin,
        data: {
          type: "packio:source-oauth",
          success: true,
          provider: "github",
          sourceOAuthAccountId: "oauth-1",
        },
      }),
    );

    await waitFor(() => {
      expect(apiClient.post).toHaveBeenCalledWith(
        "/organization/acme/sources/authorizations/oauth",
        {
          provider: "github",
          sourceOauthAccountId: "oauth-1",
        },
      );
    });

    expect(popup.close).toHaveBeenCalledOnce();
    await waitFor(() => {
      expect(apiClient.get).toHaveBeenCalledWith(
        "/organization/acme/sources/authorizations/auth-new/resources?provider=github",
      );
    });
    await waitFor(() => {
      expect(modal.onAuthorizationCreated).toHaveBeenCalledWith({
        ...authorization,
        id: "auth-new",
      });
    });
  });

  it("selects only Forgejo instances and passes the instance to the OAuth popup", async () => {
    const popup = { closed: false, close: vi.fn() };
    vi.spyOn(window, "open").mockReturnValue(popup as unknown as Window);
    renderModal({
      activeModal: "access",
      providers: [gitlabProvider, forgejoProvider],
      sourceProviderInstances: [gitlabInstance, forgejoInstance],
      providerAuthorizationsAll: [],
    });

    await fireEvent.change(screen.getByRole("combobox", { name: "Provider" }), {
      target: { value: "forgejo_self_hosted" },
    });

    const select = screen.getByLabelText("Forgejo instance");
    expect(select).toHaveValue(forgejoInstance.id);
    expect(screen.queryByText("Acme GitLab")).not.toBeInTheDocument();

    await fireEvent.click(
      screen.getByRole("button", { name: /Connect with Forgejo/ }),
    );

    expect(window.open).toHaveBeenCalledWith(
      "/organization/acme/auth/oauth/forgejo_self_hosted/source-redirect?popup=1&provider_instance_id=forgejo-instance-1",
      "packio-source-oauth",
      "popup=yes,width=720,height=760",
    );
  });

  it("offers only authorizations scoped to the selected Forgejo instance when connecting", () => {
    renderModal({
      providers: [forgejoProvider],
      sourceProviderInstances: [forgejoInstance],
      providerAuthorizationsAll: [
        {
          ...authorization,
          id: "forgejo-auth",
          provider: "forgejo_self_hosted",
          provider_instance_id: forgejoInstance.id,
          account_name: "forgejo-user",
        },
        {
          ...authorization,
          id: "legacy-auth",
          provider: "forgejo_self_hosted",
          provider_instance_id: null,
          account_name: "legacy-user",
        },
      ],
    });

    expect(screen.getAllByText("forgejo-user").length).toBeGreaterThan(0);
    expect(screen.queryByText("legacy-user")).not.toBeInTheDocument();
  });

  it("keeps the GitLab instance wording for the real catalog name", () => {
    renderModal({
      activeModal: "access",
      providers: [{ ...gitlabProvider, name: "GitLab Self-Hosted" }],
      sourceProviderInstances: [gitlabInstance],
      providerAuthorizationsAll: [],
    });

    expect(screen.getByLabelText("GitLab instance")).toBeInTheDocument();
  });

  describe("opened from a source page", () => {
    const returnTo = "/organization/acme/sources/source-1?tab=activity";

    it("preselects the initial provider and instance", () => {
      renderModal({
        activeModal: "access",
        providers: [githubProvider, gitlabProvider],
        sourceProviderInstances: [gitlabInstance],
        initialProvider: "gitlab_self_hosted",
        initialProviderInstanceId: gitlabInstance.id,
      });

      expect(screen.getByLabelText("GitLab instance")).toHaveValue(
        gitlabInstance.id,
      );
    });

    it("ignores an initial provider that does not exist", () => {
      renderModal({
        activeModal: "access",
        initialProvider: "missing",
      });

      expect(screen.getByRole("combobox", { name: "Provider" })).toHaveValue(
        "github",
      );
    });

    it("shows Done without Continue to add source, and Done closes", async () => {
      const modal = renderModal({ activeModal: "access", returnTo });
      await fireEvent.click(screen.getByRole("button", { name: "Done" }));
      expect(modal.onClose).toHaveBeenCalledOnce();
      expect(
        screen.queryByRole("button", { name: "Continue to add source" }),
      ).toBeNull();
    });

    it("sends return_to with the token form", () => {
      renderModal({
        activeModal: "access",
        providers: [{ ...forgejoProvider, name: "Forgejo Self-Hosted" }],
        sourceProviderInstances: [{ ...forgejoInstance, auth_mode: "token" }],
        providerAuthorizationsAll: [],
        returnTo,
      });

      const form = document
        .querySelector<HTMLInputElement>('input[name="authorization_token"]')!
        .closest("form")!;
      expect(new FormData(form).get("return_to")).toBe(returnTo);
    });

    it("visits returnTo after OAuth success instead of opening the connect wizard", async () => {
      vi.mocked(router.visit).mockClear();
      vi.mocked(apiClient.post).mockResolvedValue({
        ok: true,
        status: 200,
        data: { authorization: { ...authorization, id: "auth-new" } },
      });
      const modal = renderModal({ activeModal: "access", returnTo });

      window.dispatchEvent(
        new MessageEvent("message", {
          origin: window.location.origin,
          data: {
            type: "packio:source-oauth",
            success: true,
            provider: "github",
            sourceOAuthAccountId: "oauth-1",
          },
        }),
      );

      await waitFor(() => expect(router.visit).toHaveBeenCalledWith(returnTo));
      expect(apiClient.get).not.toHaveBeenCalled();
      expect(modal.onAuthorizationCreated).toHaveBeenCalled();
      expect(
        screen.getByRole("dialog", { name: "Manage provider access" }),
      ).toBeInTheDocument();
      expect(sessionStorage.getItem("sources_modal_state")).toBeNull();
    });
  });

  it("submits the Forgejo token form with the selected instance", async () => {
    const modal = renderModal({
      activeModal: "access",
      providers: [{ ...forgejoProvider, name: "Forgejo Self-Hosted" }],
      sourceProviderInstances: [{ ...forgejoInstance, auth_mode: "token" }],
      providerAuthorizationsAll: [],
    });

    expect(screen.getByLabelText("Forgejo instance")).toHaveValue(
      forgejoInstance.id,
    );
    const token = document.querySelector<HTMLInputElement>(
      'input[name="authorization_token"]',
    );
    expect(token).not.toBeNull();
    await fireEvent.input(token!, { target: { value: "forgejo-token" } });
    const form = token!.closest("form")!;
    await fireEvent.submit(form);

    expect(modal.onAccessSubmit).toHaveBeenCalledOnce();
    const data = new FormData(form);
    expect(data.get("provider")).toBe("forgejo_self_hosted");
    expect(data.get("provider_instance_id")).toBe(forgejoInstance.id);
  });

  it("asks for the Forgejo instance URL when no instance is configured", () => {
    renderModal({
      activeModal: "access",
      providers: [{ ...forgejoProvider, name: "Forgejo Self-Hosted" }],
      sourceProviderInstances: [],
      providerAuthorizationsAll: [],
    });

    expect(screen.getByText("Forgejo instance URL")).toBeInTheDocument();
  });

  describe("manage provider access", () => {
    it("lists saved access with the form collapsed, and opens the form on demand", async () => {
      renderModal({ activeModal: "access" });

      expect(screen.getByText("octocat")).toBeInTheDocument();
      expect(screen.getByText("gho_****")).toBeInTheDocument();
      expect(
        document.querySelector('input[name="authorization_token"]'),
      ).toBeNull();

      await fireEvent.click(
        screen.getByRole("button", { name: "Add provider access" }),
      );
      expect(
        document.querySelector('input[name="authorization_token"]'),
      ).not.toBeNull();
    });

    it("shows the footer Save button only while the token form is open", async () => {
      renderModal({ activeModal: "access" });
      const addButton = screen.getByRole("button", {
        name: "Add provider access",
      });

      expect(
        screen.queryByRole("button", { name: "Save provider access" }),
      ).toBeNull();
      await fireEvent.click(addButton);
      const save = screen.getByRole("button", { name: "Save provider access" });
      expect(save).toHaveAttribute("type", "submit");
      expect(save).toHaveAttribute("form", "provider-access-form");
      expect(document.getElementById("provider-access-form")).not.toBeNull();
      await fireEvent.click(addButton);
      expect(
        screen.queryByRole("button", { name: "Save provider access" }),
      ).toBeNull();
    });

    it("shows a single empty line and an open form without saved access", () => {
      renderModal({ activeModal: "access", providerAuthorizationsAll: [] });

      expect(screen.getAllByText(/No saved/)).toHaveLength(1);
      expect(screen.getByText("No saved access")).toBeVisible();
      expect(
        document.querySelector('input[name="authorization_token"]'),
      ).toHaveAttribute("type", "password");
    });

    it("keeps the instance URL field and the Generic Git auth scheme", () => {
      renderModal({
        activeModal: "access",
        providers: [{ ...forgejoProvider, name: "Forgejo Self-Hosted" }],
        providerAuthorizationsAll: [],
      });
      expect(screen.getByText("Forgejo instance URL")).toBeInTheDocument();
      cleanup();

      renderModal({
        activeModal: "access",
        providers: [genericGitProvider],
        providerAuthorizationsAll: [],
      });
      expect(screen.getByLabelText("Auth scheme")).toBeInTheDocument();
    });

    it("shows the token error in the form and keeps the value", async () => {
      const view = renderModal({
        activeModal: "access",
        providerAuthorizationsAll: [],
        accessErrors: {},
      });
      const token = document.querySelector<HTMLInputElement>(
        'input[name="authorization_token"]',
      )!;
      await fireEvent.input(token, { target: { value: "bad-token" } });
      await view.rerender({
        accessErrors: { authorization_token: ["The token was rejected."] },
      });

      expect(screen.getByText("The token was rejected.")).toBeInTheDocument();
      expect(token).toHaveValue("bad-token");
      expect(screen.getByRole("status")).toBeEmptyDOMElement();
    });

    it("keeps the form and error with returnTo", async () => {
      const view = renderModal({
        activeModal: "access",
        providerAuthorizationsAll: [],
        returnTo: "/organization/acme/sources/source-1",
      });
      await view.rerender({
        accessErrors: { authorization_token: ["Token rejected."] },
      });
      expect(screen.getByText("Token rejected.")).toBeInTheDocument();
      expect(
        document.querySelector('input[name="authorization_token"]'),
      ).not.toBeNull();
    });

    it("clears and closes the form and confirms after a save", async () => {
      const view = renderModal({
        activeModal: "access",
        providerAuthorizationsAll: [],
      });
      const token = document.querySelector<HTMLInputElement>(
        'input[name="authorization_token"]',
      )!;
      await fireEvent.input(token, { target: { value: "good-token" } });
      await view.rerender({
        providerAuthorizationsAll: [authorization],
        accessSaved: 1,
      });

      await waitFor(() =>
        expect(screen.getByRole("status")).toHaveTextContent(
          "Access saved for this organization",
        ),
      );
      expect(
        document.querySelector('input[name="authorization_token"]'),
      ).toBeNull();
      expect(screen.getByText("octocat")).toBeInTheDocument();
    });

    it("hides a stale confirmation when the provider changes", async () => {
      const view = renderModal({
        activeModal: "access",
        providers: [githubProvider, gitlabProvider],
        providerAuthorizationsAll: [],
      });
      await view.rerender({ accessSaved: 1 });
      await screen.findByText("Access saved for this organization");
      await fireEvent.change(
        screen.getByRole("combobox", { name: "Provider" }),
        { target: { value: "gitlab_self_hosted" } },
      );
      expect(
        screen.queryByText("Access saved for this organization"),
      ).toBeNull();
    });

    it("returns to the wizard after saving when it came from there", async () => {
      const view = renderModal({ providerAuthorizationsAll: [] });
      await fireEvent.click(
        screen.getByRole("button", { name: "Configure provider access" }),
      );
      expect(
        screen.getByRole("button", { name: "Continue to add source" }),
      ).toBeInTheDocument();

      await view.rerender({
        providerAuthorizationsAll: [authorization],
        accessSaved: 1,
      });

      expect(
        await screen.findByRole("dialog", { name: "Add source" }),
      ).toBeInTheDocument();
      expect(screen.getByRole("combobox", { name: "Provider" })).toHaveValue(
        "github",
      );
    });

    it("stays in access mode after OAuth success from the list", async () => {
      vi.mocked(apiClient.post).mockResolvedValue({
        ok: true,
        status: 200,
        data: { authorization: { ...authorization, id: "auth-new" } },
      });
      const modal = renderModal({ activeModal: "access" });

      window.dispatchEvent(
        new MessageEvent("message", {
          origin: window.location.origin,
          data: {
            type: "packio:source-oauth",
            success: true,
            provider: "github",
            sourceOAuthAccountId: "oauth-1",
          },
        }),
      );

      await waitFor(() =>
        expect(modal.onAuthorizationCreated).toHaveBeenCalled(),
      );
      expect(
        screen.getByRole("dialog", { name: "Manage provider access" }),
      ).toBeInTheDocument();
      expect(apiClient.get).not.toHaveBeenCalled();
    });

    it("stays in access mode after saving when opened from the list", async () => {
      const view = renderModal({ activeModal: "access" });
      await view.rerender({ accessSaved: 1 });

      expect(
        screen.getByRole("dialog", { name: "Manage provider access" }),
      ).toBeInTheDocument();
      expect(
        screen.queryByRole("button", { name: "Continue to add source" }),
      ).toBeNull();
    });
  });

  describe("repository loading", () => {
    const repo = (name: string): ProviderResourcePayload => ({
      id: name,
      name,
      canonical_url: `https://github.com/${name}`,
      default_ref: "main",
      private: false,
      provider: "github",
    });
    const load = () =>
      fireEvent.click(
        screen.getByRole("button", { name: "Load repositories" }),
      );

    it("clears the selection when the provider changes", async () => {
      vi.mocked(apiClient.get).mockResolvedValue({
        ok: true,
        status: 200,
        data: { resources: [repo("vendor/a"), repo("vendor/b")] },
      });
      renderModal({ providers: [githubProvider, gitlabProvider] });
      await load();
      await fireEvent.click(await screen.findByLabelText("vendor/a"));
      expect(screen.getByText("1 selected · Private")).toBeInTheDocument();

      const provider = screen.getByRole("combobox", { name: "Provider" });
      await fireEvent.change(provider, {
        target: { value: "gitlab_self_hosted" },
      });
      await fireEvent.change(provider, { target: { value: "github" } });

      expect(screen.queryByText(/\d+ selected ·/)).not.toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: "Load repositories" }),
      ).toBeInTheDocument();
    });

    it("ignores a response that arrives after the provider changed", async () => {
      const errorToast = vi.spyOn(toast, "error").mockImplementation(() => "");
      let resolve!: (value: never) => void;
      vi.mocked(apiClient.get).mockReturnValue(
        new Promise((r) => (resolve = r)),
      );
      renderModal({ providers: [githubProvider, gitlabProvider] });
      await load();
      await fireEvent.change(
        screen.getByRole("combobox", { name: "Provider" }),
        { target: { value: "gitlab_self_hosted" } },
      );

      resolve({ ok: false, status: 500, error: "Late failure" } as never);
      await new Promise((r) => setTimeout(r, 0));

      expect(errorToast).not.toHaveBeenCalled();
      expect(screen.queryByText("Late failure")).not.toBeInTheDocument();
    });

    it("shows the load error with Retry", async () => {
      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({
          ok: false,
          status: 500,
          error: "Boom",
        } as never)
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          data: { resources: [repo("vendor/a"), repo("vendor/b")] },
        });
      renderModal();
      await load();

      expect(await screen.findByRole("alert")).toHaveTextContent("Boom");
      await fireEvent.click(screen.getByRole("button", { name: "Retry" }));

      expect(await screen.findByLabelText("vendor/a")).toBeInTheDocument();
      expect(screen.queryByText("Boom")).not.toBeInTheDocument();
    });

    it("explains a rejected access (401) and offers to configure access again", async () => {
      const errorToast = vi.spyOn(toast, "error").mockImplementation(() => "");
      vi.mocked(apiClient.get).mockResolvedValue({
        ok: false,
        status: 401,
        error: "Unauthorized",
      } as never);
      renderModal();
      await load();

      expect(
        await screen.findByRole("button", {
          name: "Configure provider access",
        }),
      ).toBeInTheDocument();
      expect(errorToast).toHaveBeenCalledWith(
        expect.stringContaining("rejected this access"),
      );
    });

    it("keeps only failed repositories selected after a partial result", () => {
      renderModal({
        connectResult: {
          queued: ["https://github.com/vendor/a"],
          skipped: [],
          failed: [
            { repository: "https://github.com/vendor/b", message: "No access" },
          ],
        },
      });

      expect(screen.getByRole("alert")).toHaveTextContent(
        "Connected: https://github.com/vendor/a",
      );
      expect(screen.getByRole("alert")).toHaveTextContent(
        "Needs retry: https://github.com/vendor/b (No access)",
      );
      expect(screen.getByText("1 selected · Private")).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: "Connect source" }),
      ).toBeEnabled();
    });

    it("drops the previous access's load error when another access is chosen", async () => {
      vi.mocked(apiClient.get).mockResolvedValue({
        ok: false,
        status: 500,
        error: "Boom",
      } as never);
      renderModal({
        providerAuthorizationsAll: [
          authorization,
          { ...authorization, id: "auth-2", account_name: "hubot" },
        ],
      });
      await fireEvent.change(screen.getByLabelText("Saved access"), {
        target: { value: "auth-1" },
      });
      await load();
      expect(await screen.findByRole("alert")).toHaveTextContent("Boom");

      await fireEvent.change(screen.getByLabelText("Saved access"), {
        target: { value: "auth-2" },
      });

      expect(screen.queryByText("Boom")).not.toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: "Load repositories" }),
      ).toBeInTheDocument();
    });

    it("connects a manually entered repository URL without resource_urls", async () => {
      const modal = renderModal();
      await fireEvent.click(
        screen.getByRole("button", { name: "Enter repository URL" }),
      );
      expect(
        screen.getByRole("button", { name: "Connect source" }),
      ).toBeDisabled();
      await fireEvent.input(screen.getByLabelText("Repository URL"), {
        target: { value: "https://github.com/vendor/private" },
      });
      const submit = screen.getByRole("button", { name: "Connect source" });
      expect(submit).toBeEnabled();
      await fireEvent.click(submit);

      expect(modal.onConnectSubmit).toHaveBeenCalledOnce();
      const form = document.getElementById(
        "add-source-form",
      ) as HTMLFormElement;
      const data = new FormData(form);
      expect(data.get("repository_url")).toBe(
        "https://github.com/vendor/private",
      );
      expect(data.get("authorization_id")).toBe("auth-1");
      expect(data.getAll("resource_urls[]")).toEqual([]);
    });
  });
});
