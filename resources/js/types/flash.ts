import type { ToastPayload } from "@/types/toast";

export interface PackioFlashPayload {
  error?: string;
  issued_api_token?: { name: string; token: string };
  success?: string;
  toasts?: ToastPayload[];
}

declare module "@inertiajs/core" {
  interface InertiaConfig {
    flashDataType: PackioFlashPayload;
  }
}
