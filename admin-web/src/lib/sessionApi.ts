import { adminRequest } from "@/lib/adminApi";
import type { ApiEnvelope, SessionData } from "@/lib/types";

export function login(email: string, password: string, signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<SessionData>>("/api/admin/login", {
    method: "POST",
    body: { email, password },
    signal,
  });
}

export function fetchSession(signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<SessionData>>("/api/admin/session", { signal });
}

export function logout(signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<undefined>>("/api/admin/logout", { method: "POST", signal });
}
