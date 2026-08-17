import { adminRequest } from "@/lib/adminApi";
import type { AdminInbox, DataEnvelope } from "@/lib/types";

export function fetchInbox(signal?: AbortSignal) {
  return adminRequest<DataEnvelope<AdminInbox>>("/api/admin/inbox", { signal });
}
