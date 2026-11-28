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

export function authorizationCardClass(
  authorization: ProviderAuthorizationPayload,
  selected: boolean,
): string {
  const reconnectRequired = isAuthorizationReconnectRequired(authorization);

  if (selected && reconnectRequired) {
    return "border-(--button-danger-bg) bg-(--danger-soft) shadow-sm";
  }

  if (selected) {
    return "border-(--accent) bg-(--surface-muted) shadow-sm";
  }

  if (reconnectRequired) {
    return "border-(--button-danger-bg) bg-(--danger-soft)";
  }

  return "border-(--panel-border) bg-(--surface) hover:border-(--accent) hover:bg-(--surface-muted)";
}
