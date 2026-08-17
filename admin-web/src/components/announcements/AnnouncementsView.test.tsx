import { describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { ToastProvider } from "@/components/ui-custom/Toast";
import { AnnouncementsView } from "@/components/announcements/AnnouncementsView";

const BASE = "http://localhost:8000";

function audience(recipients: number) {
  return HttpResponse.json({
    data: {
      recipients,
      pharmacies:
        recipients === 0
          ? []
          : [
              { id: 1, name: "Alhajj Pharmacy" },
              { id: 2, name: "Barada Pharmacy" },
            ],
    },
  });
}

function renderView() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <AnnouncementsView />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

describe("AnnouncementsView", () => {
  it("broadcasts to every approved pharmacy by default", async () => {
    const sent = vi.fn();
    server.use(
      http.get(`${BASE}/api/admin/announcements/audience`, () => audience(2)),
      http.post(`${BASE}/api/admin/announcements`, async ({ request }) => {
        sent(await request.json());
        return HttpResponse.json(
          {
            message: "Sent.",
            code: "announcement_sent",
            data: { recipients: 2 },
          },
          { status: 201 },
        );
      }),
    );
    renderView();

    await waitFor(() =>
      expect(screen.getByText(/2 pharmacy\(ies\) will receive this/i)).toBeInTheDocument(),
    );

    await userEvent.type(screen.getByLabelText(/title/i), "Scheduled maintenance");
    await userEvent.type(
      screen.getByLabelText(/message/i),
      "The platform is unavailable on Friday.",
    );
    await userEvent.click(screen.getByRole("button", { name: /send announcement/i }));

    await waitFor(() =>
      expect(sent).toHaveBeenCalledWith({
        title: "Scheduled maintenance",
        message: "The platform is unavailable on Friday.",
        target: "all",
      }),
    );
  });

  it("sends the chosen pharmacy id when targeting one", async () => {
    const sent = vi.fn();
    server.use(
      http.get(`${BASE}/api/admin/announcements/audience`, () => audience(2)),
      http.post(`${BASE}/api/admin/announcements`, async ({ request }) => {
        sent(await request.json());
        return HttpResponse.json(
          {
            message: "Sent.",
            code: "announcement_sent",
            data: { recipients: 1 },
          },
          { status: 201 },
        );
      }),
    );
    renderView();

    await waitFor(() =>
      expect(screen.getByRole("button", { name: /one pharmacy/i })).toBeInTheDocument(),
    );
    await userEvent.click(screen.getByRole("button", { name: /one pharmacy/i }));
    await userEvent.selectOptions(screen.getByLabelText(/pharmacy/i), "2");
    await userEvent.type(screen.getByLabelText(/title/i), "Licence reminder");
    await userEvent.type(
      screen.getByLabelText(/message/i),
      "Please renew your licence this month.",
    );
    await userEvent.click(screen.getByRole("button", { name: /send announcement/i }));

    await waitFor(() =>
      expect(sent).toHaveBeenCalledWith({
        title: "Licence reminder",
        message: "Please renew your licence this month.",
        target: "pharmacy",
        pharmacy_id: 2,
      }),
    );
  });

  it("refuses a short message without calling the API", async () => {
    const sent = vi.fn();
    server.use(
      http.get(`${BASE}/api/admin/announcements/audience`, () => audience(2)),
      http.post(`${BASE}/api/admin/announcements`, () => {
        sent();
        return HttpResponse.json({}, { status: 201 });
      }),
    );
    renderView();

    await waitFor(() =>
      expect(screen.getByText(/2 pharmacy\(ies\) will receive this/i)).toBeInTheDocument(),
    );
    await userEvent.type(screen.getByLabelText(/title/i), "Hi");
    await userEvent.type(screen.getByLabelText(/message/i), "short");
    await userEvent.click(screen.getByRole("button", { name: /send announcement/i }));

    await waitFor(() => expect(screen.getByText(/not sent/i)).toBeInTheDocument());
    expect(sent).not.toHaveBeenCalled();
  });

  it("warns and disables sending when no pharmacy is approved", async () => {
    server.use(http.get(`${BASE}/api/admin/announcements/audience`, () => audience(0)));
    renderView();

    await waitFor(() =>
      expect(screen.getByRole("alert")).toHaveTextContent(/no pharmacy has been approved/i),
    );
    expect(screen.getByRole("button", { name: /send announcement/i })).toBeDisabled();
  });

  it("surfaces a server rejection", async () => {
    server.use(
      http.get(`${BASE}/api/admin/announcements/audience`, () => audience(2)),
      http.post(`${BASE}/api/admin/announcements`, () =>
        HttpResponse.json(
          {
            message: "There are no approved pharmacies to notify.",
            code: "announcement_no_recipients",
          },
          { status: 422 },
        ),
      ),
    );
    renderView();

    await waitFor(() =>
      expect(screen.getByText(/2 pharmacy\(ies\) will receive this/i)).toBeInTheDocument(),
    );
    await userEvent.type(screen.getByLabelText(/title/i), "Announcement");
    await userEvent.type(screen.getByLabelText(/message/i), "A perfectly valid message body.");
    await userEvent.click(screen.getByRole("button", { name: /send announcement/i }));

    await waitFor(() =>
      expect(screen.getByText(/no approved pharmacies to notify/i)).toBeInTheDocument(),
    );
  });
});
