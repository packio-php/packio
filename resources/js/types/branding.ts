export interface Branding {
  name: string;
  tagline: string;
  domain: string;
  shortName: string;
  composerRepositoryAlias: string;
  registryTokenPrefix: string;
  registryAuthRealm: string;
  httpUserAgent: string;
  themeStorageKey: string;
  appBaseUrl: string;
}

declare global {
  interface Window {
    __appBranding?: Partial<Branding>;
  }
}
