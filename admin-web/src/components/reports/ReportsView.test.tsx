import { describe, expect, it } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { ReportsView } from "@/components/reports/ReportsView";

const BASE = "http://localhost:8000";

const fleet = {
  total_owners: 4,
  owners_operating: 2,
  owners_without_an_approved_pharmacy: 2,
  branches: { approved: 3, pending: 1, rejected: 1 },
  distribution: {
    single_branch_owners: 1,
    multi_branch_owners: 1,
    single_branch_percentage: 50,
    multi_branch_percentage: 50,
  },
};

const jobMarket = {
  open_positions: 1,
  active_seekers: 1,
  total_applicants: 5,
  hired: 3,
  rejected: 1,
  hire_rate_percentage: 60,
  capacity: { employees_per_pharmacy: 2, total_slots: 4, filled_slots: 3 },
};

const onboarding = {
  from: "2025-09-01",
  to: "2026-08-31",
  total: 3,
  points: [
    { month: "2025-09", label: "Sep 2025", registrations: 0 },
    { month: "2025-10", label: "Oct 2025", registrations: 1 },
    { month: "2025-11", label: "Nov 2025", registrations: 0 },
    { month: "2026-08", label: "Aug 2026", registrations: 2 },
  ],
};

function mockAnalytics() {
  server.use(
    http.get(`${BASE}/api/admin/analytics/pharmacies`, () => HttpResponse.json({ data: fleet })),
    http.get(`${BASE}/api/admin/analytics/job-market`, () =>
      HttpResponse.json({ data: jobMarket }),
    ),
    http.get(`${BASE}/api/admin/analytics/onboarding`, () =>
      HttpResponse.json({ data: onboarding }),
    ),
  );
}

function renderReports() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <ReportsView />
    </QueryClientProvider>,
  );
}

describe("ReportsView", () => {
  it("renders every live figure once the three endpoints resolve", async () => {
    mockAnalytics();
    renderReports();

    await waitFor(() => expect(screen.getByText("Pharmacy Fleet")).toBeInTheDocument());

    // Fleet: donut total is the operating owners, not every registered owner.
    expect(screen.getByText("Owners")).toBeInTheDocument();
    expect(screen.getAllByText("50%")).toHaveLength(2);

    // Job market, including the derived capacity note.
    expect(screen.getByText("60%")).toBeInTheDocument();
    expect(screen.getByText("3 of 4 slots filled")).toBeInTheDocument();
    expect(screen.getByText("5 applied in total")).toBeInTheDocument();

    // Onboarding total.
    expect(screen.getByText("Total registrations")).toBeInTheDocument();
  });

  it("exposes the chart as text so the data is not trapped in the bars", async () => {
    mockAnalytics();
    renderReports();

    await waitFor(() => expect(screen.getByText("Onboarding Trend")).toBeInTheDocument());

    expect(screen.getByText("Oct 2025: 1 registrations")).toBeInTheDocument();
    expect(screen.getByText("Aug 2026: 2 registrations")).toBeInTheDocument();
  });

  it("tells the administrator when a pharmacy has never been approved", async () => {
    server.use(
      http.get(`${BASE}/api/admin/analytics/pharmacies`, () =>
        HttpResponse.json({
          data: {
            ...fleet,
            owners_operating: 0,
            branches: { approved: 0, pending: 0, rejected: 0 },
            distribution: {
              single_branch_owners: 0,
              multi_branch_owners: 0,
              single_branch_percentage: 0,
              multi_branch_percentage: 0,
            },
          },
        }),
      ),
      http.get(`${BASE}/api/admin/analytics/job-market`, () =>
        HttpResponse.json({ data: jobMarket }),
      ),
      http.get(`${BASE}/api/admin/analytics/onboarding`, () =>
        HttpResponse.json({ data: onboarding }),
      ),
    );
    renderReports();

    await waitFor(() =>
      expect(screen.getByText("No approved pharmacies yet.")).toBeInTheDocument(),
    );
  });

  it("shows one error state with retry if any endpoint fails", async () => {
    server.use(
      http.get(`${BASE}/api/admin/analytics/pharmacies`, () =>
        HttpResponse.json({ message: "boom", code: "server_error" }, { status: 500 }),
      ),
      http.get(`${BASE}/api/admin/analytics/job-market`, () =>
        HttpResponse.json({ data: jobMarket }),
      ),
      http.get(`${BASE}/api/admin/analytics/onboarding`, () =>
        HttpResponse.json({ data: onboarding }),
      ),
    );
    renderReports();

    const alert = await screen.findByRole("alert");
    expect(alert).toHaveTextContent(/unable to load analytics/i);

    // Retrying re-requests: this time the endpoint answers.
    mockAnalytics();
    await userEvent.click(screen.getByRole("button", { name: /retry/i }));

    await waitFor(() => expect(screen.getByText("Pharmacy Fleet")).toBeInTheDocument());
  });
});
