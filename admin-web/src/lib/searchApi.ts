import { adminRequest } from "@/lib/adminApi";
import type { AdminSearchResults, DataEnvelope } from "@/lib/types";

export function searchConsole(query: string, signal?: AbortSignal) {
  return adminRequest<DataEnvelope<AdminSearchResults>>(
    `/api/admin/search?q=${encodeURIComponent(query)}`,
    { signal },
  );
}
