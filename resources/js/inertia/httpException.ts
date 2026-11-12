import type { GlobalEvent } from "@inertiajs/core";

type ReloadableLocation = Pick<Location, "reload">;

export function reloadCurrentLocation(
  location: ReloadableLocation = window.location,
): void {
  location.reload();
}

export function redirectExpiredInertiaSession(
  event: GlobalEvent<"httpException">,
  location: ReloadableLocation = window.location,
): boolean {
  if (event.detail?.response?.status !== 419) {
    return false;
  }

  event.preventDefault();
  reloadCurrentLocation(location);

  return true;
}
