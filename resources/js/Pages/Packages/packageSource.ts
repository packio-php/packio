export function sourceIssue(source: {
  last_sync_status: string;
  active: boolean;
  archived: boolean;
  status?: string;
}): string | null {
  if (source.status === "deleting") return "Deleting";
  if (source.archived) return "Archived";
  if (!source.active) return "Source inactive";
  if (source.last_sync_status === "failed") return "Sync failed";

  return null;
}
