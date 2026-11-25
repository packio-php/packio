export function providerIconKey(provider: string): string {
  switch (provider) {
    case "gitlab_self_hosted":
      return "gitlab";
    case "forgejo_self_hosted":
      return "forgejo";
    default:
      return provider;
  }
}
