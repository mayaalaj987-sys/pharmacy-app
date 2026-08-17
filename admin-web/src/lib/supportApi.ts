import { adminRequest } from "@/lib/adminApi";
import type { ApiEnvelope, SupportTicket, SupportTicketPage } from "@/lib/types";

export function fetchTickets(
  page: number,
  perPage: number,
  status: "all" | "open" | "resolved",
  signal?: AbortSignal,
) {
  const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
  if (status !== "all") params.set("status", status);

  return adminRequest<SupportTicketPage>(`/api/admin/support/tickets?${params.toString()}`, {
    signal,
  });
}

export function respondToTicket(ticketId: number, response: string, signal?: AbortSignal) {
  return adminRequest<ApiEnvelope<SupportTicket>>(
    `/api/admin/support/tickets/${ticketId}/respond`,
    { method: "POST", body: { response }, signal },
  );
}
