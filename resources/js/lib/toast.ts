import { writable } from "svelte/store";
import type {
  Toast,
  ToastOptions,
  ToastPosition,
  ToastType,
} from "@/types/toast";

const DEFAULT_DURATION = 5000;
const DEFAULT_POSITION: ToastPosition = "top-right";
const MAX_TOASTS = 5;

const toasts = writable<Toast[]>([]);

function generateId(): string {
  return crypto.randomUUID();
}

function addToast(
  type: ToastType,
  message: string,
  options: ToastOptions = {},
): string {
  const id = generateId();
  const toast: Toast = {
    id,
    type,
    message,
    duration: options.duration ?? DEFAULT_DURATION,
    position: options.position ?? DEFAULT_POSITION,
    createdAt: Date.now(),
  };

  toasts.update((current) => {
    const positionToasts = current.filter((t) => t.position === toast.position);
    const others = current.filter((t) => t.position !== toast.position);

    if (positionToasts.length >= MAX_TOASTS) {
      positionToasts.shift();
    }

    return [...others, ...positionToasts, toast];
  });

  return id;
}

function removeToast(id: string): void {
  toasts.update((current) => current.filter((t) => t.id !== id));
}

export const toast = {
  success: (message: string, options?: ToastOptions) =>
    addToast("success", message, options),
  error: (message: string, options?: ToastOptions) =>
    addToast("error", message, options),
  info: (message: string, options?: ToastOptions) =>
    addToast("info", message, options),
  warning: (message: string, options?: ToastOptions) =>
    addToast("warning", message, options),
  dismiss: removeToast,
};

export { toasts };
