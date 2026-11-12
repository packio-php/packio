import "@inertiajs/core";
import type { AppPageProps } from "@/types/app";

declare module "@inertiajs/core" {
  interface InertiaConfig {
    sharedPageProps: AppPageProps;
  }
}

export {};
