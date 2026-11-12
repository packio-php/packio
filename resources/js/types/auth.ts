export interface AuthUser {
  display_name: string;
  email: string;
  id: string;
  is_admin?: boolean;
  roles: string[];
}

export interface AuthPayload {
  check?: boolean;
  user?: AuthUser | null;
}
