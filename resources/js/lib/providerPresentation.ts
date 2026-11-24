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

export function providerLabel(provider: string): string | null {
  switch (provider) {
    case "gitlab_self_hosted":
      return "GitLab Self-Hosted";
    case "forgejo_self_hosted":
      return "Forgejo Self-Hosted";
    default:
      return null;
  }
}
