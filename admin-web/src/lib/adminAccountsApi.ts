import { adminRequest } from "@/lib/adminApi";
import type { AdminAccount, AdminRole, ApiEnvelope } from "@/lib/types";

export function fetchAdmins(signal?: AbortSignal) {
  return adminRequest<{ data: AdminAccount[] }>("/api/admin/admins", { signal });
}

export function createAdmin(
  input: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: AdminRole;
  },
  signal?: AbortSignal,
) {
  return adminRequest<ApiEnvelope<AdminAccount>>("/api/admin/admins", {
    method: "POST",
    body: input,
    signal,
  });
}

export function changeAdminRole(adminId: string, role: AdminRole, signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<AdminAccount>>(`/api/admin/admins/${adminId}/role`, {
    method: "PATCH",
    body: { role },
    signal,
  });
}

export function disableAdmin(adminId: string, signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<AdminAccount>>(`/api/admin/admins/${adminId}/disable`, {
    method: "POST",
    signal,
  });
}

export function reactivateAdmin(adminId: string, signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<AdminAccount>>(`/api/admin/admins/${adminId}/reactivate`, {
    method: "POST",
    signal,
  });
}
