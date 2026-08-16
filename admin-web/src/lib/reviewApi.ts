import { adminRequest } from "@/lib/adminApi";
import type { ApiEnvelope, PaginatedResponse, PharmacyApplication } from "@/lib/types";

export function fetchApplications(page: number, perPage: number, signal?: AbortSignal) {
  const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
  return adminRequest<PaginatedResponse<PharmacyApplication>>(
    `/api/admin/review/applications?${params.toString()}`,
    { signal },
  );
}

export function fetchApplication(pharmacyId: number, signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<PharmacyApplication>>(
    `/api/admin/review/applications/${pharmacyId}`,
    { signal },
  );
}

export function approveApplication(
  pharmacyId: number,
  reviewVersion: number,
  signal?: AbortSignal,
) {
  return adminRequest<ApiEnvelope<PharmacyApplication>>(
    `/api/admin/review/applications/${pharmacyId}/approve`,
    {
      method: "POST",
      body: { review_version: reviewVersion },
      signal,
    },
  );
}

export function rejectApplication(
  pharmacyId: number,
  reviewVersion: number,
  reason: string,
  signal?: AbortSignal,
) {
  return adminRequest<ApiEnvelope<PharmacyApplication>>(
    `/api/admin/review/applications/${pharmacyId}/reject`,
    {
      method: "POST",
      body: { review_version: reviewVersion, reason },
      signal,
    },
  );
}
