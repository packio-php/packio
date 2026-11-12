import { describe, expect, it, vi } from "vitest";
import {
  redirectExpiredInertiaSession,
  reloadCurrentLocation,
} from "./httpException";

describe("CSRF HTTP exception handling", () => {
  it("reloads the current location without selecting a replacement URL", () => {
    const reload = vi.fn();

    reloadCurrentLocation({ reload });

    expect(reload).toHaveBeenCalledOnce();
  });

  it("handles 419 responses without replaying the failed Inertia request", () => {
    const preventDefault = vi.fn();
    const reload = vi.fn();
    const event = {
      detail: { response: { status: 419 } },
      preventDefault,
    } as never;

    expect(redirectExpiredInertiaSession(event, { reload })).toBe(true);
    expect(preventDefault).toHaveBeenCalledOnce();
    expect(reload).toHaveBeenCalledOnce();
  });

  it("leaves non-CSRF exceptions to the regular Inertia handler", () => {
    const preventDefault = vi.fn();
    const event = {
      detail: { response: { status: 422 } },
      preventDefault,
    } as never;

    expect(redirectExpiredInertiaSession(event)).toBe(false);
    expect(preventDefault).not.toHaveBeenCalled();
  });
});
