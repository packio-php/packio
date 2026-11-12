import type { AppPageProps } from "@/types/app";
import type { Branding } from "@/types/branding";

export interface ValidationErrors {
  [key: string]: string[] | undefined;
}

export interface OldInput {
  [key: string]: string | undefined;
  display_name?: string;
  email?: string;
  redirect_to?: string;
}

export type OAuthProviderConfig = {
  provider: "github" | "gitlab" | "bitbucket" | "codeberg";
  label: string;
  enabled: boolean;
};

export interface LoginPageProps extends AppPageProps {
  branding?: Partial<Branding>;
  errors?: ValidationErrors;
  old?: OldInput;
  registration_open?: boolean;
  redirect_to?: string;
  oauth_providers?: OAuthProviderConfig[];
  turnstile?: {
    enabled: boolean;
    site_key: string;
  };
  honeypot?: {
    field_name: string;
  };
}

export interface OAuthEmailPageProps extends AppPageProps {
  branding?: Partial<Branding>;
  errors?: ValidationErrors;
  old?: {
    email?: string;
  };
  provider?: "github" | "gitlab" | "bitbucket" | "codeberg";
  provider_label?: string;
  provider_username?: string | null;
  turnstile?: {
    enabled: boolean;
    site_key: string;
  };
  honeypot?: {
    field_name: string;
  };
}

export interface RegisterPageProps extends AppPageProps {
  branding?: Partial<Branding>;
  errors?: ValidationErrors;
  old?: OldInput;
  registration_open?: boolean;
  redirect_to?: string;
  oauth_providers?: OAuthProviderConfig[];
  turnstile?: {
    enabled: boolean;
    site_key: string;
  };
  honeypot?: {
    field_name: string;
  };
}

export interface ForgotPasswordPageProps extends AppPageProps {
  branding?: Partial<Branding>;
  errors?: ValidationErrors;
  old?: {
    email?: string;
  };
  turnstile?: {
    enabled: boolean;
    site_key: string;
  };
  honeypot?: {
    field_name: string;
  };
}

export interface ResetPasswordPageProps extends AppPageProps {
  branding?: Partial<Branding>;
  errors?: ValidationErrors;
  old?: {
    selector?: string;
    token?: string;
  };
  selector?: string;
  token?: string;
  turnstile?: {
    enabled: boolean;
    site_key: string;
  };
  honeypot?: {
    field_name: string;
  };
}

export interface BannedAccountPageProps extends AppPageProps {
  reason?: string | null;
  banned_at?: string | null;
}

export interface AdminDashboardStats {
  admins_count: number;
  organizations_count: number;
  packages_count: number;
  sources_count: number;
  users_count: number;
}

export interface AdminDashboardPageProps extends AppPageProps {
  stats?: AdminDashboardStats;
}

export interface AdminUserListItem {
  ban_reason?: string | null;
  banned_at?: string | null;
  created_at: string;
  can_ban?: boolean;
  display_name: string;
  email: string;
  id: string;
  is_admin: boolean;
  is_banned?: boolean;
  moderation_hint?: string | null;
  roles: string[];
  updated_at?: string;
  email_verified?: boolean;
  banned_by_user_id?: string | null;
}

export interface AdminUsersPageProps extends AppPageProps {
  users?: AdminUserListItem[];
  pagination?: AdminUsersPaginationMeta;
  filters?: AdminUsersFilters;
}

export interface AdminUsersFilters {
  search: string;
  status: "" | "active" | "banned";
  page: number;
  per_page: number;
}

export interface AdminUsersPaginationMeta {
  current_page: number;
  from: number;
  has_more_pages: boolean;
  has_previous_pages: boolean;
  last_page: number;
  per_page: number;
  to: number;
  total: number;
}

export interface AdminUserDetailsPageProps extends AppPageProps {
  user_record?: AdminUserListItem | null;
  ban_history?: AdminUserBanHistoryEntry[];
  errors?: ValidationErrors;
}

export interface AdminUserBanHistoryEntry {
  action: "identity.user.banned" | "identity.user.unbanned" | string;
  actor_id?: string | null;
  id: string;
  occurred_at: string;
  reason?: string | null;
}

export interface AdminOrganizationInvitationListItem {
  accept_url: string;
  expires_at: string;
  id: string;
  invited_by: string;
  organization_slug: string;
  role: "member" | "owner";
  status: "accepted" | "expired" | "pending" | "revoked";
  token: string;
}

