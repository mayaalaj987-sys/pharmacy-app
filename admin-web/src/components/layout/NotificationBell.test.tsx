import { describe, expect, it } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
  createMemoryHistory,
  createRootRoute,
  createRouter,
  RouterProvider,
} from "@tanstack/react-router";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { NotificationBell } from "@/components/layout/NotificationBell";

const BASE = "http://localhost:8000";

function inbox(applications: number, tickets: number, items: unknown[] = []) {
  return HttpResponse.json({
    data: {
      total: applications + tickets,
      groups: { pharmacy_applications: applications, support_tickets: tickets },
      items,
    },
  });
}

const application = {
  id: "pharmacy-7",
  kind: "pharmacy_application" as const,
  title: "Al-Salam Pharmacy",
  detail: "Applied for verification · Khalid Al-Homsi",
  at: new Date(Date.now() - 3600_000).toISOString(),
};

const ticket = {
  id: "ticket-3",
  kind: "support_ticket" as const,
  title: "Cannot upload trade licence",
  detail: "Support request · Maya Alhaj",
  at: new Date(Date.now() - 300_000).toISOString(),
};

/** The bell links into the console, so it needs a router around it. */
function renderBell() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const rootRoute = createRootRoute({ component: NotificationBell });
  const router = createRouter({
    routeTree: rootRoute,
    history: createMemoryHistory({ initialEntries: ["/"] }),
  });

  return render(
    <QueryClientProvider client={queryClient}>
      {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
      <RouterProvider router={router as any} />
    </QueryClientProvider>,
  );
}

describe("NotificationBell", () => {
  it("badges the total waiting and names each group", async () => {
    server.use(http.get(`${BASE}/api/admin/inbox`, () => inbox(2, 1, [ticket, application])));
    renderBell();

    const bell = await screen.findByRole("button", {
      name: /notifications, 3 waiting/i,
    });
    expect(within(bell).getByText("3")).toBeInTheDocument();

    await userEvent.click(bell);
    expect(screen.getByText(/2 application\(s\) · 1 ticket\(s\)/i)).toBeInTheDocument();
  });

  it("lists what arrived, newest first, in plain language", async () => {
    server.use(http.get(`${BASE}/api/admin/inbox`, () => inbox(1, 1, [ticket, application])));
    renderBell();

    await userEvent.click(await screen.findByRole("button", { name: /notifications/i }));

    expect(screen.getByText("Cannot upload trade licence")).toBeInTheDocument();
    expect(screen.getByText(/Applied for verification/)).toBeInTheDocument();
    // Relative, not a timestamp to the second.
    expect(screen.getByText("5 minutes ago")).toBeInTheDocument();
    expect(screen.getByText("1 hour ago")).toBeInTheDocument();
  });

  it("sends each entry to the screen that resolves it", async () => {
    server.use(http.get(`${BASE}/api/admin/inbox`, () => inbox(1, 1, [ticket, application])));
    renderBell();

    await userEvent.click(await screen.findByRole("button", { name: /notifications/i }));

    expect(screen.getByText("Al-Salam Pharmacy").closest("a")).toHaveAttribute("href", "/review");
    expect(screen.getByText("Cannot upload trade licence").closest("a")).toHaveAttribute(
      "href",
      "/tickets",
    );
  });

  it("shows no badge when nothing is waiting", async () => {
    server.use(http.get(`${BASE}/api/admin/inbox`, () => inbox(0, 0)));
    renderBell();

    const bell = await screen.findByRole("button", { name: "Notifications" });
    // No count at all rather than a "0" the eye still has to read.
    expect(within(bell).queryByText("0")).not.toBeInTheDocument();

    await userEvent.click(bell);
    expect(screen.getByText(/nothing needs your attention/i)).toBeInTheDocument();
  });
});
