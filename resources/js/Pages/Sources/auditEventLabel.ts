// Keys recorded for a source by Source and Worker modules; unknown keys fall back to humanize().
const labels: Record<string, string> = {
  "source.create": "Source created",
  "source.sync": "Source synchronized",
  "source.delete": "Source deleted",
  "source.delete.initiated": "Source deletion started",
  "source.delete.initiated_by_admin": "Source deletion started by admin",
  "source.delete.requeued_by_admin": "Source deletion requeued by admin",
  "source.delete.force_requeued_by_admin":
    "Source deletion force-requeued by admin",
  "source.delete.canceled_by_admin": "Source deletion canceled by admin",
  "source.delete.force_finalize_interrupted_by_admin":
    "Source deletion finalization interrupted by admin",
  "source.delete.force_finalized_by_admin":
    "Source deletion force-finalized by admin",
  "source.oauth.refresh": "Source access token refreshed",
};

export function auditEventLabel(key: string): string {
  const known = labels[key];
  if (known) {
    return known;
  }

  const text = key.replace(/[._]+/g, " ").trim();

  return text.charAt(0).toUpperCase() + text.slice(1);
}
