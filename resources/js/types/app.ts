import type { AuthPayload } from "@/types/auth";
import type { Branding } from "@/types/branding";
import type { CsrfPayload } from "@/types/csrf";
import type { ListViewMode } from "@/lib/listViewMode";
import type {
  OrganizationMembership,
  OrganizationSummary,
} from "@/types/organization";

export interface AppVersionInfo {
  version: string;
  number: string;
  channel: string;
  sha: string;
  branch: string;
  built_at: string;
  distance: number;
  dirty: boolean;
  tag: string;
  display: string;
}

export interface SharedAppInfo {
  version?: AppVersionInfo;
  [key: string]: unknown;
}

export interface InitialTheme {
  mode: "dark" | "light" | "system";
  resolved: "dark" | "light" | null;
  pageBg: string;
  lightPageBg: string;
  darkPageBg: string;
}

export interface AppPageProps {
  app?: SharedAppInfo;
  auth?: AuthPayload;
  branding?: Partial<Branding>;
  csrf?: CsrfPayload;
  current_url?: string;
  current_membership?: OrganizationMembership | null;
  initial_list_view_mode?: ListViewMode;
  initial_theme?: InitialTheme;
  old?: Record<string, unknown>;
  organization?: OrganizationSummary | null;
  organizations?: OrganizationSummary[];
  [key: string]: unknown;
}
