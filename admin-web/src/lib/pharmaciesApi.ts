import { adminRequest } from "@/lib/adminApi";
import type { ApiEnvelope, PharmacyControlPage } from "@/lib/types";

export type PharmacyFilter = "all" | "approved" | "pending" | "rejected" | "blocked";

export function fetchPharmacies(
  page: number,
  perPage: number,
  filter: PharmacyFilter,
  search: string,
  signal?: AbortSignal,
) {
  const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
  if (filter !== "all") params.set("status", filter);
  if (search.trim() !== "") params.set("search", search.trim());

  return adminRequest<PharmacyControlPage>(`/api/admin/pharmacies?${params.toString()}`, {
    signal,
  });
}

export function blockPharmacy(pharmacyId: number, reason: string, signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<undefined>>(`/api/admin/pharmacies/${pharmacyId}/block`, {
    method: "POST",
    body: { reason },
    signal,
  });
}

export function unblockPharmacy(pharmacyId: number, signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<undefined>>(`/api/admin/pharmacies/${pharmacyId}/unblock`, {
    method: "POST",
    signal,
  });
}
