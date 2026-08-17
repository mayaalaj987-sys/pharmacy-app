import { describe, expect, it, vi } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { ToastProvider } from "@/components/ui-custom/Toast";
import { PharmaciesView } from "@/components/pharmacies/PharmaciesView";

const BASE = "http://localhost:8000";

const trading = {
  id: 1,
  name: "Alhajj Pharmacy",
  address: "Al-Mazzeh, Damascus",
  status: "approved" as const,
  is_blocked: false,
  blocked_reason: null,
  blocked_at: null,
  owner: {
    id: 4,
    name: "Maya Alhaj",
    email: "maya@example.test",
    branches: 2,
    app_rating: 4,
  },
};

const suspended = {
  ...trading,
  id: 2,
  name: "Barada Pharmacy",
  is_blocked: true,
  blocked_reason: "Licence expired.",
  blocked_at: "2026-08-17T10:00:00+00:00",
  owner: { ...trading.owner, id: 5, name: "Rana", app_rating: null },
};

function page(rows: unknown[], blockedTotal = 0) {
  return HttpResponse.json({
    data: rows,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: rows.length,
      blocked_total: blockedTotal,
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
        <PharmaciesView />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

describe("PharmaciesView", () => {
  it("lists a pharmacy with its owner, branches and app rating", async () => {
    server.use(http.get(`${BASE}/api/admin/pharmacies`, () => page([trading])));
    renderView();

    await waitFor(() => expect(screen.getByText("Alhajj Pharmacy")).toBeInTheDocument());

    const row = screen.getByText("Alhajj Pharmacy").closest("tr")!;
    expect(within(row).getByText("Maya Alhaj")).toBeInTheDocument();
    expect(within(row).getByText("2")).toBeInTheDocument();
    // The stars are the owner's rating of the app, so the label says /5.
    expect(within(row).getByText("4/5")).toBeInTheDocument();
  });

  it("says so when an owner never rated the app, rather than showing zero", async () => {
    server.use(http.get(`${BASE}/api/admin/pharmacies`, () => page([suspended], 1)));
    renderView();

    await waitFor(() => expect(screen.getByText("Not rated")).toBeInTheDocument());
    expect(screen.queryByText("0/5")).not.toBeInTheDocument();
  });

  it("suspends a pharmacy with a reason", async () => {
    const blocked = vi.fn();
    server.use(
      http.get(`${BASE}/api/admin/pharmacies`, () => page([trading])),
      http.post(`${BASE}/api/admin/pharmacies/1/block`, async ({ request }) => {
        blocked(await request.json());
        return HttpResponse.json({ message: "Suspended.", code: "pharmacy_blocked" });
      }),
    );
    renderView();

    await waitFor(() => expect(screen.getByText("Alhajj Pharmacy")).toBeInTheDocument());
    await userEvent.click(screen.getByRole("button", { name: /suspend/i }));

    const dialog = await screen.findByRole("dialog");
    // The dialog spells out that the approval survives.
    expect(dialog).toHaveTextContent(/approval itself is kept/i);

    await userEvent.type(screen.getByLabelText(/reason/i), "Licence expired.");
    await userEvent.click(within(dialog).getByRole("button", { name: /^suspend$/i }));

    await waitFor(() => expect(blocked).toHaveBeenCalledWith({ reason: "Licence expired." }));
  });

  it("refuses to suspend without a reason", async () => {
    const blocked = vi.fn();
    server.use(
      http.get(`${BASE}/api/admin/pharmacies`, () => page([trading])),
      http.post(`${BASE}/api/admin/pharmacies/1/block`, () => {
        blocked();
        return HttpResponse.json({});
      }),
    );
    renderView();

    await waitFor(() => expect(screen.getByText("Alhajj Pharmacy")).toBeInTheDocument());
    await userEvent.click(screen.getByRole("button", { name: /suspend/i }));

    const dialog = await screen.findByRole("dialog");
    await userEvent.click(within(dialog).getByRole("button", { name: /^suspend$/i }));

    await waitFor(() => expect(screen.getByText(/reason required/i)).toBeInTheDocument());
    expect(blocked).not.toHaveBeenCalled();
  });

  it("restores a suspended pharmacy and shows why it was suspended", async () => {
    const restored = vi.fn();
    server.use(
      http.get(`${BASE}/api/admin/pharmacies`, () => page([suspended], 1)),
      http.post(`${BASE}/api/admin/pharmacies/2/unblock`, () => {
        restored();
        return HttpResponse.json({ message: "Restored.", code: "pharmacy_unblocked" });
      }),
    );
    renderView();

    await waitFor(() => expect(screen.getByText("Licence expired.")).toBeInTheDocument());
    await userEvent.click(screen.getByRole("button", { name: /restore/i }));

    await waitFor(() => expect(restored).toHaveBeenCalled());
  });

  it("surfaces a conflict when someone else already suspended it", async () => {
    server.use(
      http.get(`${BASE}/api/admin/pharmacies`, () => page([trading])),
      http.post(`${BASE}/api/admin/pharmacies/1/block`, () =>
        HttpResponse.json(
          {
            message: "This pharmacy is already suspended.",
            code: "pharmacy_already_blocked",
          },
          { status: 409 },
        ),
      ),
    );
    renderView();

    await waitFor(() => expect(screen.getByText("Alhajj Pharmacy")).toBeInTheDocument());
    await userEvent.click(screen.getByRole("button", { name: /suspend/i }));

    const dialog = await screen.findByRole("dialog");
    await userEvent.type(screen.getByLabelText(/reason/i), "My own reason.");
    await userEvent.click(within(dialog).getByRole("button", { name: /^suspend$/i }));

    await waitFor(() => expect(screen.getByText(/already suspended/i)).toBeInTheDocument());
  });

  it("offers no suspend action for a pharmacy that is not trading", async () => {
    server.use(
      http.get(`${BASE}/api/admin/pharmacies`, () =>
        page([{ ...trading, status: "pending" as const }]),
      ),
    );
    renderView();

    await waitFor(() => expect(screen.getByText("Not trading")).toBeInTheDocument());
    expect(screen.queryByRole("button", { name: /suspend/i })).not.toBeInTheDocument();
  });
});
