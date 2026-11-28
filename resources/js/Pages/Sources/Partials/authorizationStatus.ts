import type { ProviderAuthorizationPayload } from "@/types/pages";

export function isAuthorizationReconnectRequired(
  authorization: ProviderAuthorizationPayload | null | undefined,
): boolean {
  return (
    authorization?.status === "inactive" ||
    authorization?.auth_health === "reauthorization_required"
  );
}

export function authorizationHealthLabel(
  authorization: ProviderAuthorizationPayload | null | undefined,
): string | null {
  if (!authorization) {
    return null;
  }

  return isAuthorizationReconnectRequired(authorization)
    ? "Reauthorization required"
    : authorization.auth_health === "healthy"
      ? "Healthy"
      : authorization.auth_health
        ? "Authorization error"
        : null;
}
