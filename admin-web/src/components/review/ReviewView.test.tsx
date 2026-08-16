import { describe, expect, it } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { ToastProvider } from "@/components/ui-custom/Toast";
import { ReviewView } from "@/components/review/ReviewView";
import { mockApplication } from "@/test/handlers";

const BASE = "http://localhost:8000";

function renderReviewView() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <ReviewView />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

describe("ReviewView", () => {
  it("shows a loading state, then the pending application", async () => {
    renderReviewView();
    expect(screen.getByRole("status")).toBeInTheDocument();
    await waitFor(() => expect(screen.getByText(mockApplication.name)).toBeInTheDocument());
  });

  it("shows an error state with retry on a failed list load", async () => {
    server.use(
      http.get(`${BASE}/api/admin/review/applications`, () =>
        HttpResponse.json({ message: "boom", code: "server_error" }, { status: 500 }),
      ),
    );
    renderReviewView();
    await waitFor(() => expect(screen.getByText("boom")).toBeInTheDocument());
    expect(screen.getByRole("button", { name: /retry/i })).toBeInTheDocument();
  });

  it("shows an empty state when there are no pending applications", async () => {
    server.use(
      http.get(`${BASE}/api/admin/review/applications`, () =>
        HttpResponse.json({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
        }),
      ),
    );
    renderReviewView();
    await waitFor(() => expect(screen.getByText(/all caught up/i)).toBeInTheDocument());
  });

  it("opens the drawer, submits an approval with the current review_version, and requires a reason to reject", async () => {
    const user = userEvent.setup();
    let approveBody: unknown;
    server.use(
      http.post(`${BASE}/api/admin/review/applications/:id/approve`, async ({ request }) => {
        approveBody = await request.json();
        return HttpResponse.json({
          message: "ok",
          code: "pharmacy_approved",
          data: { ...mockApplication, status: "approved", review_version: 4 },
        });
      }),
    );
    renderReviewView();
    await waitFor(() => expect(screen.getByText(mockApplication.name)).toBeInTheDocument());

    await user.click(screen.getByText(mockApplication.name));
    const dialog = await screen.findByRole("dialog");

    const rejectButton = within(dialog).getByRole("button", { name: /reject/i });
    await user.click(rejectButton);
    const confirmReject = within(dialog).getByRole("button", { name: /confirm rejection/i });
    expect(confirmReject).toBeDisabled();

    await user.click(within(dialog).getByRole("button", { name: /cancel/i }));

    const approveButton = within(dialog).getByRole("button", { name: /^approve$/i });
    await user.click(approveButton);

    await waitFor(() =>
      expect(approveBody).toEqual({ review_version: mockApplication.review_version }),
    );
  });
});