export interface AdminOrganizationMemberListItem {
  created_at?: string;
  display_name: string;
  email: string;
  id: string;
  role: "member" | "owner" | string;
  user_id: string;
}

export interface PaginationMeta {
  current_page: number;
  from: number;
  has_more_pages: boolean;
  has_previous_pages: boolean;
  items: unknown[];
  last_page: number;
  per_page: number;
  to: number;
  total: number;
}

export interface AdminOrganizationListItem {
  active?: boolean;
  anonymous_access: boolean;
  created_at?: string;
  id: string;
  invitations?: AdminOrganizationInvitationListItem[];
  invitations_count: number;
  members?: AdminOrganizationMemberListItem[];
  members_count: number;
  members_pagination?: PaginationMeta;
  name: string;
  packages_count?: number;
  slug: string;
  sources_count?: number;
  status?: "active" | "deleting";
  updated_at?: string;
}

export interface AdminOrganizationsPageProps extends AppPageProps {
  organizations?: AdminOrganizationListItem[];
  pagination?: AdminUsersPaginationMeta;
}

export interface AdminOrganizationDetailsPageProps extends AppPageProps {
  organization_record?: AdminOrganizationListItem | null;
}

export type RuntimeSettingOverride = "inherit" | "on" | "off";

export interface RuntimeSettingDefinition {
  allowed_values: RuntimeSettingOverride[];
  config_path: string;
  group: "identity" | "oauth_login" | "source_oauth" | string;
  help_text: string;
  key: string;
  label: string;
  provider_key: string | null;
}

export interface RuntimeSettingValue {
  effective: boolean;
  fallback: boolean;
  fallback_source: string;
  override: RuntimeSettingOverride;
}

export interface AdminSettingsPayload {
  definitions: RuntimeSettingDefinition[];
  values: Record<string, RuntimeSettingValue>;
}

export interface AdminSystemPayload {
  app_debug: boolean;
  app_production_mode: boolean;
  app_timezone: string;
  branding: Partial<Branding>;
  registry_base_path: string;
  registry_cache_ttl: number;
  registry_metadata_format: string;
}

export interface AdminSettingsPageProps extends AppPageProps {
  settings?: AdminSettingsPayload;
  system?: AdminSystemPayload;
}

export interface OrganizationsIndexPageProps extends AppPageProps {
  errors?: ValidationErrors;
  has_organizations?: boolean;
  old?: {
    name?: string;
  };
  selection_required?: boolean;
}

export interface ProfilePayload {
  created_at?: string;
  display_name: string;
  email: string;
  has_password: boolean;
  id: string;
  roles: string[];
  updated_at?: string | null;
}

export interface ProfileOAuthAccountPayload {
  account_label: string;
  id: string;
  provider: string;
  provider_label: string;
  provider_email: string | null;
  provider_username: string | null;
}

export interface ApiAccessTokenPayload {
  created_at: string;
  expires_at?: string | null;
  id: string;
  last_used_at?: string | null;
  name: string;
  revoked: boolean;
  revoked_at?: string | null;
  scopes: string[];
  token_prefix: string;
  updated_at?: string | null;
}

export interface ProfilePageProps extends AppPageProps {
  api_tokens?: ApiAccessTokenPayload[];
  branding?: Partial<Branding>;
  oauth_accounts?: ProfileOAuthAccountPayload[];
  profile?: ProfilePayload | null;
}

export interface OrganizationMemberListItem {
  can_demote: boolean;
  can_promote: boolean;
  can_remove: boolean;
  can_update_role: boolean;
  created_at?: string;
  display_name: string;
  email: string;
  id: string;
  is_current_user: boolean;
  role: "member" | "owner";
}

export interface OrganizationInvitationListItem {
  /** Missing when the stored token cannot be decrypted. */
  accept_url?: string;
  expires_at: string;
  id: string;
  invited_by: string;
  role: "member" | "owner";
  status: "accepted" | "expired" | "pending" | "revoked";
}

export interface OrganizationSettingsPayload {
  active?: boolean;
  anonymous_access?: boolean;
  created_at?: string;
  name: string;
  route_key: string;
  updated_at?: string;
}

export interface OrganizationInvitationAcceptPayload {
  accepted_at?: string | null;
  expires_at?: string;
  revoked_at?: string | null;
  role: "member" | "owner";
  status: "accepted" | "pending" | "revoked" | "unknown";
  token: string;
}

