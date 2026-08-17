import { describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { CommandPalette } from "@/components/layout/CommandPalette";

const BASE = "http://localhost:8000";
const navigate = vi.fn();

vi.mock("@tanstack/react-router", () => ({
  useNavigate: () => navigate,
}));

vi.mock("@/context/AuthContext", () => ({
  useAuth: () => ({
    navigation: { review_pharmacies: true, manage_admins: true },
  }),
}));

function results(pharmacies: unknown[] = [], tickets: unknown[] = []) {
  return HttpResponse.json({ data: { query: "x", pharmacies, tickets } });
}

function renderPalette() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <CommandPalette />
    </QueryClientProvider>,
  );
}

describe("CommandPalette", () => {
  it("opens with Ctrl+K and closes with Escape", async () => {
    renderPalette();
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

    await userEvent.keyboard("{Control>}k{/Control}");
    expect(await screen.findByRole("dialog")).toBeInTheDocument();

    await userEvent.keyboard("{Escape}");
    await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());
  });

  it("lists every screen before anything is typed", async () => {
    renderPalette();
    await userEvent.click(screen.getByRole("button", { name: /search pharmacies/i }));

    expect(await screen.findByText("Dashboard")).toBeInTheDocument();
    expect(screen.getByText("Verification")).toBeInTheDocument();
    expect(screen.getByText("Administrators")).toBeInTheDocument();
  });

  it("navigates to the screen that is picked", async () => {
    renderPalette();
    await userEvent.click(screen.getByRole("button", { name: /search pharmacies/i }));
    await userEvent.click(await screen.findByText("Analytics"));

    expect(navigate).toHaveBeenCalledWith({ to: "/reports" });
  });

  it("does not search on a single character", async () => {
    const searched = vi.fn();
    server.use(
      http.get(`${BASE}/api/admin/search`, () => {
        searched();
        return results();
      }),
    );
    renderPalette();
    await userEvent.click(screen.getByRole("button", { name: /search pharmacies/i }));
    await userEvent.type(await screen.findByLabelText(/search pharmacies/i), "a");

    // One keystroke is not a query; the server is left alone.
    await waitFor(() => expect(screen.queryByText("Searching…")).not.toBeInTheDocument());
    expect(searched).not.toHaveBeenCalled();
  });

  it("shows matching pharmacies and tickets from the server", async () => {
    server.use(
      http.get(`${BASE}/api/admin/search`, () =>
        results(
          [
            {
              id: 1,
              title: "Barada Pharmacy",
              detail: "Maya Alhaj · Al-Mazzeh, Damascus",
              status: "approved",
            },
          ],
          [
            {
              id: 3,
              title: "Cannot upload licence",
              detail: "Rana",
              status: "open",
            },
          ],
        ),
      ),
    );
    renderPalette();
    await userEvent.click(screen.getByRole("button", { name: /search pharmacies/i }));
    await userEvent.type(await screen.findByLabelText(/search pharmacies/i), "barada");

    expect(await screen.findByText("Barada Pharmacy")).toBeInTheDocument();
    expect(screen.getByText("Cannot upload licence")).toBeInTheDocument();
    expect(screen.getByText("Pharmacies")).toBeInTheDocument();
    expect(screen.getByText("Support tickets")).toBeInTheDocument();
  });

  it("says so when nothing matches", async () => {
    server.use(http.get(`${BASE}/api/admin/search`, () => results()));
    renderPalette();
    await userEvent.click(screen.getByRole("button", { name: /search pharmacies/i }));
    await userEvent.type(await screen.findByLabelText(/search pharmacies/i), "zzzznothing");

    expect(await screen.findByText(/nothing matches/i)).toBeInTheDocument();
  });
});
