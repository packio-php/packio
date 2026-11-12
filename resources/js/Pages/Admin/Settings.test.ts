import { fireEvent, render, screen, within } from "@testing-library/svelte";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { resetInertiaMocks, router } from "@/test/inertia";
import AdminSettings from "./Settings.svelte";

vi.mock("@/Layouts/AdminShell.svelte", async () => ({
  default: (await import("@/test/TestShell.svelte")).default,
}));

describe("Admin settings page", () => {
  beforeEach(() => {
    resetInertiaMocks();
  });

  it("renders and submits API documentation public override", async () => {
    render(AdminSettings, {
      props: {
        settings: {
          definitions: [
            {
              allowed_values: ["inherit", "on", "off"],
              config_path: "api.docs.public",
              group: "api",
              help_text:
                "Controls whether API documentation is visible without signing in.",
              key: "api.docs.public",
              label: "Public API documentation",
              provider_key: null,
            },
          ],
          values: {
            "api.docs.public": {
              effective: true,
              fallback: true,
              fallback_source: "api.docs.public",
              override: "inherit",
            },
          },
        },
      },
    });

    expect(screen.getByRole("heading", { name: "API" })).toBeInTheDocument();
    expect(screen.getByText("Public API documentation")).toBeInTheDocument();

    const overrideGroup = screen.getByLabelText(
      "Public API documentation override",
    );
    await fireEvent.click(
      within(overrideGroup).getByRole("button", {
        name: "Off",
      }),
    );
    await fireEvent.submit(
      screen.getByRole("button", { name: "Save settings" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      "/admin/settings",
      {
        settings: {
          "api.docs.public": "off",
        },
      },
      {
        preserveScroll: true,
      },
    );
  });

  it("renders and submits HTTP CORS override", async () => {
    render(AdminSettings, {
      props: {
        settings: {
          definitions: [
            {
              allowed_values: ["inherit", "on", "off"],
              config_path: "packio.cors.enabled",
              group: "http",
              help_text:
                "Controls whether Packio sends CORS responses for configured origins.",
              key: "http.cors.enabled",
              label: "CORS",
              provider_key: null,
            },
          ],
          values: {
            "http.cors.enabled": {
              effective: true,
              fallback: true,
              fallback_source: "packio.cors.enabled",
              override: "inherit",
            },
          },
        },
      },
    });

    expect(screen.getByRole("heading", { name: "HTTP" })).toBeInTheDocument();
    expect(screen.getByText("CORS")).toBeInTheDocument();

    const overrideGroup = screen.getByLabelText("CORS override");
    await fireEvent.click(
      within(overrideGroup).getByRole("button", {
        name: "Off",
      }),
    );
    await fireEvent.submit(
      screen.getByRole("button", { name: "Save settings" }),
    );

    expect(router.post).toHaveBeenCalledWith(
      "/admin/settings",
      {
        settings: {
          "http.cors.enabled": "off",
        },
      },
      {
        preserveScroll: true,
      },
    );
  });
});