export interface RegistryPackageSummary {
  latest_version?: string | null;
  name: string;
}

export interface PaginationPayload<T = unknown> {
  current_page: number;
  from: number;
  has_more_pages: boolean;
  has_previous_pages: boolean;
  items?: T[];
  last_page: number;
  per_page: number;
  to: number;
  total: number;
}

export interface RegistrySetupPageProps extends AppPageProps {
  base_url?: string;
  branding?: Partial<Branding>;
  organization?: import("@/types/organization").OrganizationSummary | null;
  private_bootstrap?: string | null;
  private_packages?: RegistryPackageSummary[];
  public_bootstrap?: string;
  public_packages?: RegistryPackageSummary[];
  registry_host?: string;
}

export interface PackagesIndexSection {
  active: boolean;
  description?: string | null;
  download_count: number;
  latest_version?: string | null;
  name: string;
  source?: {
    active: boolean;
    archived: boolean;
    id: string;
    last_failed_sync_at?: string | null;
    last_successful_sync_at?: string | null;
    last_sync_status: string;
    name: string;
    status?: "active" | "deleting";
    type: string;
    url: string;
    visibility: "private" | "public";
  } | null;
  visibility: "private" | "public";
}

export interface PackageStatsPayload {
  listed: number;
  private: number;
  public: number;
  total: number;
}

export interface PackageIndexFilters {
  listed: "" | "hidden" | "listed";
  page: number;
  per_page: number;
  search: string;
  source: "" | "managed" | "manual";
  source_sync_status: string[];
  visibility: Array<"private" | "public">;
}

export interface FilterOptionPayload {
  label: string;
  value: string;
}

export interface PackageFilterOptions {
  source_sync_statuses: FilterOptionPayload[];
}

export interface PackageDetailsPayload {
  access_hint: string;
  active: boolean;
  anonymous_access: boolean;
  created_at: string;
  description?: string | null;
  download_count: number;
  name: string;
  readme_html?: string | null;
  source_summary?: {
    active: boolean;
    archived: boolean;
    default_ref: string;
    id: string;
    last_failed_sync_at?: string | null;
    last_successful_sync_at?: string | null;
    last_sync_error?: string | null;
    last_sync_status: string;
    name: string;
    status?: "active" | "deleting";
    type: string;
    url: string;
    visibility: "private" | "public";
  } | null;
  source_type: string;
  source_url: string;
  type: string;
  updated_at?: string | null;
  visibility: "private" | "public";
}

export interface PackageReleasePayload {
  active: boolean;
  created_at: string;
  dist_url: string;
  download_count: number;
  reference: string;
  released_at?: string | null;
  source_url: string;
  version: string;
}

export interface RegistryTokenPayload {
  created_at: string;
  id: string;
  last_used_at?: string | null;
  masked_token: string;
  name: string;
  permissions: string[];
  plain_token?: string | null;
}

export interface TokensIndexPageProps extends AppPageProps {
  branding?: Partial<Branding>;
  can_manage_tokens?: boolean;
  organization?: import("@/types/organization").OrganizationSummary | null;
  tokens?: RegistryTokenPayload[];
}

export interface SourceProviderPayload {
  capabilities?: Record<string, boolean>;
  description: string;
  key: string;
  name: string;
  oauth_available: boolean;
  oauth_enabled: boolean;
  oauth_provider: string | null;
}

export interface ProviderAuthorizationPayload {
  account_name?: string | null;
  auth_failure_reason?: string | null;
  auth_health?: string | null;
  auth_strategy: string;
  configured: boolean;
  id: string;
  last_token_refresh_at?: string | null;
  masked_value?: string;
  provider: string;
  provider_instance_id?: string | null;
  source_oauth_account_id?: string | null;
  status: string;
}

export interface SourceProviderInstancePayload {
  auth_mode: "oauth2" | "token" | "oauth2_and_token";
  base_url: string;
  client_id?: string;
  has_client_secret?: boolean;
  id: string;
  label: string;
  provider_type: string;
  status: "active" | "inactive";
}

export interface SourceProviderInstanceDependenciesPayload {
  instance: Pick<
    SourceProviderInstancePayload,
    "id" | "label" | "base_url" | "provider_type"
  >;
  source_count: number;
  authorization_count: number;
  active_authorization_count: number;
  inactive_authorization_count: number;
  sources: Array<{
    id: string;
    name: string;
    url: string;
    provider: string;
    status: string;
  }>;
  authorizations: Array<{
    id: string;
    account_name: string;
    provider: string;
    auth_strategy: string;
    status: "active" | "inactive";
  }>;
}

