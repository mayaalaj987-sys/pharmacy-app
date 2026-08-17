import { adminRequest } from "@/lib/adminApi";
import type {
  DataEnvelope,
  JobMarketAnalytics,
  OnboardingAnalytics,
  PharmacyFleetAnalytics,
} from "@/lib/types";

export function fetchPharmacyFleet(signal?: AbortSignal) {
  return adminRequest<DataEnvelope<PharmacyFleetAnalytics>>("/api/admin/analytics/pharmacies", {
    signal,
  });
}

export function fetchJobMarket(signal?: AbortSignal) {
  return adminRequest<DataEnvelope<JobMarketAnalytics>>("/api/admin/analytics/job-market", {
    signal,
  });
}

export function fetchOnboarding(signal?: AbortSignal) {
  return adminRequest<DataEnvelope<OnboardingAnalytics>>("/api/admin/analytics/onboarding", {
    signal,
  });
}
