export function providerIconKey(provider: string): string {
  switch (provider) {
    case "gitlab_self_hosted":
      return "gitlab";
    default:
      return provider;
  }
}
