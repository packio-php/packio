import type { Branding } from "@/types/branding";

export const defaultBranding: Branding = {
  name: "Application",
  tagline: "",
  domain: "",
  shortName: "app",
  composerRepositoryAlias: "registry",
  registryTokenPrefix: "rt_",
  registryAuthRealm: "Application",
  httpUserAgent: "Packio/0.1",
  themeStorageKey: "packio-theme",
  appBaseUrl: "http://localhost:8000",
};

export function mergeBranding(branding: Partial<Branding> = {}): Branding {
  return { ...defaultBranding, ...branding };
}

export function browserBranding(): Branding {
  if (typeof window === "undefined") {
    return defaultBranding;
  }

  return mergeBranding(window.__appBranding ?? {});
}
