import { describe, expect, it } from "vitest";
import { Endpoints } from "./endpoints";

describe("Endpoints", () => {
  it("keeps auth route contracts stable", () => {
    expect(Endpoints.auth.login()).toBe("/auth/login");
    expect(Endpoints.auth.register()).toBe("/auth/register");
    expect(Endpoints.auth.logout()).toBe("/auth/logout");
  });

  it("builds organization-scoped source routes", () => {
    expect(Endpoints.sources.providerInstanceEnable("acme", "instance-1")).toBe(
      "/organization/acme/source-provider-instances/instance-1/enable",
    );
  });

  it("builds profile and admin routes", () => {
    expect(Endpoints.profile.disconnectOAuth("account-1")).toBe(
      "/profile/oauth/account-1/disconnect",
    );
    expect(Endpoints.profile.issueApiToken()).toBe("/profile/api-tokens");
    expect(Endpoints.profile.deleteApiToken("token-1")).toBe(
      "/profile/api-tokens/token-1",
    );
    expect(Endpoints.profile.revokeApiToken("token-1")).toBe(
      "/profile/api-tokens/token-1/revoke",
    );
    expect(Endpoints.admin.requeueSourceDelete("source-1")).toBe(
      "/admin/sources/source-1/requeue-delete",
    );
    expect(Endpoints.admin.forceRequeueSourceDelete("source-1")).toBe(
      "/admin/sources/source-1/force-requeue-delete",
    );
    expect(Endpoints.admin.cancelSourceDelete("source-1")).toBe(
      "/admin/sources/source-1/cancel-delete",
    );
    expect(Endpoints.admin.forceFinalizeSourceDelete("source-1")).toBe(
      "/admin/sources/source-1/force-finalize-delete",
    );
  });
});
