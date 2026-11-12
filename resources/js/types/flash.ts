import type { ToastPayload } from "@/types/toast";

export interface PackioFlashPayload {
  error?: string;
  success?: string;
  toasts?: ToastPayload[];
}

declare module "@inertiajs/core" {
  interface InertiaConfig {
    flashDataType: PackioFlashPayload;
  }
}
