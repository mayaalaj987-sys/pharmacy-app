import { describe, expect, it } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { DashboardView } from "@/components/dashboard/DashboardView";

const BASE = "http://localhost:8000";

const overview = {
  compared_to: "2026-07-31",
  totals: {
    registered: { value: 12, change: { from: 10, delta: 2, percent: 20 } },
    approved: { value: 8, change: { from: 8, delta: 0, percent: 0 } },
    pending: { value: 3, change: { from: 1, delta: 2, percent: 200 } },
  },
  pharmacies: [
    {
      id: 1,
      name: "Al-Salam Pharmacy",
      address: "Al-Mazzeh, Damascus",
      owner: "Khalid Al-Homsi",
      status: "pending" as const,
    },
    {
      id: 2,
      name: "Alhajj Pharmacy",
      address: "Aleppo",
      owner: "Maya Alhaj",
      status: "approved" as const,
    },
  ],
};

const onboarding = {
  from: "2025-09-01",
  to: "2026-08-31",
  total: 4,
  points: [
    { month: "2025-09", label: "Sep 2025", registrations: 1 },
    { month: "2026-08", label: "Aug 2026", registrations: 3 },
  ],
};

const activity = [
  {
    id: 5,
    actor: "Aya",
    action: "pharmacy.review.approved",
    label: "approved a pharmacy",
    outcome: "success",
    target: "Pharmacy #2",
    reason: null,
    logged_at: "2026-08-17T10:00:00+00:00",
  },
  {
    id: 4,
    actor: "Rana",
    action: "admin.accounts.index",
    label: "accounts index (denied)",
    outcome: "denied",
    target: null,
    reason: "not_super_admin",
    logged_at: "2026-08-17T09:00:00+00:00",
  },
];

function mockAll() {
  server.use(
    http.get(`${BASE}/api/admin/analytics/overview`, () => HttpResponse.json({ data: overview })),
    http.get(`${BASE}/api/admin/analytics/onboarding`, () =>
      HttpResponse.json({ data: onboarding }),
    ),
    http.get(`${BASE}/api/admin/activity`, () => HttpResponse.json({ data: activity })),
  );
}

describe("DashboardView", () => {
  it("shows live counts with real month-over-month movement", async () => {
    mockAll();
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });
    render(
      <QueryClientProvider client={queryClient}>
        <DashboardView />
      </QueryClientProvider>,
    );

    await waitFor(() => expect(screen.getByText("Registered pharmacies")).toBeInTheDocument());

    // Scoped per card, then anchored on the trend line: several cards show
    // the same delta, and "+20%" itself contains "+2".
    const registered = within(screen.getByText("Registered pharmacies").closest("section")!);
    expect(registered.getByText("12")).toBeInTheDocument();

    const trend = registered.getByText("vs last month").closest("p")!;
    expect(trend).toHaveTextContent("+2");
    expect(trend).toHaveTextContent("(+20%)");

    // A flat metric says so, with no percentage at all.
    const approved = within(screen.getByText("Approved pharmacies").closest("section")!);
    expect(approved.getByText("No change")).toBeInTheDocument();
    expect(approved.getByText("vs last month").closest("p")!).not.toHaveTextContent("%");
  });

  it("renders the audit log as the activity feed, denials included", async () => {
    mockAll();
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });
    render(
      <QueryClientProvider client={queryClient}>
        <DashboardView />
      </QueryClientProvider>,
    );

    await waitFor(() => expect(screen.getByText("Recent Activity")).toBeInTheDocument());

    expect(screen.getByText("approved a pharmacy")).toBeInTheDocument();
    expect(screen.getByText("accounts index (denied)")).toBeInTheDocument();
    expect(screen.getByText(/cannot be edited or deleted/i)).toBeInTheDocument();
  });

  it("explains an empty feed instead of showing a blank panel", async () => {
    server.use(
      http.get(`${BASE}/api/admin/analytics/overview`, () => HttpResponse.json({ data: overview })),
      http.get(`${BASE}/api/admin/analytics/onboarding`, () =>
        HttpResponse.json({ data: onboarding }),
      ),
      http.get(`${BASE}/api/admin/activity`, () => HttpResponse.json({ data: [] })),
    );
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });
    render(
      <QueryClientProvider client={queryClient}>
        <DashboardView />
      </QueryClientProvider>,
    );

    await waitFor(() => expect(screen.getByText(/nothing has happened yet/i)).toBeInTheDocument());
  });

  it("offers a retry when the overview fails", async () => {
    server.use(
      http.get(`${BASE}/api/admin/analytics/overview`, () =>
        HttpResponse.json({ message: "boom", code: "server_error" }, { status: 500 }),
      ),
    );
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });
    render(
      <QueryClientProvider client={queryClient}>
        <DashboardView />
      </QueryClientProvider>,
    );

    const alert = await screen.findByRole("alert");
    expect(alert).toHaveTextContent(/unable to load the dashboard/i);
    expect(screen.getByRole("button", { name: /retry/i })).toBeInTheDocument();
  });
});
