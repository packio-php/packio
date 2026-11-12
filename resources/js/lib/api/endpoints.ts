/**
 * Typed frontend endpoints that map to backend handlers.
 */
export const Endpoints = {
  auth: {
    login: (): string => "/auth/login",
    register: (): string => "/auth/register",
    logout: (): string => "/auth/logout",
    oauthEmail: (): string => "/auth/oauth/email",
  },

  admin: {
    user: (userUuid: string): string => `/admin/users/${userUuid}`,
    banUser: (userUuid: string): string => `/admin/users/${userUuid}/ban`,
    unbanUser: (userUuid: string): string => `/admin/users/${userUuid}/unban`,
    verifyUser: (userUuid: string): string => `/admin/users/${userUuid}/verify`,
    packages: (): string => "/admin/packages",
    sources: (): string => "/admin/sources",
    requeueSourceDelete: (sourceId: string): string =>
      `/admin/sources/${sourceId}/requeue-delete`,
    forceRequeueSourceDelete: (sourceId: string): string =>
      `/admin/sources/${sourceId}/force-requeue-delete`,
    cancelSourceDelete: (sourceId: string): string =>
      `/admin/sources/${sourceId}/cancel-delete`,
    forceFinalizeSourceDelete: (sourceId: string): string =>
      `/admin/sources/${sourceId}/force-finalize-delete`,
    sourceDelete: (sourceId: string): string =>
      `/admin/sources/${sourceId}/delete`,
    sourceDeletionPreview: (sourceId: string): string =>
      `/admin/sources/${sourceId}/deletion-preview`,
    jobs: (): string => "/admin/jobs",
    job: (jobId: string): string => `/admin/jobs/${jobId}`,
    restartJob: (jobId: string): string => `/admin/jobs/${jobId}/restart`,
    organizationDelete: (orgId: string): string =>
      `/admin/organizations/${orgId}/delete`,
  },

  sources: {
    authorizationCheck: (org: string, authorizationId: string): string =>
      `/organization/${org}/sources/authorizations/${authorizationId}/check`,
    providerInstances: (org: string): string =>
      `/organization/${org}/source-provider-instances`,
    providerInstance: (org: string, instanceId: string): string =>
      `/organization/${org}/source-provider-instances/${instanceId}`,
    providerInstanceEnable: (org: string, instanceId: string): string =>
      `/organization/${org}/source-provider-instances/${instanceId}/enable`,
    providerInstanceDisable: (org: string, instanceId: string): string =>
      `/organization/${org}/source-provider-instances/${instanceId}/disable`,
    providerInstanceDependencies: (org: string, instanceId: string): string =>
      `/organization/${org}/source-provider-instances/${instanceId}/dependencies`,
    providerInstanceDeleteInactiveAuthorizations: (
      org: string,
      instanceId: string,
    ): string =>
      `/organization/${org}/source-provider-instances/${instanceId}/delete-inactive-authorizations`,
  },

  profile: {
    issueApiToken: (): string => "/profile/api-tokens",
    deleteApiToken: (tokenId: string): string =>
      `/profile/api-tokens/${tokenId}`,
    revokeApiToken: (tokenId: string): string =>
      `/profile/api-tokens/${tokenId}/revoke`,
    disconnectOAuth: (accountId: string): string =>
      `/profile/oauth/${accountId}/disconnect`,
  },
} as const;

export type EndpointSection = keyof typeof Endpoints;
