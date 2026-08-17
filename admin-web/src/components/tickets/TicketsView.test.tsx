import { describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { ToastProvider } from "@/components/ui-custom/Toast";
import { TicketsView } from "@/components/tickets/TicketsView";

const BASE = "http://localhost:8000";

const openTicket = {
  id: 1,
  subject: "Cannot receive an order",
  message: "Receiving a purchase order does not add stock to my shelves.",
  status: "open" as const,
  sender: {
    name: "Maya Alhaj",
    role: "pharmacist" as const,
    email: "maya@example.test",
  },
  pharmacy: "Alhajj Pharmacy",
  admin_response: null,
  responded_by: null,
  responded_at: null,
  created_at: "2026-08-17T09:00:00+00:00",
};

const resolvedTicket = {
  ...openTicket,
  id: 2,
  subject: "Already answered",
  status: "resolved" as const,
  admin_response: "Update to the latest build.",
  responded_by: "Aya",
  responded_at: "2026-08-17T10:00:00+00:00",
};

function queue(tickets: unknown[], openTotal = 1) {
  return HttpResponse.json({
    data: tickets,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: tickets.length,
      open_total: openTotal,
    },
  });
}

function renderTickets() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <TicketsView />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

describe("TicketsView", () => {
  it("lists the queue and opens a ticket on click", async () => {
    server.use(http.get(`${BASE}/api/admin/support/tickets`, () => queue([openTicket])));
    renderTickets();

    expect(screen.getByRole("status")).toBeInTheDocument();
    await waitFor(() => expect(screen.getByText("Select a ticket to view")).toBeInTheDocument());

    await userEvent.click(screen.getByText(openTicket.subject));

    expect(screen.getByText(openTicket.message)).toBeInTheDocument();
    expect(screen.getByLabelText(/your response/i)).toBeInTheDocument();
  });

  it("sends a response and resolves the ticket", async () => {
    const respond = vi.fn();
    server.use(
      http.get(`${BASE}/api/admin/support/tickets`, () => queue([openTicket])),
      http.post(`${BASE}/api/admin/support/tickets/1/respond`, async ({ request }) => {
        respond(await request.json());
        return HttpResponse.json({
          message: "Response sent.",
          code: "support_ticket_resolved",
          data: resolvedTicket,
        });
      }),
    );
    renderTickets();

    await waitFor(() => expect(screen.getByText(openTicket.subject)).toBeInTheDocument());
    await userEvent.click(screen.getByText(openTicket.subject));
    await userEvent.type(screen.getByLabelText(/your response/i), "Update to the latest build.");
    await userEvent.click(screen.getByRole("button", { name: /send response/i }));

    await waitFor(() =>
      expect(respond).toHaveBeenCalledWith({
        response: "Update to the latest build.",
      }),
    );
  });

  it("refuses a response that is too short without calling the API", async () => {
    const respond = vi.fn();
    server.use(
      http.get(`${BASE}/api/admin/support/tickets`, () => queue([openTicket])),
      http.post(`${BASE}/api/admin/support/tickets/1/respond`, () => {
        respond();
        return HttpResponse.json({});
      }),
    );
    renderTickets();

    await waitFor(() => expect(screen.getByText(openTicket.subject)).toBeInTheDocument());
    await userEvent.click(screen.getByText(openTicket.subject));
    await userEvent.type(screen.getByLabelText(/your response/i), "ok");
    await userEvent.click(screen.getByRole("button", { name: /send response/i }));

    await waitFor(() => expect(screen.getByText(/response too short/i)).toBeInTheDocument());
    expect(respond).not.toHaveBeenCalled();
  });

  it("explains a 409 as another administrator having answered first", async () => {
    server.use(
      http.get(`${BASE}/api/admin/support/tickets`, () => queue([openTicket])),
      http.post(`${BASE}/api/admin/support/tickets/1/respond`, () =>
        HttpResponse.json(
          {
            message: "This ticket has already been answered.",
            code: "support_ticket_already_resolved",
          },
          { status: 409 },
        ),
      ),
    );
    renderTickets();

    await waitFor(() => expect(screen.getByText(openTicket.subject)).toBeInTheDocument());
    await userEvent.click(screen.getByText(openTicket.subject));
    await userEvent.type(screen.getByLabelText(/your response/i), "My own answer to this ticket.");
    await userEvent.click(screen.getByRole("button", { name: /send response/i }));

    await waitFor(() =>
      expect(screen.getByText(/another administrator already answered/i)).toBeInTheDocument(),
    );
  });

  it("distinguishes an empty platform from an all-answered queue", async () => {
    // "Every ticket is answered" is wrong when nobody has ever written in.
    server.use(http.get(`${BASE}/api/admin/support/tickets`, () => queue([], 0)));
    const { unmount } = renderTickets();

    await waitFor(() =>
      expect(screen.getByText(/no one has contacted support yet/i)).toBeInTheDocument(),
    );
    unmount();

    server.use(
      http.get(`${BASE}/api/admin/support/tickets`, () =>
        HttpResponse.json({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 3, open_total: 0 },
        }),
      ),
    );
    renderTickets();

    await waitFor(() =>
      expect(screen.getByText(/every ticket has been answered/i)).toBeInTheDocument(),
    );
  });

  it("shows an answered ticket read-only", async () => {
    server.use(http.get(`${BASE}/api/admin/support/tickets`, () => queue([resolvedTicket], 0)));
    renderTickets();

    await waitFor(() => expect(screen.getByText(resolvedTicket.subject)).toBeInTheDocument());
    await userEvent.click(screen.getByText(resolvedTicket.subject));

    expect(screen.getByText(/answered by Aya/i)).toBeInTheDocument();
    expect(screen.queryByLabelText(/your response/i)).not.toBeInTheDocument();
  });
});
