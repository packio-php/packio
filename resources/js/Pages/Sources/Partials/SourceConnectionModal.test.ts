import { fireEvent, render, screen, waitFor } from "@testing-library/svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { apiClient } from "@/lib/api";
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
  capabilities: { supports_synchronization: true },
};

const disabledProvider: SourceProviderPayload = {
  key: "coming_soon",
  name: "Coming Soon",
  description: "Unavailable",
  oauth_available: false,
  oauth_enabled: false,
  oauth_provider: null,
  capabilities: { supports_synchronization: false },
};

const gitlabProvider: SourceProviderPayload = {
  key: "gitlab_self_hosted",
  name: "GitLab",
  description: "Self-hosted GitLab repositories",
  oauth_available: true,
  oauth_enabled: true,
  oauth_provider: "gitlab",
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
  resolvedBranding: { name: string };
  isSubmitting: boolean;
  user_oauth_accounts: UserOAuthAccountPayload[];
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
    resolvedBranding: { name: "Packio" },
    isSubmitting: false,
    user_oauth_accounts: [],
    onClose,
    onConnectSubmit,
    onAccessSubmit,
    onDeleteAuthorization,
    onAuthorizationCreated,
    withOrganization: (path: string) => `/organization/acme${path}`,
    ...overrides,
  };

  render(SourceConnectionModal, { props });

  return {
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
    expect(
      screen.getByText(/Older versions are hidden from the registry/),
    ).toHaveTextContent(
      "Dev versions do not count toward the limit. 0 disables the limit.",
    );

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
      screen.getByRole("button", { name: "Connect 1 source" }),
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
      screen.getByRole("button", { name: /Connect\s+sources/ }),
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

    expect(
      screen.getByText(/No saved GitLab authorization is available/),
    ).toBeInTheDocument();
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

    expect(
      screen.getByText(/No saved GitLab authorization is available/),
    ).toBeInTheDocument();
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

    expect(
      screen.getByText(
        "No saved GitLab authorization is available for this organization yet. Save provider access first, then continue with repository selection.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Authorize GitLab" }),
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
    const modal = renderModal({ activeModal: "access" });

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
});