export interface ProviderResourcePayload {
  canonical_url: string;
  default_ref?: string;
  id: string;
  name: string;
  private: boolean;
  provider: string;
}

export interface SourceWebhookEventPayload {
  created_at: string;
  delivery_id: string;
  event: string;
  message?: string | null;
  provider?: string;
  reprocess_count?: number;
  scheduled_job_id?: string | null;
  status: string;
}

export interface SourceListItem {
  active: boolean;
  archived: boolean;
  archived_at?: string | null;
  default_ref: string;
  id: string;
  last_failed_sync_at?: string | null;
  last_successful_sync_at?: string | null;
  last_sync_error?: string | null;
  last_sync_status: string;
  name: string;
  keep_last_releases: number;
  provider?: string;
  provider_label?: string;
  status?: "active" | "deleting";
  url: string;
  visibility: "private" | "public";
  webhook_events?: SourceWebhookEventPayload[];
  webhook_secret?: string | null;
  webhook_url?: string | null;
}

export interface SourcesIndexStatsPayload {
  active: number;
  archived: number;
  total: number;
}

export interface SourceIndexFilters {
  page: number;
  per_page: number;
  provider: string[];
  search: string;
  status: Array<"active" | "archived" | "inactive">;
  sync_status: string[];
  visibility: Array<"private" | "public">;
}

export interface SourceFilterOptions {
  providers: FilterOptionPayload[];
  sync_statuses: FilterOptionPayload[];
}

export interface SourceDeletionPreviewPayload {
  source: {
    id: string;
    name: string;
    provider: string;
    organization_id: string;
  };
  packages_count: number;
  releases_count: number;
}

export interface AuditEntryPayload {
  id: string;
  event: string;
  occurred_at: string;
}

export interface UserOAuthAccountPayload {
  id: string;
  provider: string;
  provider_email: string | null;
  provider_username: string | null;
}

export interface SourcesIndexPageProps extends AppPageProps {
  audit?: AuditEntryPayload[];
  branding?: Partial<Branding>;
  can_manage_sources?: boolean;
  filters?: SourceIndexFilters;
  organization?: import("@/types/organization").OrganizationSummary | null;
  pagination?: PaginationPayload<SourceListItem>;
  provider_authorizations?: ProviderAuthorizationPayload[];
  provider_resources?: Record<string, ProviderResourcePayload[]>;
  providers?: SourceProviderPayload[];
  sources?: SourceListItem[];
  source_filter_options?: SourceFilterOptions;
  source_stats?: SourcesIndexStatsPayload;
  source_urls?: string[];
  source_provider_instances?: SourceProviderInstancePayload[];
  user_oauth_accounts?: UserOAuthAccountPayload[];
}

export interface SourceCredentialsPayload {
  auth_failure_reason?: string | null;
  auth_health?: string | null;
  auth_type?: string | null;
  configured: boolean;
  last_token_refresh_at?: string | null;
  masked_value?: string | null;
  source_oauth_account_id?: string | null;
  status_label?: string | null;
}

export interface SourceDetailsPayload {
  active: boolean;
  archived: boolean;
  archived_at?: string | null;
  created_at: string;
  credentials?: SourceCredentialsPayload | null;
  default_ref: string;
  id: string;
  keep_last_releases: number;
  last_failed_sync_at?: string | null;
  last_successful_sync_at?: string | null;
  last_sync_error?: string | null;
  last_sync_status: string;
  name: string;
  packages_active: boolean;
  provider?: string;
  provider_label?: string;
  status?: "active" | "deleting";
  type: string;
  url: string;
  visibility: "private" | "public";
  webhook_external_id?: string | null;
  webhook_registered?: boolean;
  webhook_secret?: string | null;
  webhook_url?: string | null;
  supports_auto_registration?: boolean;
}

export interface SourceStatsPayload {
  deliveries_count?: number;
  packages_count?: number;
  releases_count?: number;
}

export interface SourcePackageReleaseTagPayload {
  version: string;
}

export interface SourcePackagePayload {
  active: boolean;
  description?: string | null;
  name: string;
  release_count: number;
  releases: SourcePackageReleaseTagPayload[];
  visibility: "private" | "public";
}
