import type { SourceListItem } from "@/types/pages";

export type SourceTone = "accent" | "danger" | "muted" | "gold";

export function sourceStateLabel(
  source: Pick<SourceListItem, "active" | "archived">,
): "Active" | "Inactive" | "Archived" {
  if (source.archived) {
    return "Archived";
  }

  return source.active ? "Active" : "Inactive";
}

export function sourceStateTone(
  source: Pick<SourceListItem, "active" | "archived">,
): SourceTone {
  return !source.archived && source.active ? "accent" : "muted";
}

export function syncSummary(
  source: Pick<
    SourceListItem,
    "last_sync_status" | "last_failed_sync_at" | "last_successful_sync_at"
  >,
): {
  label: "Synced" | "Failed" | "Never synced" | "Pending";
  tone: SourceTone;
  at: string | null;
} {
  const failedAt = source.last_failed_sync_at ?? null;
  const successAt = source.last_successful_sync_at ?? null;

  if (source.last_sync_status === "failed") {
    return { label: "Failed", tone: "danger", at: failedAt };
  }

  if (source.last_sync_status === "success") {
    return { label: "Synced", tone: "accent", at: successAt };
  }

  if (!failedAt && !successAt) {
    return { label: "Never synced", tone: "muted", at: null };
  }

  return { label: "Pending", tone: "gold", at: failedAt ?? successAt };
}
