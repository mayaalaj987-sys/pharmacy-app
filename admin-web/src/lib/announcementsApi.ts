import { adminRequest } from "@/lib/adminApi";
import type {
  AnnouncementAudience,
  AnnouncementResult,
  ApiEnvelope,
  DataEnvelope,
} from "@/lib/types";

export function fetchAudience(signal?: AbortSignal) {
  return adminRequest<DataEnvelope<AnnouncementAudience>>("/api/admin/announcements/audience", {
    signal,
  });
}

export function sendAnnouncement(
  body: {
    title: string;
    message: string;
    target: "all" | "pharmacy";
    pharmacy_id?: number;
  },
  signal?: AbortSignal,
) {
  return adminRequest<ApiEnvelope<AnnouncementResult>>("/api/admin/announcements", {
    method: "POST",
    body,
    signal,
  });
}
