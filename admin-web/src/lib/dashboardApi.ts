import { adminRequest } from "@/lib/adminApi";
import type { ActivityEntry, DashboardOverview, DataEnvelope } from "@/lib/types";

export function fetchOverview(signal?: AbortSignal) {
  return adminRequest<DataEnvelope<DashboardOverview>>("/api/admin/analytics/overview", {
    signal,
  });
}

export function fetchActivity(limit = 12, signal?: AbortSignal) {
  return adminRequest<DataEnvelope<ActivityEntry[]>>(`/api/admin/activity?limit=${limit}`, {
    signal,
  });
}
