export interface OrganizationSummary {
  active?: boolean;
  anonymous_access?: boolean;
  created_at?: string;
  id?: string;
  name: string;
  slug: string;
  status?: "active" | "deleting";
  updated_at?: string;
}

export interface OrganizationMembership {
  organization_id?: string;
  role: "member" | "owner";
  user_id?: string;
}
