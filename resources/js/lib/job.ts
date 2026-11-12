export function jobTypeLabel(type: string): string {
  switch (type) {
    case "identity.auth_mail":
      return "Authentication Email";
    case "source.sync":
      return "Source Sync";
    case "source.create":
      return "Source Create";
    case "source.delete":
      return "Source Delete";
    case "package.delete":
      return "Package Delete";
    case "organization.delete":
      return "Organization Delete";
    case "source.oauth.refresh":
      return "Source OAuth Refresh";
    case "store.release.archive":
      return "Store Release Archive";
    case "cleanup.package.archives":
      return "Cleanup Package Archives";
    case "increment.download.count":
      return "Increment Download Count";
    default:
      return type;
  }
}

export function jobStatusTone(status: string): string {
  switch (status) {
    case "pending":
    case "running":
      return "gold";
    case "held":
    case "audited":
      return "gold";
    case "completed":
      return "accent";
    case "failed":
      return "danger";
    case "canceled":
      return "neutral";
    default:
      return "muted";
  }
}
