import { fireEvent, render, screen, waitFor } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import { apiClient } from "@/lib/api";
import { router } from "@/test/inertia";
import Settings from "./Settings.svelte";
import type {
  SourceProviderInstanceDependenciesPayload,
  SourceProviderInstancePayload,
} from "@/types/pages";

vi.mock("@/Layouts/AppShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

const organization = {
  id: "org-1",
  name: "Acme",
  slug: "acme",
  anonymous_access: false,
};

const settings = {
  name: "Acme",
  route_key: "acme",
  anonymous_access: false,
  created_at: "2024-01-01T00:00:00",
  updated_at: "2024-01-02T00:00:00",
};

const gitlabInstance: SourceProviderInstancePayload = {
  id: "instance-1",
  provider_type: "gitlab_self_hosted",
  label: "Company GitLab",
  base_url: "https://gitlab.example.com",
  client_id: "client-id",
  has_client_secret: true,
  auth_mode: "oauth2_and_token",
  status: "active",
};

const noDependencies: SourceProviderInstanceDependenciesPayload = {
  instance: {
    id: gitlabInstance.id,
    label: gitlabInstance.label,
    base_url: gitlabInstance.base_url,
    provider_type: gitlabInstance.provider_type,
  },
  source_count: 0,
  authorization_count: 0,
  active_authorization_count: 0,
  inactive_authorization_count: 0,
  sources: [],
  authorizations: [],
};

describe("Organization settings page", () => {
  it("posts organization settings form", async () => {
    render(Settings, {
      props: {
        organization,
        settings,
      },
    });

    await fireEvent.input(screen.getByLabelText("Organization name"), {
      target: { value: "Acme Registry" },
    });
    await fireEvent.click(screen.getByLabelText("Anonymous access"));
    await fireEvent.submit(
      screen.getByRole("button", { name: "Save settings" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      expect.stringContaining("/organization/acme/settings"),
      {
        name: "Acme Registry",
        anonymous_access: "1",
      },
      {
        preserveScroll: true,
      },
    );
  });

  it("loads, saves, toggles, and deletes provider instances", async () => {
    vi.spyOn(apiClient, "get")
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { instances: [gitlabInstance] },
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: noDependencies,
      });
    vi.spyOn(apiClient, "post")
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: {
          instance: {
            ...gitlabInstance,
            id: "instance-2",
            label: "Second GitLab",
            base_url: "https://second.example.com",
            status: "active",
          },
        },
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: {
          instance: {
            ...gitlabInstance,
            status: "inactive",
          },
        },
      });
    vi.spyOn(apiClient, "delete").mockResolvedValue({
      ok: true,
      status: 200,
      data: { deleted: true },
    });

    render(Settings, {
      props: {
        organization,
        settings,
        branding: { appBaseUrl: "https://registry.example.com" },
        source_provider_instances_enabled: true,
      },
    });

    expect(apiClient.get).toHaveBeenCalledWith(
      "/organization/acme/source-provider-instances?provider_type=gitlab_self_hosted",
    );
    expect(await screen.findByText("Company GitLab")).toBeInTheDocument();

    await fireEvent.input(screen.getByLabelText("Label"), {
      target: { value: "Second GitLab" },
    });
    await fireEvent.input(screen.getByLabelText("Base URL"), {
      target: { value: "https://second.example.com" },
    });
    await fireEvent.input(screen.getByLabelText("OAuth client ID"), {
      target: { value: "second-client" },
    });
    await fireEvent.input(screen.getByLabelText("OAuth client secret"), {
      target: { value: "secret" },
    });
    await fireEvent.click(screen.getByRole("button", { name: "Add instance" }));

    await waitFor(() => {
      expect(apiClient.post).toHaveBeenCalledWith(
        "/organization/acme/source-provider-instances",
        {
          providerType: "gitlab_self_hosted",
          label: "Second GitLab",
          baseUrl: "https://second.example.com",
          clientId: "second-client",
          clientSecret: "secret",
          authMode: "oauth2_and_token",
        },
      );
    });
    expect(await screen.findByText("Second GitLab")).toBeInTheDocument();

    await fireEvent.click(
      screen.getAllByRole("button", { name: "Disable" })[0],
    );

    await waitFor(() => {
      expect(apiClient.post).toHaveBeenCalledWith(
        "/organization/acme/source-provider-instances/instance-1/disable",
      );
    });
    expect(await screen.findByText("inactive")).toBeInTheDocument();

    await fireEvent.click(screen.getAllByRole("button", { name: "Delete" })[0]);
    expect(
      await screen.findByText("Delete GitLab instance?"),
    ).toBeInTheDocument();
    await fireEvent.click(
      screen.getByRole("button", { name: "Delete instance" }),
    );

    await waitFor(() => {
      expect(apiClient.delete).toHaveBeenCalledWith(
        "/organization/acme/source-provider-instances/instance-1",
      );
    });
  });

  it("deletes inactive authorizations and the instance when they are the only dependencies", async () => {
    const inactiveDependencies: SourceProviderInstanceDependenciesPayload = {
      ...noDependencies,
      authorization_count: 2,
      inactive_authorization_count: 2,
      authorizations: [
        {
          id: "authorization-1",
          account_name: "Former admin",
          provider: "gitlab_self_hosted",
          auth_strategy: "oauth",
          status: "inactive",
        },
        {
          id: "authorization-2",
          account_name: "Former automation",
          provider: "gitlab_self_hosted",
          auth_strategy: "token",
          status: "inactive",
        },
      ],
    };
    vi.spyOn(apiClient, "get")
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { instances: [gitlabInstance] },
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: inactiveDependencies,
      });
    vi.spyOn(apiClient, "post").mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        deleted: true,
        deleted_inactive_authorizations_count: 2,
        deleted_source_oauth_accounts_count: 1,
      },
    });

    render(Settings, {
      props: {
        organization,
        settings,
        source_provider_instances_enabled: true,
      },
    });

    await screen.findByText("Company GitLab");
    await fireEvent.click(screen.getByRole("button", { name: "Delete" }));

    expect(
      await screen.findByText(
        "Delete inactive authorizations and GitLab instance?",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText("Former admin")).toBeInTheDocument();
    expect(screen.getByText("Former automation")).toBeInTheDocument();

    await fireEvent.click(
      screen.getByRole("button", {
        name: "Delete 2 inactive authorization(s) and instance",
      }),
    );

    expect(apiClient.post).toHaveBeenCalledWith(
      "/organization/acme/source-provider-instances/instance-1/delete-inactive-authorizations",
    );
  });

  it("shows source dependencies and disables instance deletion", async () => {
    vi.spyOn(apiClient, "get")
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { instances: [gitlabInstance] },
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: {
          ...noDependencies,
          source_count: 1,
          sources: [
            {
              id: "source-1",
              name: "Registry",
              url: "https://gitlab.example.com/team/registry",
              provider: "gitlab_self_hosted",
              status: "active",
            },
          ],
        },
      });

    render(Settings, {
      props: {
        organization,
        settings,
        source_provider_instances_enabled: true,
      },
    });

    await screen.findByText("Company GitLab");
    await fireEvent.click(screen.getByRole("button", { name: "Delete" }));

    expect(await screen.findByText("Registry")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Delete instance" }),
    ).toBeDisabled();
  });

  it("shows active authorizations and disables instance deletion", async () => {
    vi.spyOn(apiClient, "get")
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { instances: [gitlabInstance] },
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: {
          ...noDependencies,
          authorization_count: 1,
          active_authorization_count: 1,
          authorizations: [
            {
              id: "authorization-1",
              account_name: "Current admin",
              provider: "gitlab_self_hosted",
              auth_strategy: "oauth",
              status: "active",
            },
          ],
        },
      });

    render(Settings, {
      props: {
        organization,
        settings,
        source_provider_instances_enabled: true,
      },
    });

    await screen.findByText("Company GitLab");
    await fireEvent.click(screen.getByRole("button", { name: "Delete" }));

    expect(await screen.findByText("Current admin")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Delete instance" }),
    ).toBeDisabled();
  });

  it("replaces stale cleanup dependencies after a conflict response", async () => {
    const inactiveDependencies: SourceProviderInstanceDependenciesPayload = {
      ...noDependencies,
      authorization_count: 1,
      inactive_authorization_count: 1,
      authorizations: [
        {
          id: "authorization-1",
          account_name: "Former admin",
          provider: "gitlab_self_hosted",
          auth_strategy: "oauth",
          status: "inactive",
        },
      ],
    };
    const conflictDependencies: SourceProviderInstanceDependenciesPayload = {
      ...inactiveDependencies,
      active_authorization_count: 1,
      inactive_authorization_count: 0,
      authorizations: [
        {
          ...inactiveDependencies.authorizations[0],
          account_name: "Current admin",
          status: "active",
        },
      ],
    };
    vi.spyOn(apiClient, "get")
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { instances: [gitlabInstance] },
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: inactiveDependencies,
      });
    vi.spyOn(apiClient, "post").mockResolvedValue({
      ok: false,
      status: 409,
      error:
        "Source provider instance cannot be deleted while sources or authorizations depend on it.",
      data: { dependencies: conflictDependencies },
    });

    render(Settings, {
      props: {
        organization,
        settings,
        source_provider_instances_enabled: true,
      },
    });

    await screen.findByText("Company GitLab");
    await fireEvent.click(screen.getByRole("button", { name: "Delete" }));
    await fireEvent.click(
      screen.getByRole("button", {
        name: "Delete 1 inactive authorization(s) and instance",
      }),
    );

    expect(await screen.findByText("Current admin")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Delete instance" }),
    ).toBeDisabled();
  });
});
