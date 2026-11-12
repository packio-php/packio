export type ToastType = "success" | "error" | "info" | "warning";
export type ToastPosition =
  | "top-left"
  | "top-right"
  | "bottom-left"
  | "bottom-right";

export interface Toast {
  id: string;
  type: ToastType;
  message: string;
  duration: number;
  position: ToastPosition;
  createdAt: number;
}

export interface ToastOptions {
  duration?: number;
  position?: ToastPosition;
}

export interface ToastPayload {
  type: ToastType;
  message: string;
  duration: number | null;
  position: ToastPosition | null;
}
